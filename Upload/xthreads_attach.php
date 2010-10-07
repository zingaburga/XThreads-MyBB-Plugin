<?php
/***
 * Lightweight PHP script for handling XThreads attachment downloads
 * Is lightweight as it only includes one file (MyBB settings file) and can work without querying the DB at all
 * (and even if it does, it loads a minimal DB engine - even less than the AJAX core)
 * An issue with this is that it relies on data passed in the URL to be fairly accurate, although inaccurate/forged information won't cause any serious issue
 *
 * This also has some nice features that MyBB's attachment system can't deliver, including caching, better handling of larger files (ranged requests, better memory management etc), header only requests,  etc
 */

/**
 * the following controls whether you wish to count downloads
 *  if 0: is disabled, and the DB won't be queried at all
 *  if 1: downloads = number of requests made (MyBB style attachment download counting)
 *  if 2 [default]: will count download only when entire file is sent; in the case of segmented download, will only count if last segment is requested (and completed)
 * mode 2 is perhaps the most accurate method of counting downloads under normal circumstances
 */
define('COUNT_DOWNLOADS', 2);


/**
 * the following is just the default cache expiry period for downloads, specified in seconds
 * as XThreads changes the URL if a file is modified, you can safely use a very long cache expiry time
 * the default value is 1 week (604800 seconds)
 */
define('CACHE_TIME', 604800);

// TODO: user perms + load session? + wol patch too probably


// do some basic initialisation
error_reporting(E_ALL ^ E_NOTICE); // this script works fine with E_ALL, however, we'll be compatible with MyBB
// remove unnecessary stuff
unset($HTTP_SERVER_VARS, $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_COOKIE_VARS, $HTTP_POST_FILES, $HTTP_ENV_VARS, $HTTP_SESSION_VARS);
unset($_GET, $_POST, $_FILES, $_ENV);
foreach(array('GLOBALS', '_COOKIE', '_REQUEST', '_SERVER') as $p)
	if(isset($_REQUEST[$p]) || isset($_FILES[$p]) || isset($_COOKIE[$p])) {
		header('HTTP/1.1 400 Bad Request');
		exit;
	}
// script will work if magic quotes is on, unless filenames happen to have quotes or something
@set_magic_quotes_runtime(0);
@ini_set('magic_quotes_runtime', 0); 
// will also work with register globals, so we won't bother with these


define('MYBB_ROOT', dirname(__FILE__).'/');
if(file_exists(MYBB_ROOT.'inc/settings.php')) {
	require MYBB_ROOT.'inc/settings.php';
	// TODO: perhaps have a dedicated setting for this one
	$basedir = $settings['uploadspath'].'/xthreads_ul/';
	unset($settings);
}
else // use default
	$basedir = './uploads/xthreads_ul/';

if(!@is_dir($basedir)) {
	header('HTTP/1.1 500 Internal Server Error');
	exit;
}

// parse input filename
if(!isset($_SERVER['PATH_INFO'])) {
	if(isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['PHP_SELF'])) {
		$snlen = strlen($_SERVER['SCRIPT_NAME']);
		if(substr($_SERVER['PHP_SELF'], 0, $snlen) == $_SERVER['SCRIPT_NAME'])
			$_SERVER['PATH_INFO'] = substr($_SERVER['PHP_SELF'], $snlen);
	}
}

if(!isset($_SERVER['PATH_INFO']) || !$_SERVER['PATH_INFO']) {
	header('HTTP/1.1 400 Bad Request');
	exit;
}

// maybe disallow \:*?"<>| in filenames, but then, they're valid *nix names...
if(!preg_match('~^/([0-9]+)_([0-9]+)_([0-9a-fA-F]{8})/([0-9a-fA-F]{32}/)?([^/]*)$~', $_SERVER['PATH_INFO'], $match)) {
	header('HTTP/1.1 400 Bad Request');
	exit;
}

if(isset($_REQUEST['thumb']) && preg_match('~^[0-9]+x[0-9]+$~', $_REQUEST['thumb']))
	$fext = $_REQUEST['thumb'].'.thumb';
else
	$fext = 'upload';

if(get_magic_quotes_gpc())
	$match[5] = stripslashes($match[5]);
$month_dir = 'ts_'.floor($match[2] / 1000000).'/';
$fn = 'file_'.$match[1].'_'.$match[3].'_'.preg_replace('~[^a-zA-Z0-9_\-%]~', '', str_replace(array(' ', '.', '+'), '_', $match[5])).'.'.$fext;
if(file_exists($basedir.$month_dir.$fn))
	$fn = $basedir.$month_dir.$fn;
elseif(file_exists($basedir.$fn))
	$fn = $basedir.$fn;
else {
	header('HTTP/1.1 404 Not Found');
	exit;
}

// check to see if unmodified/cached
$cached = false;
$modtime = filemtime($fn);
if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($cachetime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) && $cachetime > 0) {
	$cached = ($cachetime >= $modtime);
}
$etag = '"'.$match[1].'_'.$match[2].'_'.$match[3].'"';
if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && ($etag_match = trim($_SERVER['HTTP_IF_NONE_MATCH']))) {
	if($etag_match == $etag) $cached = true;
	elseif(strpos($etag_match, ',') && in_array($etag, array_map('trim', explode(',', $etag_match))))
		$cached = true;
	else
		$cached = false;
}

if($cached) {
	header('HTTP/1.1 304 Not Modified');
	exit;
}

$fp = fopen($fn, 'rb');
if(!$fp) {
	header('HTTP/1.1 500 Internal Server Error');
	exit;
}

header('Accept-Ranges: bytes');
header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modtime).'GMT');
header('Expires: '.gmdate('D, d M Y H:i:s', time() + CACHE_TIME).'GMT');
header('Cache-Control: max-age='.CACHE_TIME);
header('ETag: '.$etag);

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
		'jpeg' => 'image/jpeg',
		'gif' => 'image/gif',
		'png' => 'image/png',
		'bmp' => 'image/bmp',
		'zip' => 'application/zip',
		'doc' => 'application/msword',
		'docx' => 'application/msword',
		'xls' => 'application/msexcel',
		'xlsx' => 'application/msexcel',
		'ppt' => 'application/mspowerpoint',
		'pptx' => 'application/mspowerpoint',
		'pdf' => 'application/pdf',
		'gz' => 'application/x-gzip',
		'tar' => 'application/x-tar',
		'htm' => 'text/html',
		'html' => 'text/html',
		'css' => 'text/css',
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
		$content_type = @mime_content_type($match[5]);
	elseif(function_exists('finfo_open') && ($fi = @finfo_open(FILEINFO_MIME))) {
		$content_type = @finfo_file($fi, $match[5]);
		finfo_close($fi);
	} else // fallback
		$content_type = 'application/octet-stream';
}
header('Content-Type: '.$content_type);

if(!isset($_REQUEST['thumb'])) { // don't send disposition for thumbnails
	if(isset($_REQUEST['download']) && $_REQUEST['download'])
		$disposition = 'attachment';
	elseif((!isset($_SERVER['HTTP_USER_AGENT']) || strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') === false) && (
		strtolower(substr($content_type, 0, 6)) == 'image/' || strtolower(substr($content_type, 0, 5)) == 'text/'
	))
		$disposition = 'inline';
	else
		$disposition = 'attachment';
	header('Content-Disposition: '.$disposition.'; filename="'.urlencode($match[5]).'"');
}

$range_start = 0;
$fsize = filesize($fn);
$range_end = $fsize-1;

if($range_end < 0) {
	// this is a 0 byte file
	header('Content-Length: 0');
	fclose($fp);
	exit;
}

if(COUNT_DOWNLOADS == 1 && !isset($_REQUEST['thumb'])) increment_downloads($match[1]);

if(isset($_SERVER['HTTP_RANGE']) && ($p = strpos($_SERVER['HTTP_RANGE'], '='))) {
	$rangestr = substr($_SERVER['HTTP_RANGE'], $p+1);
	$p = strpos($rangestr, '-');
	$ostart = intval(substr($rangestr, 0, $p));
	$oend = intval(substr($rangestr, $p+1));
	
	if($oend && $oend < $range_end && $oend > $range_start)
		$range_end = $oend;
	if($ostart && $ostart > $range_start && $ostart < $range_end)
		$range_start = $ostart;
}

header('Content-Length: '.($range_end - $range_start + 1));
header('Content-Range: bytes '.$range_start.'-'.$range_end.'/'.$fsize);
if(!$range_start && $range_end == $fsize-1 && $match[4])
	header('Content-MD5: '.$match[4]);

if(isset($_SERVER['REQUEST_METHOD']))
	$reqmeth = strtoupper($_SERVER['REQUEST_METHOD']);
else
	$reqmeth = 'GET';

if($reqmeth != 'HEAD') {
	if($range_start)
		fseek($fp, $range_start);
	
	if($range_end == $fsize-1) {
		fpassthru($fp);
		if(COUNT_DOWNLOADS == 2 && !isset($_REQUEST['thumb'])) increment_downloads($match[1]);
	} else {
		$bytes = $range_end - $range_start + 1;
		while(!feof($fp) && $bytes > 0) {
			$bufsize = min($bytes, 16384);
			echo fread($fp, $bufsize);
			$bytes -= $bufsize;
		}
	}
}

fclose($fp);



function increment_downloads($aid) {
	// load config + MyBB's DB engine
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
	/*switch($config['database']['type'])
	{
		case 'sqlite3':
			$db = new DB_SQLite3;	break;
		case 'sqlite2':
			$db = new DB_SQLite2;	break;
		case 'pgsql':
			$db = new DB_PgSQL;		break;
		case 'mysqli':
			$db = new DB_MySQLi;	break;
		default:
			$db = new DB_MySQL;
	}*/
	if(!extension_loaded($db->engine)) {
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
	
	// so we do all the above just to run an update query :P
	$db->write_query('UPDATE '.$db->table_prefix.'xtattachments SET downloads=downloads+1 WHERE aid='.intval($aid), 1);
	$db->close();
	unset($db);
}
