<?php
/***
 * Lightweight PHP script for handling XThreads attachment downloads
 * Is lightweight as it only includes one file (MyBB settings file) and can work without querying the DB at all
 * (and even if it does, it loads a minimal DB engine - even less than the AJAX core)
 * An issue with this is that it relies on data passed in the URL to be fairly accurate, although inaccurate/forged information won't cause any serious issue
 *
 * This also has some nice features that MyBB's attachment system can't deliver, including caching, better handling of larger files (ranged requests, less memory usage etc), header only requests,  etc
 */


/**
 * if disabled, MyBB core will not be loaded
 * the original idea was to perform permission checking, but I have not decided to implement this; file downloads probably still work with it turned on, however, there isn't any reason to do this
 * may be useful to enable if you need data from the core though
 */
define('LOAD_SESSION', false);
// need session class, mybb class, db class
// OR, just check cookie for session & load DB to verify session + check perms
// if using error_no_permission, must load entire global core



if(LOAD_SESSION) {
	// we'll be lazy and just load the full MyBB core
	define('IN_MYBB', 1);
	define('THIS_SCRIPT', 'xthreads_attach.php');
	define('NO_ONLINE', 1); // TODO: check
	
	require './global.php';
	
	// TODO: disable calling send_page_headers()
	
	// TODO: maybe do online for WOL
}
else {
	
	// do some basic initialisation
	error_reporting(E_ALL ^ E_NOTICE); // this script works fine with E_ALL, however, we'll be compatible with MyBB
	// remove unnecessary stuff
	unset($HTTP_SERVER_VARS, $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_COOKIE_VARS, $HTTP_POST_FILES, $HTTP_ENV_VARS, $HTTP_SESSION_VARS);
	unset($_GET, $_POST, $_FILES, $_ENV);
	foreach(array('GLOBALS', '_COOKIE', '_REQUEST', '_SERVER') as $p)
		if(isset($_REQUEST[$p]) || isset($_FILES[$p]) || isset($_COOKIE[$p])) {
			header('HTTP/1.1 400 Bad Request');
			die('Bad request');
		}
	// script will work if magic quotes is on, unless filenames happen to have quotes or something
	@set_magic_quotes_runtime(0);
	@ini_set('magic_quotes_runtime', 0); 
	// will also work with register globals, so we won't bother with these
	
	
	define('MYBB_ROOT', dirname(__FILE__).'/');
	@include_once(MYBB_ROOT.'cache/xthreads.php'); // include defines
}


// put everything in function to limit scope (and memory usage by relying on PHP to garbage collect all the unreferenced variables)
function do_processing() {
	
	if(is_object(@$GLOBALS['mybb'])) {
		$basedir = $GLOBALS['mybb']->settings['uploadspath'].'/xthreads_ul/';
		$bburl = $GLOBALS['mybb']->settings['bburl'];
	} else {
		if(file_exists(MYBB_ROOT.'inc/settings.php')) {
			require MYBB_ROOT.'inc/settings.php';
			// TODO: perhaps have a dedicated setting for this one
			$basedir = $settings['uploadspath'].'/xthreads_ul/';
			$bburl = $settings['bburl'];
			unset($settings);
		}
		else // use default
			$basedir = './uploads/xthreads_ul/';
			$bburl = 'htp://example.com/'; // dummy
	}
	
	if(!@is_dir($basedir)) {
		header('HTTP/1.1 500 Internal Server Error');
		die('Can\'t find XThreads base directory.');
	}

	// parse input filename
	if(isset($_REQUEST['file']) && $_REQUEST['file'] !== '') { // using query string
		$_SERVER['PATH_INFO'] = '/'.$_REQUEST['file'];
		//if(get_magic_quotes_gpc())
		//	$_SERVER['PATH_INFO'] = stripslashes($_SERVER['PATH_INFO']);
	} else {
		if(!isset($_SERVER['PATH_INFO'])) {
			if(isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['PHP_SELF'])) {
				$snlen = strlen($_SERVER['SCRIPT_NAME']);
				if(substr($_SERVER['PHP_SELF'], 0, $snlen) == $_SERVER['SCRIPT_NAME'])
					$_SERVER['PATH_INFO'] = substr($_SERVER['PHP_SELF'], $snlen);
			}
		}

		if(!isset($_SERVER['PATH_INFO']) || !$_SERVER['PATH_INFO']) {
			header('HTTP/1.1 400 Bad Request');
			die('No parameters specified.');
		}
	}

	// maybe disallow \:*?"<>| in filenames, but then, they're valid *nix names...
	if(!preg_match('~^[/|]([0-9]+)_([0-9]+)_([0-9a-fA-F]{8})[/|]([0-9a-fA-F]{32}[/|])?([^/]*)([/|]thumb([0-9]+x[0-9]+))?$~', $_SERVER['PATH_INFO'], $match)) {
		header('HTTP/1.1 400 Bad Request');
		die('Received malformed request string.');
	}

	$thumb = null;
	if($match[6]) $thumb =& $match[7];
	//if(isset($_REQUEST['thumb']) && preg_match('~^[0-9]+x[0-9]+$~', $_REQUEST['thumb']))
	if($thumb)
		$fext = $thumb.'.thumb';
	else
		$fext = 'upload';

	$match[5] = str_replace("\0", '', $match[5]);
	$month_dir = 'ts_'.floor($match[2] / 1000000).'/';
	$fn = 'file_'.$match[1].'_'.$match[3].'_'.preg_replace('~[^a-zA-Z0-9_\-%]~', '', str_replace(array(' ', '.', '+'), '_', $match[5])).'.'.$fext;
	if(file_exists($basedir.$month_dir.$fn))
		$fn_rel = $month_dir.$fn;
	elseif(file_exists($basedir.$fn))
		$fn_rel = $fn;
	else {
		header('HTTP/1.1 404 Not Found');
		die('Specified attachment not found.');
	}
	$fn = $basedir.$fn_rel;

	// check to see if unmodified/cached
	$cached = false;
	$modtime = filemtime($fn);
	if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($cachetime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) && $cachetime > 0) {
		$cached = ($cachetime >= $modtime);
	}
	$etag = '"xthreads_attach_'.substr(md5($bburl), 0, 8).'_'.$match[1].'_'.$match[2].'_'.$match[3].'"';
	if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && ($etag_match = trim($_SERVER['HTTP_IF_NONE_MATCH']))) {
		if($etag_match == $etag) $cached = true;
		elseif(strpos($etag_match, ',') && in_array($etag, array_map('trim', explode(',', $etag_match))))
			$cached = true;
		else
			$cached = false;
	}

	if($cached) {
		header('HTTP/1.1 304 Not Modified');
		header('ETag: '.$etag);
		header('Vary: Range');
		exit;
	}

	global $fp, $fsize, $range_start, $range_end, $plugins;
	if(is_object($plugins)) {
		$evalcode = '';
		$evalcode = $plugins->run_hooks('xthreads_attachment_before_headers', $evalcode);
		if($evalcode)
			eval($evalcode);
		unset($evalcode);
	}
	
	if(!XTHREADS_PROXY_REDIR_HEADER_PREFIX) {
		$fp = fopen($fn, 'rb');
		if(!$fp) {
			header('HTTP/1.1 500 Internal Server Error');
			die('Failed to open file.');
		}
		
		$range_start = 0;
		$fsize = filesize($fn);
		$range_end = $fsize-1;

		if(isset($_SERVER['HTTP_RANGE']) && ($p = strpos($_SERVER['HTTP_RANGE'], '='))) {
			$rangestr = substr($_SERVER['HTTP_RANGE'], $p+1);
			$p = strpos($rangestr, '-');
			$ostart = (int)substr($rangestr, 0, $p);
			$oend = (int)substr($rangestr, $p+1);
			
			if($oend && $oend < $range_end && $oend > $range_start)
				$range_end = $oend;
			if($ostart && $ostart > $range_start && $ostart < $range_end)
				$range_start = $ostart;
		}
		
		if($range_start || $range_end != $fsize-1) {
			// check If-Range header
			$cached = true; // reuse this variable
			if(isset($_SERVER['HTTP_IF_RANGE']) && ($etag_match = trim($_SERVER['HTTP_IF_RANGE']))) {
				if($etag_match != $etag && (!strpos($etag_match, ',') || !in_array($etag, array_map('trim', explode(',', $etag_match))))) {
					$cached = false;
					// re-send whole file
					$range_start = 0;
					$range_end = $fsize -1;
				}
			}
			if($cached)
				header('HTTP/1.1 206 Partial Content');
		}

		if(XTHREADS_COUNT_DOWNLOADS == 1 && !$thumb) increment_downloads($match[1]);
		header('Accept-Ranges: bytes');
	} else {
		if(XTHREADS_COUNT_DOWNLOADS && !$thumb) increment_downloads($match[1]);
	}
	header('Allow: GET, HEAD');
	header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modtime).'GMT');
	header('Expires: '.gmdate('D, d M Y H:i:s', time() + XTHREADS_CACHE_TIME).'GMT');
	header('Cache-Control: max-age='.XTHREADS_CACHE_TIME);
	header('ETag: '.$etag);
	header('Vary: Range');

	// check referrer?

	// TODO: perhaps think of a way to store thumbs w/ proper extension
	// try to determine Content-Type
	$content_type = '';
	$p = strrpos($match[5], '.');
	if($p) {
		$ext = strtolower(substr($match[5], $p+1));
		$exts = array(
			'txt' => 'text/plain',
			'jpg' => 'image/jpeg',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png',
			'bmp' => 'image/bmp',
			'svg' => 'image/svg+xml',
			'tif' => 'image/tiff',
			'tiff' => 'image/tiff',
			'ico' => 'image/x-icon',
			'wmf' => 'application/x-msmetafile',
			'zip' => 'application/zip',
			'rar' => 'application/x-rar-compressed',
			'7z' => 'application/x-7z-compressed',
			'doc' => 'application/msword',
			'docx' => 'application/msword',
			'xls' => 'application/msexcel',
			'xlsx' => 'application/msexcel',
			'ppt' => 'application/mspowerpoint',
			'pptx' => 'application/mspowerpoint',
			'mdb' => 'application/x-msaccess',
			'pub' => 'application/x-mspublisher',
			'pdf' => 'application/pdf',
			'gz' => 'application/x-gzip',
			'tar' => 'application/x-tar',
			'htm' => 'text/html',
			'html' => 'text/html',
			'css' => 'text/css',
			'js' => 'text/javascript',
			'mid' => 'audio/mid',
			'mp3' => 'audio/mpeg',
			'flac' => 'audio/flac',
			'ogg' => 'audio/ogg',
			'wav' => 'audio/x-wav',
			'mpeg' => 'video/mpeg',
			'mpg' => 'video/mpeg',
			'mov' => 'video/quicktime',
			'avi' => 'video/x-msvideo',
			'mp4' => 'video/mp4',
			'm4a' => 'audio/mp4',
			'mkv' => 'video/x-matroska',
			'mka' => 'audio/x-matroska',
			'ogv' => 'video/ogg',
			'wmv' => 'audio/x-ms-wmv',
		);
		if(isset($exts[$ext]))
			$content_type = $exts[$ext];
		unset($exts);
		
		if(!$content_type) {
			// try MyBB's attachment cache if cached to files
			if(file_exists(MYBB_ROOT.'cache/attachtypes.php')) {
				@include MYBB_ROOT.'cache/attachtypes.php';
				if(isset($attachtypes) && is_array($attachtypes) && isset($attachtypes[$ext])) {
					$content_type = $attachtypes[$ext]['mimetype'];
				}
				unset($attachtypes);
			}
		}
	}
	if(!$content_type) {
		// try system MIME file
		if(function_exists('mime_content_type'))
			//$content_type = @mime_content_type($match[5]);
			$content_type = @mime_content_type($fn);
		elseif(function_exists('finfo_open') && ($fi = @finfo_open(FILEINFO_MIME))) {
			$content_type = @finfo_file($fi, $fn);
			finfo_close($fi);
		}
	}
	if(!$content_type) // fallback
		$content_type = 'application/octet-stream';
	header('Content-Type: '.$content_type);

	if(!$thumb) { // don't send disposition for thumbnails
		$disposition = 'attachment';
		if(!isset($_REQUEST['download']) || !$_REQUEST['download'])
			if(!isset($_SERVER['HTTP_USER_AGENT']) || strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') === false) {
				switch(strtolower($content_type)) {
					case 'text/plain': case 'text/css': case 'text/javascript':
					case 'application/pdf':
						$disposition = 'inline';
						break;
					default:
						if(in_array(substr($content_type, 0, 6), array('image/', 'audio/', 'video/')))
							$disposition = 'inline';
							// Does this work well with IE's type sniffing?
				}
			}
		header('Content-Disposition: '.$disposition.'; filename="'.strtr($match[5], array('"'=>'\\"', "\r"=>'', "\n"=>'')).'"');
	}

	if(XTHREADS_PROXY_REDIR_HEADER_PREFIX) {
		// we terminate here and let the webserver do the rest of the work
		header(XTHREADS_PROXY_REDIR_HEADER_PREFIX.$fn_rel);
		exit;
	}
	
	if($range_end < 0) {
		// this is a 0 byte file
		header('Content-Length: 0');
		fclose($fp);
		exit;
	}

	header('Content-Length: '.($range_end - $range_start + 1));
	header('Content-Range: bytes '.$range_start.'-'.$range_end.'/'.$fsize);
	if(!$range_start && $range_end == $fsize-1 && strlen($match[4]) == 33)
		header('Content-MD5: '.base64_encode(pack('H*', substr($match[4], 0, 32))));

	if(isset($_SERVER['REQUEST_METHOD']))
		$reqmeth = strtoupper($_SERVER['REQUEST_METHOD']);
	else
		$reqmeth = 'GET';
	
	if($reqmeth == 'HEAD') {
		fclose($fp);
		exit;
	}
	
	if(XTHREADS_COUNT_DOWNLOADS == 2 && !$thumb)
		$GLOBALS['aid'] = $match[1]; // increment download below
	
} do_processing();

// kill unneeded variables - save memory as this PHP thread may last a while on the server especially for larger downloads
unset($_REQUEST, $_COOKIE, $_SERVER);
/* $keepvars = array('keepvars'=>1, 'k'=>1, 'v'=>1, 'GLOBALS'=>1, 'fp'=>1, 'fsize'=>1, 'range_start'=>1, 'range_end'=>1, 'thumb'=>1, 'match'=>1);
// note, thumb may be a reference to match
foreach($GLOBALS as $k => &$v) {
	if(!isset($keepvars[$k]))
		unset($GLOBALS[$k]);
}
unset($keepvars, $k, $v); */

if(LOAD_SESSION) unset($mybb, $db); // TODO: maybe also unload other vars

if(!function_exists('stream_copy_to_stream')) {
	function stream_copy_to_stream($source, $dest, $maxlength=-1, $offset=0) {
		if($offset)
			fseek($source, $offset, SEEK_CUR);
		$copied = 0;
		while(!feof($source) && ($maxlength == -1 || $copied < $maxlength)) {
			$len = 16384;
			if($maxlength > -1) $len = min($maxlength-$copied, $len);
			$data = fread($source, $len);
			$copied += strlen($data);
			fwrite($dest, $data);
		}
		return $copied;
	}
}

if(is_object($plugins)) $plugins->run_hooks('xthreads_attachment_before_download');

$fout = fopen('php://output', 'wb'); // this call shouldn't fail, right?

if($range_start)
	fseek($fp, $range_start);

if($range_end == $fsize-1) {
	unset($range_start, $range_end, $fsize);
	stream_copy_to_stream($fp, $fout);
	//while(!feof($fp)) echo fread($fp, 16384);
	if(isset($aid)) increment_downloads($aid);
} else {
	$bytes = $range_end - $range_start + 1;
	unset($aid, $range_start, $range_end, $fsize);
	stream_copy_to_stream($fp, $fout, $bytes);
	/* unset($aid, $range_start, $range_end, $fsize);
	while(!feof($fp) && $bytes > 0) {
		$bufsize = min($bytes, 16384);
		echo fread($fp, $bufsize);
		$bytes -= $bufsize;
	} */
}

fclose($fp);
fclose($fout);


function increment_downloads($aid) {
	// if DB is loaded, use it
	if(is_object(@$GLOBALS['db'])) {
		$GLOBALS['db']->write_query('UPDATE '.$db->table_prefix.'xtattachments SET downloads=downloads+1 WHERE aid='.(int)$aid, 1);
		return;
	}
	
	// otherwise, load config + MyBB's DB engine
	if(!file_exists(MYBB_ROOT.'inc/config.php')) return;
	require_once MYBB_ROOT.'inc/config.php';
	if(!isset($config['database']) || !is_array($config['database'])) return;
	
	// create dummy classes before loading DB
	class dummy_mybb {
		var $debug_mode = false;
	}
	$GLOBALS['mybb'] = new dummy_mybb;
	
	
	$dbclass = 'db_'.$config['database']['type'];
	require_once MYBB_ROOT.'inc/'.$dbclass.'.php';
	if(!class_exists($dbclass)) return;
	$db = new $dbclass;
	if(!extension_loaded($db->engine)) {
		if(!function_exists('dl')) return;
		if(DIRECTORY_SEPARATOR == '\\')
			@dl('php_'.$db->engine.'.dll');
		else
			@dl($db->engine.'.so');
		if(!extension_loaded($db->engine)) return;
	}
	
	// connect to DB
	define('TABLE_PREFIX', $config['database']['table_prefix']);
	$db->connect($config['database']);
	$db->set_table_prefix(TABLE_PREFIX);
	$db->type = $config['database']['type'];
	
	if(is_object($GLOBALS['plugins'])) {
		$GLOBALS['plugins']->run_hooks('xthreads_attachment_increment_dlcount', $aid);
	}
	
	// so we do all the above just to run an update query :P
	$db->write_query('UPDATE '.$db->table_prefix.'xtattachments SET downloads=downloads+1 WHERE aid='.(int)$aid, 1);
	$db->close();
	unset($db, $GLOBALS['mybb']);
}
