<?php
/**
 * A lot of this code is copied from MyBB's inc/functions_upload.php
 */

// note, $uid is used purely for flood checking; not verification, identification or anything else
function &upload_xtattachment($attachment, &$tf, $uid, $update_attachment=0, $tid=0)
{
	$attacharray = do_upload_xtattachment($attachment, $tf, $update_attachment, $tid);
	if($attacharray['error'])
		return $attacharray;
	
	// perform user flood checking
	static $done_flood_check = false;
	if($uid && !$done_flood_check && $attacharray['aid']) {
        require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
		$done_flood_check = true;
		xthreads_rm_attach_query('tid=0 AND uid='.(int)$uid.' AND aid != '.$attacharray['aid'].' AND updatetime < '.(TIME_NOW-XTHREADS_UPLOAD_EXPIRE_TIME));
		// we'll do an extra query to get around the issue of delete queries not supporting offsets
		global $db;
		$cutoff = $db->fetch_field($db->simple_select('xtattachments', 'uploadtime', 'tid=0 AND uid='.(int)$uid.' AND aid != '.$attacharray['aid'].' AND uploadtime > '.(TIME_NOW-XTHREADS_UPLOAD_FLOOD_TIME), array('order_by' => 'uploadtime', 'order_dir' => 'desc', 'limit' => 1, 'limit_start' => XTHREADS_UPLOAD_FLOOD_NUMBER)), 'uploadtime');
		if($cutoff)
			xthreads_rm_attach_query('tid=0 AND uid='.(int)$uid.' AND aid != '.$attacharray['aid'].' AND uploadtime > '.(TIME_NOW-XTHREADS_UPLOAD_FLOOD_TIME).' AND uploadtime <= '.$cutoff);
	}
	
	return $attacharray;
}

function do_upload_xtattachment($attachment, &$tf, $update_attachment=0, $tid=0, $timestamp=TIME_NOW)
{
	global $db, $mybb, $lang;
	
	$posthash = $db->escape_string($mybb->input['posthash']);
	$tid = (int)$tid; // may be possible for this to be null, if so, change to 0
	$path = $mybb->settings['uploadspath'].'/xthreads_ul/';
	
	if(!$lang->xthreads_threadfield_attacherror) $lang->load('xthreads');
	
	if(is_array($attachment)) {
		if(isset($attachment['error']) && $attachment['error']) {
			if($attachment['error'] == 2) {
				return array('error' => $lang->sprintf($lang->xthreads_xtaerr_error_attachsize, get_friendly_size($tf['filemaxsize'])));
			}
			elseif($attachment['error'] >= 1 && $attachment['error'] <= 7) {
				$langvar = 'error_uploadfailed_php'.$attachment['error'];
				$langstr = $lang->$langvar;
			}
			else
				$langstr = $lang->sprintf($lang->error_uploadfailed_phpx, $attachment['error']);
			return array('error' => $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$langstr);
		}
		
		if(!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name'])) {
			return array('error' => $lang->error_uploadfailed.$lang->error_uploadfailed_php4);
		}
		
		
		$file_size = $attachment['size']; // @filesize($attachment['tmp_name'])
		
		$attachment['name'] = strtr($attachment['name'], array('/' => '', "\x0" => ''));
		
		if($error = xthreads_validate_attachment($attachment, $tf)) {
			@unlink($attachment['tmp_name']);
			return array('error' => $error);
		}
		
		$movefunc = 'move_uploaded_file';
	} elseif($mybb->usergroup['cancp'] == 1 && substr($attachment, 0, 7) == 'file://') {
		// admin file move
		$filename = strtr(substr($attachment, 7), array('/' => '', DIRECTORY_SEPARATOR => '', "\0" => ''));
		$file = $path.'admindrop/'.$filename;
		if(xthreads_empty($filename) || !file_exists($file)) {
			return array('error' => $lang->sprintf($lang->xthreads_xtaerr_admindrop_not_found, htmlspecialchars_uni($filename), htmlspecialchars_uni($file)));
		}
		if(!is_writable($file)) {
			return array('error' => $lang->sprintf($lang->xthreads_xtaerr_admindrop_file_unwritable, htmlspecialchars_uni($filename)));
		}
		if(strtolower($file) == 'index.html') {
			return array('error' => $lang->xthreads_xtaerr_admindrop_index_error);
		}
		
		$attachment = array(
			'name' => $filename,
			'tmp_name' => $file,
			'size' => @filesize($file),
		);
		unset($file, $filename);
		if($error = xthreads_validate_attachment($attachment, $tf)) {
			return array('error' => $error);
		}
		
		$file_size = $attachment['size'];
		$movefunc = 'rename';
	} else {
		// fetch URL
		if(!empty($tf['filemagic']))
			$magic =& $tf['filemagic'];
		else
			$magic = array();
		$attachment = xthreads_fetch_url($attachment, $tf['filemaxsize'], $tf['fileexts'], $magic);
		db_ping($db);
		if($attachment['error']) {
			return array('error' => $attachment['error']);
		}
		$file_size = $attachment['size'];
		
		if(xthreads_empty($attachment['name']) || $file_size < 1)
			return array('error' => $lang->error_uploadfailed);
		
		$attachment['name'] = strtr($attachment['name'], array('/' => '', "\x0" => ''));
		
		$movefunc = 'rename';
	}
	
	
	if($tf['fileimage']) {
		$img_dimensions = @getimagesize($attachment['tmp_name']);
		if(empty($img_dimensions) || !in_array($img_dimensions[2], array(IMAGETYPE_GIF,IMAGETYPE_JPEG,IMAGETYPE_PNG))) {
			@unlink($attachment['tmp_name']);
			return array('error' => $lang->error_attachtype);
		}
		if(preg_match('~^([0-9]+)x([0-9]+)(\\|([0-9]+)x([0-9]+))?$~', $tf['fileimage'], $match)) {
			// check if image exceeds max/min dimensions
			if(($img_dimensions[0] < $match[1] || $img_dimensions[1] < $match[2]) || (
				$match[3] && (
					$img_dimensions[0] > $match[4] || $img_dimensions[1] > $match[5]
				)
			)) {
				@unlink($attachment['tmp_name']);
				return array('error' => $lang->sprintf($lang->xthreads_xtaerr_error_imgdims, $img_dimensions[0], $img_dimensions[1]));
			}
		}
		/*
		// convert WBMP -> PNG (saves space, bandwidth and works with MyBB's thumbnail generator)
		// unfortunately, although this is nice, we have a problem of filetype checking etc...
		if($img_dimensions[2] == IMAGETYPE_WBMP) {
			if(function_exists('imagecreatefromwbmp') && $img = @imagecreatefromwbmp($attachment['tmp_name'])) {
				@unlink($attachment['tmp_name']);
				@imagepng($img, $attachment['tmp_name'], 6); // use zlib's recommended compression level
				imgdestroy($img);
				unset($img);
				// double check that we have a file
				if(!file_exists($attachment['tmp_name']))
					return array('error' => $lang->error_attachtype); // get user to upload a non-WBMP file, lol
				// change extension + update filesize, do MIME as well
				if(strtolower(substr($attachment['name'], -5)) == '.wbmp')
					$attachment['name'] = substr($attachment['name'], 0, -5).'.png';
				$file_size = @filesize($attachment['tmp_name']);
				if(strtolower($attachment['type']) == 'image/wbmp')
					$attachment['type'] = 'image/png';
				// update type too
				$img_dimensions[2] = IMAGETYPE_PNG;
			}
			else {
				// can't do much, error out
				@unlink($attachment['tmp_name']);
				return array('error' => $lang->error_attachtype);
			}
		}
		*/
		// we won't actually bother checking MIME types - not a big issue anyway
	}
	
	if(!XTHREADS_UPLOAD_LARGEFILE_SIZE || $file_size < XTHREADS_UPLOAD_LARGEFILE_SIZE) {
		@set_time_limit(30); // as md5_file may take a while
		$md5_start = time();
		$file_md5 = @md5_file($attachment['tmp_name'], true);
		if(strlen($file_md5) == 32) {
			// perhaps not PHP5
			$file_md5 = pack('H*', $file_md5);
		}
		if(time() - $md5_start > 2) // ping DB if process took longer than 2 secs
			db_ping($db);
		unset($md5_start);
	}
	
	if($update_attachment) {
		$prevattach = $db->fetch_array($db->simple_select('xtattachments', 'aid,attachname,indir,md5hash', 'aid='.(int)$update_attachment));
		if(!$prevattach['aid']) $update_attachment = false;
	} /* else {
		// Check if attachment already uploaded
		// TODO: this is actually a little problematic - perhaps verify that this is attached to this field (or maybe rely on checks in xt_updatehooks file)
		if(isset($file_md5))
			$md5check = ' OR md5hash="'.$db->escape_string($file_md5).'"';
		else
			$md5check = '';
		$prevattach = $db->fetch_array($db->simple_select('xtattachments', 'aid', 'filename="'.$db->escape_string($attachment['name']).'" AND (md5hash IS NULL'.$md5check.') AND filesize='.$file_size.' AND (posthash="'.$posthash.'" OR (tid='.$tid.' AND tid!=0))'));
		if($prevattach['aid']) {
			@unlink($attachment['tmp_name']);
			// TODO: maybe return aid instead?
			return array('error' => $lang->error_alreadyuploaded);
		}
	} */
	
	
	// We won't use MyBB's nice monthly directories, instead, we'll use a more confusing system based on the timestamps
	// note, one month = 2592000 seconds, so if we split up by 1mil, it'll be approx 11.5 days
	// If safe_mode is enabled, don't attempt to use the monthly directories as it won't work
	if(ini_get('safe_mode') == 1 || strtolower(ini_get('safe_mode')) == 'on') {
		$month_dir = '';
	} else {
		$month_dir = 'ts_'.floor(TIME_NOW / 1000000).'/';
		if(!@is_dir($path.$month_dir)) {
			@mkdir($path.$month_dir);
			// Still doesn't exist - oh well, throw it in the main directory
			if(@is_dir($path.$month_dir)) {
				// write index file
				if($index = fopen($path.$month_dir.'index.html', 'w')) {
					fwrite($index, '<html><body></body></html>');
					fclose($index);
					@my_chmod($path.$month_dir.'index.html', 0644);
				}
				@my_chmod($path.$month_dir, 0755);
			}
			else
				$month_dir = '';
		}
	}
	
	// All seems to be good, lets move the attachment!
	$basename = substr(md5(uniqid(mt_rand(), true).substr($mybb->post_code, 16)), 12, 8).'_'.preg_replace('~[^a-zA-Z0-9_\-%]~', '', str_replace(array(' ', '.', '+'), '_', $attachment['name'])).'.upload';
	$filename = 'file_'.($prevattach['aid'] ? $prevattach['aid'] : 't'.TIME_NOW).'_'.$basename;
	
	@ignore_user_abort(true); // don't let the user break this integrity between file system and DB
	if(isset($GLOBALS['xtfurl_tmpfiles'])) { // if using url fetch, remove this from list of temp files
		unset($GLOBALS['xtfurl_tmpfiles'][$attachment['tmp_name']]);
	}
	while(!(@$movefunc($attachment['tmp_name'], $path.$month_dir.$filename))) {
		if($month_dir) { // try doing it again without the month_dir
			$month_dir = '';
		} else {
			// failed
			@ignore_user_abort(false);
			return array('error' => $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$lang->error_uploadfailed_movefailed);
		}
	}
	
	// Lets just double check that it exists
	if(!file_exists($path.$month_dir.$filename)) {
		@ignore_user_abort(false);
		return array('error' => $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$lang->error_uploadfailed_lost);
	}
	
	// Generate the array for the insert_query
	$attacharray = array(
		'posthash' => $posthash,
		'tid' => $tid,
		'uid' => (int)$mybb->user['uid'],
		'field' => $tf['field'],
		'filename' => strval($attachment['name']),
		'uploadmime' => strval($attachment['type']),
		'filesize' => $file_size,
		'attachname' => $basename,
		'indir' => $month_dir,
		'downloads' => 0,
		'uploadtime' => $timestamp,
		'updatetime' => $timestamp,
	);
	if(isset($file_md5))
		$attacharray['md5hash'] = $file_md5;
	else
		$attacharray['md5hash'] = null;
	if(!empty($img_dimensions)) {
		$origdimarray = array('w' => $img_dimensions[0], 'h' => $img_dimensions[1], 'type' => $img_dimensions[2]);
		$attacharray['thumbs'] = serialize(array('orig' => $origdimarray));
	}
	
	if($update_attachment) {
		unset($attacharray['downloads'], $attacharray['uploadtime']);
		//$attacharray['updatetime'] = TIME_NOW;
		xthreads_db_update('xtattachments', $attacharray, 'aid='.$prevattach['aid']);
		$attacharray['aid'] = $prevattach['aid'];
		
		// and finally, delete old attachment
		xthreads_rm_attach_fs($prevattach);
		$new_file = $path.$month_dir.$filename;
	}
	else {
		$attacharray['aid'] = xthreads_db_insert('xtattachments', $attacharray);
		// now that we have the aid, move the file
		$new_file = $path.$month_dir.'file_'.$attacharray['aid'].'_'.$basename;
		@rename($path.$month_dir.$filename, $new_file);
		if(!file_exists($new_file)) {
			// oh dear, all our work for nothing...
			@unlink($path.$month_dir.$filename);
			$db->delete_query('xtattachments', 'aid='.$attacharray['aid']);
			@ignore_user_abort(false);
			return array('error' => $lang->error_uploadfailed.$lang->error_uploadfailed_detail.$lang->error_uploadfailed_lost);
		}
	}
	@my_chmod($new_file, '0644');
	@ignore_user_abort(false);
	
	if(!empty($img_dimensions) && !empty($tf['fileimgthumbs'])) {
		// generate thumbnails
		$attacharray['thumbs'] = xthreads_build_thumbnail($tf['fileimgthumbs'], $attacharray['aid'], $tf['field'], $new_file, $path, $month_dir, $img_dimensions);
		$attacharray['thumbs']['orig'] = $origdimarray;
		$attacharray['thumbs'] = serialize($attacharray['thumbs']);
	}
	
	return $attacharray;
}

function xthreads_validate_attachment(&$attachment, &$tf) {
	global $lang;
	if(empty($attachment['name']) || $attachment['size'] < 1) {
		return $lang->error_uploadfailed;
	}
	if($tf['filemaxsize'] && $attachment['size'] > $tf['filemaxsize']) {
		return $lang->sprintf($lang->xthreads_xtaerr_error_attachsize, get_friendly_size($tf['filemaxsize']));
	}
	if(!xthreads_fetch_url_validext($attachment['name'], $tf['fileexts']))
		return $lang->error_attachtype;
	if(!empty($tf['filemagic'])) {
		$validmagic = false;
		if($fp = @fopen($attachment['tmp_name'], 'rb')) {
			$startbuf = fread($fp, 255); // since it's impossible to exceed this amount in the field (yes, it's dirty, lol)
			fclose($fp);
			foreach($tf['filemagic'] as &$magic) {
				if(xthreads_empty($magic)) continue;
				if(substr($startbuf, 0, strlen($magic)) == $magic) {
					$validmagic = true;
					break;
				}
			}
		} else
			return $lang->error_uploadfailed;
		
		if(!$validmagic) {
			return $lang->error_attachtype;
		}
	}
	return false; // no error
}

function &xthreads_build_thumbnail($thumbdims, $aid, $fieldname, $filename, $path, $month_dir, $img_dimensions=null) {
	if(empty($img_dimensions)) {
		//$img_dimensions = @getimagesize($path.$month_dir.$filename);
		$img_dimensions = @getimagesize($filename);
	}
	$update_thumbs = array('orig' => array('w' => $img_dimensions[0], 'h' => $img_dimensions[1], 'type' => $img_dimensions[2]));
	if(is_array($img_dimensions)) {
		$filterfunc = 'xthreads_imgthumb_'.$fieldname;
		foreach($thumbdims as $dims => $complex) {
			$destname = basename(substr($filename, 0, -6).$dims.'.thumb');
			if($complex) {
				require_once MYBB_ROOT.'inc/xthreads/xt_image.php';
				$img = new XTImageTransform;
				if($img->_load($filename)) {
					// run filter chain
					$filterfunc($dims, $img);
					// write out file & save
					$img->_enableWrite = true;
					$img->write($path.$month_dir.'/'.$destname);
					$update_thumbs[$dims] = array('w' => $img->WIDTH, 'h' => $img->HEIGHT, 'type' => $img->typeGD, 'file' => $month_dir.$destname);
				} else {
					// failed
					$update_thumbs[$dims] = array('w' => 0, 'h' => 0, 'type' => 0, 'file' => '');
				}
			} else {
				$p = strpos($dims, 'x');
				if(!$p) continue;
				$w = (int)substr($dims, 0, $p);
				$h = (int)substr($dims, $p+1);
				
				if($img_dimensions[0] > $w || $img_dimensions[1] > $h) {
					// TODO: think about using own function to apply image convolution
					require_once MYBB_ROOT.'inc/functions_image.php';
					$thumbnail = generate_thumbnail($filename, $path.$month_dir, $destname, $h, $w);
					// if it fails, there's nothing much we can do... so twiddle thumbs is the solution
					if($thumbnail['code'] == 1) {
						$newdims = scale_image($img_dimensions[0], $img_dimensions[1], $w, $h);
						$update_thumbs[$dims] = array('w' => $newdims['width'], 'h' => $newdims['height'], 'type' => $img_dimensions[2], 'file' => $month_dir.$destname);
					}
					else {
						$update_thumbs[$dims] = array('w' => 0, 'h' => 0, 'type' => 0, 'file' => '');
					}
				}
				else { // image is small (hopefully), just copy it over
					// TODO: maybe use hardlink instead?
					@copy($filename, $path.$month_dir.$destname);
					$update_thumbs[$dims] = array('w' => $img_dimensions[0], 'h' => $img_dimensions[1], 'type' => $img_dimensions[2], 'file' => $month_dir.$destname);
				}
			}
		}
	}
	
	global $db;
	$db->update_query('xtattachments', array(
		'thumbs' => $db->escape_string(serialize($update_thumbs))
	), 'aid='.$aid);
	return $update_thumbs;
}


// copied from MyBB's fetch_remote_file function, but modified for our needs
// this will attempt to "smartly" terminate the transfer early if it's going to end up rejected anyway
function xthreads_fetch_url($url, $max_size=0, $valid_ext='', $valid_magic=array()) {
	global $lang;
	if(!$lang->xthreads_xtfurlerr_invalidurl) $lang->load('xthreads');
	$url = str_replace("\x0", '', $url);
	$purl = @parse_url($url);
	if(xthreads_empty($purl['host'])) return array('error' => $lang->xthreads_xtfurlerr_invalidurl);
	
	// attempt to decode special IP tricks, eg 0x7F.0.0.0 or even 127.000.0.0
	if(substr_count($purl['host'], '.') == 3 && preg_match('~^[0-9a-fA-FxX.]+$~', $purl['host'])) {
		$parts = explode('.', $purl['host']);
		$modify = true;
		foreach($parts as &$part) {
			if($part === '') return array('error' => $lang->xthreads_xtfurlerr_invalidurl);
			if($part{0} === '0' && isset($part{1})) {
				if($part{1} == 'x' || $part{1} == 'X') {
					// check hex digit
					$hexpart = substr($part, 2);
					if($hexpart === '' || !ctype_xdigit($hexpart)) {
						$modify = false;
						break;
					} else {
						$part = hexdec($hexpart);
					}
				} elseif(!is_numeric($part)) {
					$modify = false;
					break;
				} elseif(preg_match('~^[0-7]+$~', $part)) {
					$part = octdec($part);
				} else {
					$part = (int)$part;
				}
			}
			elseif(!is_numeric($part)) {
				$modify = false;
				break;
			} else
				$part = (int)$part; // converts stuff like 000 into 0, although above should do that
		}
		if($modify) $purl['host'] = implode('.', $parts);
	}
	
	if(XTHREADS_URL_FETCH_DISALLOW_HOSTS && in_array($purl['host'], array_map('trim', explode(',', XTHREADS_URL_FETCH_DISALLOW_HOSTS))))
		return array('error' => $lang->xthreads_xtfurlerr_badhost);
	
	$portmap = array(
		'http' => 80,
		'https' => 443,
		'ftp' => 21,
		'ftps' => 990,
	);
	$scheme = strtolower($purl['scheme']);
	
	if(!isset($portmap[$scheme])) return array('error' => $lang->xthreads_xtfurlerr_invalidscheme);
	if(!$purl['port'])
		$purl['port'] = $portmap[$scheme];
	elseif(XTHREADS_URL_FETCH_DISALLOW_PORT && $purl['port'] != $portmap[$scheme])
		return array('error' => $lang->xthreads_xtfurlerr_badport);
	
	$ret = array(
		'tmp_name' => tempnam(xthreads_get_temp_dir(), mt_rand()),
		'name' => basename($purl['path']),
		'name_disposition' => false,
		'size' => 0,
	);
	@unlink($ret['tmp_name']);
	if(substr($purl['path'], -1) == '/' || xthreads_empty($ret['name'])) $ret['name'] = 'index.html';
	
	require_once MYBB_ROOT.'inc/xthreads/xt_urlfetcher.php';
	$fetcher = getXTUrlFetcher($purl['scheme']);
	if(!isset($fetcher)) {
		return array('error' => $lang->xthreads_xtfurlerr_nofetcher);
	}
	
	$fp = @fopen($ret['tmp_name'], 'wb');
	if(!$fp) return array('error' => $lang->xthreads_xtfurlerr_cantwrite);
	
	xthreads_fetch_url_register_tmp($ret['tmp_name']);
	@set_time_limit(0);
	
	
	$fetcher->url = $url;
	$fetcher->setRefererFromUrl();
	
	$fetcher->charset = $lang->settings['charset'];
	$fetcher->lang = $lang->settings['htmllang'];
	
	$GLOBALS['xtfurl_ret'] =& $ret;
	$GLOBALS['xtfurl_max_size'] = $max_size;
	$fetcher->meta_function = 'xthreads_fetch_url_meta';
	$GLOBALS['xtfurl_datalen'] = 0;
	$GLOBALS['xtfurl_magicchecked'] = false;
	$GLOBALS['xtfurl_validmagic'] =& $valid_magic;
	$GLOBALS['xtfurl_databuf'] = '';
	$GLOBALS['xtfurl_exts'] =& $valid_ext;
	$GLOBALS['xtfurl_fp'] =& $fp;
	$fetcher->body_function = 'xthreads_fetch_url_write';
	
	$result = $fetcher->fetch();
	// TODO: fix the following
	if($result === false) {
		$error = $fetcher->getError($errcode);
		$langvar = 'xthreads_xtfurlerr_'.$error;
		if(isset($lang->$langvar))
			$ret['error'] = $lang->$langvar;
		else
			$ret['error'] = $lang->sprintf($lang->xthreads_xtfurlerr_errcode, $fetcher->name, $errcode, htmlspecialchars_uni($error));
	}
	
	$fetcher->close();
	
	if(!$ret['error']) {
		// check magic if not done
		if($result && !$GLOBALS['xtfurl_magicchecked'] && !empty($valid_magic)) {
			if(!xthreads_fetch_url_validmagic($GLOBALS['xtfurl_databuf'], $valid_magic)) {
				$GLOBALS['xtfurl_magicchecked'] = 'invalid';
				$result = null;
			}
		}
		if($result === null) {
			// aborted - most likely from early termination
			if($ret['size'] && $max_size && $ret['size'] > $max_size) {
				$ret['error'] = $lang->sprintf($lang->xthreads_xtaerr_error_attachsize, get_friendly_size($max_size));
			}
			elseif($GLOBALS['xtfurl_magicchecked'] == 'invalid') { // this also covers extension check
				$ret['error'] = $lang->error_attachtype;
			}
		}
	}
	
	fclose($fp);
	if($ret['error'])
		@unlink($ret['tmp_name']);
	else {
		$ret['size'] = @filesize($ret['tmp_name']);
		if($ret['size'] < 1 || empty($ret['name'])) // weird...
			@unlink($ret['tmp_name']);
	}
	
	@set_time_limit(30);
	return $ret;
}
function xthreads_fetch_url_validext(&$name, &$exts) {
	if(!xthreads_empty($exts)) {
		$fn = strtolower($name);
		foreach(explode('|', strtolower($exts)) as $ext) {
			if($ext !== '' && substr($fn, -strlen($ext) -1) == '.'.$ext)
				return true;
		}
		return false;
	}
	return true;
}
function xthreads_fetch_url_validmagic(&$data, &$magic) {
	if(empty($magic)) return true;
	foreach($magic as &$m) {
		if($m && substr($data, 0, strlen($m)) == $m) {
			return true;
		}
	}
	return false;
}

function xthreads_fetch_url_meta(&$fetcher, &$name, &$val) {
	global $xtfurl_ret;
	switch($name) {
		case 'retcode':
			if($val[0] != 200) {
				global $lang;
				$GLOBALS['xtfurl_ret']['error'] = $lang->sprintf($lang->xthreads_xtfurlerr_badresponse, $val[0], $val[1]);
				return false;
			}
			return true;
		
		case 'name':
			$xtfurl_ret['name_disposition'] = true;
			// fall through
		case 'size':
		case 'type':
			if(!xthreads_empty($val))
				$xtfurl_ret[$name] = $val;
			if($name == 'size' && $GLOBALS['xtfurl_max_size'] && $val > $GLOBALS['xtfurl_max_size'])
				return false;
	}
	return true;
}
function xthreads_fetch_url_write(&$fetcher, &$data) {
	$len = strlen($data);
	global $xtfurl_datalen, $xtfurl_magicchecked, $xtfurl_ret;
	
	// check extension
	if(!$xtfurl_datalen) {
		// firstly, do we have an extension?  if not, maybe try guess one from the content-type
		if(!xthreads_empty($xtfurl_ret['type']) && !$xtfurl_ret['name_disposition'] && strpos($xtfurl_ret['name'], '.') === false) {
			// we'll only try a few common ones
			switch(strtolower($xtfurl_ret['type'])) {
				case 'text/html': case 'text/xhtml+xml':
					$xtfurl_ret['name'] .= '.html'; break;
				case 'image/jpeg': case 'image/jpg':
					$xtfurl_ret['name'] .= '.jpg'; break;
				case 'image/gif':
					$xtfurl_ret['name'] .= '.gif'; break;
				case 'image/png':
					$xtfurl_ret['name'] .= '.png'; break;
				case 'image/bmp':
					$xtfurl_ret['name'] .= '.bmp'; break;
				case 'image/svg+xml':
					$xtfurl_ret['name'] .= '.svg'; break;
				case 'image/tiff':
					$xtfurl_ret['name'] .= '.tiff'; break;
				case 'image/x-icon':
					$xtfurl_ret['name'] .= '.ico'; break;
				case 'text/xml':
					$xtfurl_ret['name'] .= '.xml'; break;
				case 'text/plain':
					$xtfurl_ret['name'] .= '.txt'; break;
				case 'text/css':
					$xtfurl_ret['name'] .= '.css'; break;
				case 'text/javascript': case 'application/javascript': case 'application/x-javascript':
					$xtfurl_ret['name'] .= '.js'; break;
			}
		}
		if(!xthreads_fetch_url_validext($xtfurl_ret['name'], $GLOBALS['xtfurl_exts'])) {
			$xtfurl_magicchecked = 'invalid'; // dirty, but works...
			return false;
		}
	}
	
	$xtfurl_datalen += $len;
	if($GLOBALS['xtfurl_max_size'] && $xtfurl_datalen > $GLOBALS['xtfurl_max_size']) {
		$xtfurl_ret['size'] = $xtfurl_datalen;
		return false;
	}
	if(!$xtfurl_magicchecked && !empty($GLOBALS['xtfurl_validmagic'])) {
		global $xtfurl_databuf;
		if($xtfurl_datalen >= 255) {
			// check magic
			$xtfurl_databuf .= substr($data, 0, 255-$xtfurl_datalen+$len);
			if(!xthreads_fetch_url_validmagic($xtfurl_databuf, $GLOBALS['xtfurl_validmagic'])) {
				$xtfurl_magicchecked = 'invalid';
				return false;
			}
			$xtfurl_magicchecked = true;
		} else {
			$xtfurl_databuf .= $data;
		}
	}
	fwrite($GLOBALS['xtfurl_fp'], $data);
	return true;
}


// these functions ensure that temp files are cleaned up if the user aborts the connection
function xthreads_fetch_url_register_tmp($name) {
	global $xtfurl_tmpfiles;
	if(!is_array($xtfurl_tmpfiles)) {
		$xtfurl_tmpfiles = array();
		register_shutdown_function('xthreads_fetch_url_tmp_shutdown');
	}
	$xtfurl_tmpfiles[$name] = 1;
}
function xthreads_fetch_url_tmp_shutdown() {
	if(!connection_aborted()) return;
	global $xtfurl_tmpfiles;
	foreach($xtfurl_tmpfiles as $name => $foo) {
		@unlink($name); // should always succeed (hopefully)...
	}
}

if(!function_exists('ctype_xdigit')) {
	function ctype_xdigit($s) {
		return (bool)preg_match('~^[0-9a-fA-F]+$~', $s);
	}
}
function xthreads_get_temp_dir() {
	if(ini_get('safe_mode') == 1 || strtolower(ini_get('safe_mode')) == 'on')
		// safemode - fallback to cache dir
		return realpath(MYBB_ROOT.'cache/');
	elseif(function_exists('sys_get_temp_dir') && ($tmpdir = sys_get_temp_dir()) && @is_dir($tmpdir) && is_writable($tmpdir))
		return realpath($tmpdir);
	elseif(!function_exists('sys_get_temp_dir')) {
		// PHP < 5.2.1, try to find a temp dir
		$dirs = array();
		foreach(array('TMP', 'TMPDIR', 'TEMP') as $e) {
			if($env = getenv($e))
				$dirs[] = $env;
		}
		if(DIRECTORY_SEPARATOR == '\\') { // Windows
			// all this probably unnecessary, but oh well, enjoy it whilst we can
			if($env = getenv('LOCALAPPDATA'))
				$dirs[] = $env.'\\Temp\\';
			if($env = getenv('USERPROFILE'))
				$dirs[] = $env.'\\Local Settings\\Temp\\';
			if($env = getenv('SYSTEMROOT'))
				$dirs[] = $env.'\\Temp\\';
			if($env = getenv('WINDIR'))
				$dirs[] = $env.'\\Temp\\';
			if($env = getenv('SYSTEMDRIVE'))
				$dirs[] = $env.'\\Temp\\';
			
			$dirs[] = 'C:\\Windows\\Temp\\';
			$dirs[] = 'C:\\Temp\\';
		} else {
			$dirs[] = '/tmp/';
		}
		foreach($dirs as &$dir) {
			if(@is_dir($dir) && is_writable($dir))
				return realpath($dir);
		}
	}
	// fallback on cache dir (guaranteed to be writable)
	return realpath(MYBB_ROOT.'cache/');
}

function db_ping(&$dbobj) {
	if($dbobj->type == 'mysqli')
		$func = 'mysqli_ping';
	else
		$func = xthreads_db_type($dbobj->type).'_ping';
	if(!function_exists($func)) return true; // fallback
	if(is_object(@$dbobj->db)) return true; // sqlite
	
	$ret = @$func($dbobj->read_link);
	if($dbobj->write_link !== $dbobj->read_link)
		$ret = @$func($dbobj->write_link) && $ret;
	return $ret;
}
