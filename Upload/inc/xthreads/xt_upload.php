<?php
/**
 * A lot of this code is copied from MyBB's inc/functions_upload.php
 */

// note, $uid is used purely for flood checking; not verification, identification or anything else
function &upload_xtattachment(&$attachment, &$tf, $uid, $update_attachment=0, $tid=0)
{
	$attacharray = do_upload_xtattachment($attachment, $tf, $update_attachment, $tid);
	if($attacharray['error'])
		return $attacharray;
	
	// perform user flood checking
	static $done_flood_check = false;
	if($uid && !$done_flood_check && $attacharray['aid']) {
		$done_flood_check = true;
		xthreads_rm_attach_query('tid=0 AND uid='.intval($uid).' AND aid != '.$attacharray['aid'].' AND updatetime < '.(TIME_NOW-XTHREADS_UPLOAD_EXPIRE_TIME));
		//xthreads_rm_attach_query('tid=0 AND uid='.intval($uid).' AND uploadtime > '.(TIME_NOW-XTHREADS_UPLOAD_FLOOD_TIME).' ORDER BY uploadtime DESC LIMIT 18446744073709551615 OFFSET '.XTHREADS_UPLOAD_FLOOD_NUMBER);
		// 18446744073709551615 is recommended from http://dev.mysql.com/doc/refman/4.1/en/select.html
		// we'll do an extra query to get around the issue of delete queries not supporting offsets
		global $db;
		$cutoff = $db->fetch_field($db->simple_select('xtattachments', 'uploadtime', 'tid=0 AND uid='.intval($uid).' AND aid != '.$attacharray['aid'].' AND uploadtime > '.(TIME_NOW-XTHREADS_UPLOAD_FLOOD_TIME), array('order_by' => 'uploadtime', 'order_dir' => 'desc', 'limit' => 1, 'limit_start' => XTHREADS_UPLOAD_FLOOD_NUMBER)), 'uploadtime');
		if($cutoff)
			xthreads_rm_attach_query('tid=0 AND uid='.intval($uid).' AND aid != '.$attacharray['aid'].' AND uploadtime > '.(TIME_NOW-XTHREADS_UPLOAD_FLOOD_TIME).' AND uploadtime <= '.$cutoff);
	}
	
	return $attacharray;
}

function do_upload_xtattachment(&$attachment, &$tf, $update_attachment=0, $tid=0, $timestamp=TIME_NOW)
{
	global $db, $mybb, $lang;
	
	$posthash = $db->escape_string($mybb->input['posthash']);

	if(is_array($attachment)) {
		if(isset($attachment['error']) && $attachment['error']) {
			if($attachment['error'] >= 1 && $attachment['error'] <= 7) {
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
		
		if(empty($attachment['name']) || $file_size < 1)
			return array('error' => $lang->error_uploadfailed);
		
		$attachment['name'] = strtr($attachment['name'], array('/' => '', "\x0" => ''));
		
		// validation
		if($tf['filemaxsize'] && $file_size > $tf['filemaxsize']) {
			@unlink($attachment['tmp_name']);
			return array('error' => $lang->sprintf($lang->error_attachsize, $tf['filemaxsize']/1024));
		}
		if($tf['fileexts']) {
			$ext = strtolower(get_extension($attachment['name']));
			if(strpos('|'.strtolower($tf['fileexts']).'|', '|'.$ext.'|') === false) {
				@unlink($attachment['tmp_name']);
				return array('error' => $lang->error_attachtype);
			}
		}
		if(!empty($tf['filemagic'])) {
			$validmagic = false;
			if($fp = @fopen($attachment['tmp_name'], 'rb')) {
				$startbuf = fread($fp, 255); // since it's impossible to exceed this amount in the field (yes, it's dirty, lol)
				fclose($fp);
				foreach($tf['filemagic'] as &$magic) {
					if(!$magic) continue;
					if(substr($startbuf, 0, strlen($magic)) == $magic) {
						$validmagic = true;
						break;
					}
				}
			} else
				return array('error' => $lang->error_uploadfailed);
			
			if(!$validmagic) {
				@unlink($attachment['tmp_name']);
				return array('error' => $lang->error_attachtype);
			}
		}
		
		
		
		$movefunc = 'move_uploaded_file';
	} else {
		// fetch URL
		if(!empty($tf['filemagic']))
			$magic =& $tf['filemagic'];
		else
			$magic = array();
		$attachment = xthreads_fetch_url($attachment, $tf['filemaxsize'], $tf['fileexts'], $magic);
		if($attachment['error']) {
			return array('error' => $attachment['error']);
		}
		$file_size = $attachment['size'];
		
		if(empty($attachment['name']) || $file_size < 1)
			return array('error' => $lang->error_uploadfailed);
		
		$attachment['name'] = strtr($attachment['name'], array('/' => '', "\x0" => ''));
		
		$movefunc = 'rename';
	}
	
	
	if($tf['fileimage']) {
		$img_dimensions = @getimagesize($attachment['tmp_name']);
		if(empty($img_dimensions)) {
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
				// TODO: better error message
				return array('error' => $lang->error_attachtype);
			}
		}
		// we won't actually bother checking MIME types - not a big issue anyway
	}
	
	@set_time_limit(60); // as md5_file may take a while
	$file_md5 = @md5_file($attachment['tmp_name'], true);
	if(strlen($file_md5) == 32) {
		// perhaps not PHP5
		$file_md5 = pack('H*', $file_md5);
	}
	
	if($update_attachment) {
		$prevattach = $db->fetch_array($db->simple_select('xtattachments', 'aid,attachname,indir', 'aid='.intval($update_attachment)));
		if(!$prevattach['aid']) $update_attachment = false;
	} else {
		// Check if attachment already uploaded
		// TODO: this is actually a little problematic - perhaps verify that this is attached to this field (or maybe rely on checks in xt_updatehooks file)
		$prevattach = $db->fetch_array($db->simple_select('xtattachments', 'aid', 'filename="'.$db->escape_string($attachment['name']).'" AND md5hash="'.$db->escape_string($file_md5).'" AND filesize='.$file_size.' AND (posthash="'.$posthash.'" OR (tid='.intval($tid).' AND tid!=0))'));
		if($prevattach['aid']) {
			@unlink($attachment['tmp_name']);
			// TODO: maybe return aid instead?
			return array('error' => $lang->error_alreadyuploaded);
		}
	}
	
	
	$path = $mybb->settings['uploadspath'].'/xthreads_ul/';
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
				// write index directory
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
	$basename = substr(md5(uniqid(rand())), 12, 8).'_'.preg_replace('~[^a-zA-Z0-9_\-%]~', '', str_replace(array(' ', '.', '+'), '_', $attachment['name'])).'.upload';
	$filename = 'file_'.($prevattach['aid'] ? $prevattach['aid'] : TIME_NOW).'_'.$basename;
	
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
		'uid' => $uid,
		'field' => $tf['field'],
		'filename' => $attachment['name'],
		'uploadmime' => $attachment['type'],
		'filesize' => $file_size,
		'attachname' => $basename,
		'indir' => $month_dir,
		'md5hash' => $file_md5,
		'downloads' => 0,
		'uploadtime' => $timestamp,
		'updatetime' => $timestamp,
	);
	if(!empty($img_dimensions)) {
		$origdimarray = array('w' => $img_dimensions[0], 'h' => $img_dimensions[1], 'type' => $img_dimensions[2]);
		$attacharray['thumbs'] = serialize(array('orig' => $origdimarray));
	}
	
	if($update_attachment) {
		unset($attacharray['downloads'], $attacharray['uploadtime']);
		//$attacharray['updatetime'] = TIME_NOW;
		$db->update_query('xtattachments', array_map(array($db, 'escape_string'), $attacharray), 'aid='.$prevattach['aid']);
		$attacharray['aid'] = $prevattach['aid'];
		
		// and finally, delete old attachment
		xthreads_rm_attach_fs($prevattach);
		$new_file = $path.$month_dir.$filename;
	}
	else {
		$attacharray['aid'] = $db->insert_query('xtattachments', array_map(array($db, 'escape_string'), $attacharray));
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
		$attacharray['thumbs'] = xthreads_build_thumbnail($tf['fileimgthumbs'], $attacharray['aid'], $new_file, $path, $month_dir, $img_dimensions);
		$attacharray['thumbs']['orig'] = $origdimarray;
		$attacharray['thumbs'] = serialize($attacharray['thumbs']);
	}
	
	return $attacharray;
}


function &xthreads_build_thumbnail($thumbdims, $aid, $filename, $path, $month_dir, $img_dimensions=null) {
	if(empty($img_dimensions)) {
		//$img_dimensions = @getimagesize($path.$month_dir.$filename);
		$img_dimensions = @getimagesize($filename);
	}
	$update_thumbs = array('orig' => array('w' => $img_dimensions[0], 'h' => $img_dimensions[1], 'type' => $img_dimensions[2]));
	if(is_array($img_dimensions)) {
		foreach($thumbdims as &$dims) {
			$p = strpos($dims, 'x');
			if(!$p) continue;
			$w = intval(substr($dims, 0, $p));
			$h = intval(substr($dims, $p+1));
			
			$destname = basename(substr($filename, 0, -6).$w.'x'.$h.'.thumb');
			
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
				@copy($filename, $path.$month_dir.$destname);
				$update_thumbs[$dims] = array('w' => $img_dimensions[0], 'h' => $img_dimensions[1], 'type' => $img_dimensions[2], 'file' => $month_dir.$destname);
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
// this will "smartly" terminate the transfer early if it's going to end up rejected anyway
function xthreads_fetch_url($url, $max_size=0, $valid_ext='', $valid_magic=array()) {
	global $lang;
	if(!$lang->xthreads_xtfurlerr_invalidurl) $lang->load('xthreads');
	$url = str_replace("\x0", '', $url);
	$purl = @parse_url($url);
	if(!$purl['host']) return array('error' => $lang->xthreads_xtfurlerr_invalidurl);
	if(XTHREADS_URL_FETCH_DISALLOW_HOSTS && in_array($purl['host'], explode(',', XTHREADS_URL_FETCH_DISALLOW_HOSTS)))
		return array('error' => $lang->xthreads_xtfurlerr_badhost);
	
	$portmap = array(
		'http' => 80,
		'https' => 443,
		'ftp' => 21,
	);
	$scheme = strtolower($purl['scheme']);
	
	if(!isset($portmap[$scheme])) return array('error' => $lang->xthreads_xtfurlerr_invalidscheme);
	if(!$purl['port'])
		$purl['port'] = $portmap[$scheme];
	elseif(XTHREADS_URL_FETCH_DISALLOW_PORT && $purl['port'] != $portmap[$scheme])
		return array('error' => $lang->xthreads_xtfurlerr_badport);
	
	$ret = array(
		'tmp_name' => tempnam(sys_get_temp_dir(), mt_rand()),
		'name' => basename($purl['path']),
		'size' => 0,
	);
	@unlink($ret['tmp_name']);
	if(!$ret['name']) $ret['name'] = 'index.html';
	$fp = @fopen($ret['tmp_name'], 'wb');
	if(!$fp) return array('error' => $lang->xthreads_xtfurlerr_cantwrite);
	
	xthreads_fetch_url_register_tmp($ret['tmp_name']);
	
	$referrer = $purl['scheme'].'://'.$purl['hostname'].'/';
	
	@set_time_limit(0);
	if(function_exists('curl_init')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_REFERER, $referrer);
		//curl_setopt($ch, CURLOPT_USERAGENT, '');
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		
		$GLOBALS['xtfurl_ret'] =& $ret;
		$GLOBALS['xtfurl_max_size'] = $max_size;
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'xthreads_fetch_url_header_curl');
		$GLOBALS['xtfurl_datalen'] = 0;
		$GLOBALS['xtfurl_magicchecked'] = false;
		$GLOBALS['xtfurl_validmagic'] =& $valid_magic;
		$GLOBALS['xtfurl_databuf'] = '';
		$GLOBALS['xtfurl_exts'] =& $valid_ext;
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'xthreads_fetch_url_curl_write');
		
		
		curl_setopt($ch, CURLOPT_FILE, $fp);
		$success = @curl_exec($ch);
		
		
		// check magic if not done
		if($success && !$GLOBALS['xtfurl_magicchecked'] && !empty($valid_magic)) {
			if(!xthreads_fetch_url_validmagic($GLOBALS['xtfurl_databuf'], $valid_magic)) {
				$GLOBALS['xtfurl_magicchecked'] = 'invalid';
				curl_close($ch);
			}
		}
		
		// check for early termination conditions
		if($ret['size'] && $max_size && $ret['size'] > $max_size) {
			$ret['error'] = $lang->sprintf($lang->error_attachsize, $max_size/1024);
		}
		elseif($GLOBALS['xtfurl_magicchecked'] == 'invalid') {
			$ret['error'] = $lang->error_attachtype;
		}
		// the whole file transferred?
		elseif($success) {
			// appears successful...
			curl_close($ch);
		}
		else {
			$ret['error'] = $lang->sprintf($lang->xthreads_xtfurlerr_curl, curl_errno($ch), curl_error($ch));
			curl_close($ch);
		}
	}
 	else if(function_exists('fsockopen')) {
		if(!$purl['path'])
			$purl['path'] = '/';
		if($purl['query'])
			$purl['path'] .= '?'.$purl['query'];
		if($fr = @fsockopen($purl['host'], $purl['port'], $error_no, $error, 10)) {
			@stream_set_timeout($fr, 10);
			$headers = array(
				'GET '.$purl['path'].' HTTP/1.1',
				'Host: '.$purl['host'],
				'Connection: close',
				'Accept: */*',
				'Accept-Charset: '.$lang->settings['charset'].';q=0.5, *;q=0.2',
				'Accept-Language: '.$lang->settings['htmllang'].';q=0.5, *;q=0.3', // will be the user's lang preference first
				'Referrer: '.$referrer,
				"\r\n",
			);
			
			if(@fwrite($fr, implode("\r\n", $headers))) {
				$datalen = 0;
				$databuf = '';
				$magicchecked = false;
				$doneheaders = false;
				while(!feof($fr)) {
					$data = fill_fread($fr, 16384);
					$len = strlen($data);
					// TODO: remove partial fill handling code below
					if(!$doneheaders) {
						$p = strpos($data, "\r\n\r\n");
						if(!$p || $p > 12288) { // should be no reason to have >12KB headers
							$ret['error'] = $lang->xthreads_xtfurlerr_headernotfound;
							break;
						}
						// parse headers
						foreach(explode("\r\n", substr($data, 0, $p)) as $header) {
							$res = xthreads_fetch_url_header($header);
							if($res['size'] && $max_size && $res['size'] > $max_size) {
								$ret['error'] = $lang->sprintf($lang->error_attachsize, $max_size/1024);
								break;
							}
							if($res['name'])
								$ret['name'] = $res['name'];
							if($res['type'])
								$ret['type'] = $res['type'];
						}
						if($ret['error']) break;
						
						// check extension
						if(!xthreads_fetch_url_validext($ret['name'], $valid_ext)) {
							$ret['error'] = $lang->error_attachtype;
							break;
						}
						
						$p += 4;
						$data = substr($data, $p);
						$len -= $p;
						$doneheaders = true;
					}
					if($len) {
						if($datalen + $len > 255) {
							if(!$magicchecked) {
								$databuf .= substr($data, 0, min(255, $datalen+$len)-$datalen);
								if(!xthreads_fetch_url_validmagic($databuf, $valid_magic)) {
									$ret['error'] = $lang->error_attachtype;
									break;
								}
								$magicchecked = true;
							}
						} else {
							$databuf .= $data;
						}
						$datalen += $len;
						if($max_size && $datalen > $max_size) {
							$ret['error'] = $lang->sprintf($lang->error_attachsize, $max_size/1024);
							break;
						}
						fwrite($fp, $data);
					}
				}
				if(!$magicchecked) {
					if(!xthreads_fetch_url_validmagic($databuf, $valid_magic)) {
						$ret['error'] = $lang->error_attachtype;
					}
				}
				fclose($fr);
			}
			else {
				$ret['error'] = $lang->xthreads_xtfurlerr_cantwritesocket;
				fclose($fr);
			}
		} else
			$ret['error'] = $lang->sprintf($lang->xthreads_xtfurlerr_socket, $error_no, $error);
	}
	else {
		if(xthreads_fetch_url_validext($ret['name'], $valid_ext)) {
			$fr = @fopen($url, 'rb');
			if($fr) {
				$datalen = 0;
				while(!feof($fr)) {
					$data = fill_fread($fr, 16384);
					$len += strlen($data);
					
					if(!$datalen) {
						if(!xthreads_fetch_url_validmagic($data, $valid_magic)) {
							$ret['error'] = $lang->error_attachtype;
							break;
						}
					}
					
					$datalen += $len;
					if($max_size && $datalen > $max_size) {
						$ret['error'] = $lang->sprintf($lang->error_attachsize, $max_size/1024);
						break;
					}
					fwrite($fp, $data);
				}
				fclose($fr);
			} else
				$ret['error'] = $lang->xthreads_xtfurlerr_urlopenfailed;
		} else
			$ret['error'] = $lang->error_attachtype;
	}
	fclose($fp);
	if($ret['error'])
		@unlink($ret['tmp_name']);
	else {
		$ret['size'] = @filesize($ret['tmp_name']);
		if($ret['size'] < 1 || empty($ret['name'])) // weird...
			@unlink($ret['tmp_name']);
	}
	
	@set_time_limit(0);
	return $ret;
}
function xthreads_fetch_url_validext(&$name, &$exts) {
	if($exts) {
		$ext = strtolower(get_extension($name));
		if(strpos('|'.strtolower($exts).'|', '|'.$ext.'|') === false) {
			return false;
		}
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
function xthreads_fetch_url_header($header) {
	$header = trim($header);
	$p = strpos($header, ':');
	if(!$p) return null;
	$hdata = trim(substr($header, $p+1));
	switch(strtolower(substr($header, 0, $p))) {
		case 'content-length':
			$size = intval($hdata);
			if($size) {
				return array('size' => $size);
			}
		break;
		case 'content-disposition':
			foreach(explode(';', $hdata) as $disp) {
				$disp = trim($disp);
				if(strtolower(substr($disp, 0, 9)) == 'filename=') {
					$tmp = substr($disp, 9);
					if($tmp) {
						if($tmp{0} == '"' && $tmp{strlen($tmp)-1} == '"')
							$tmp = substr($tmp, 1, -1);
						return array('name' => trim(str_replace("\x0", '', $tmp)));
					}
				}
			}
		break;
		case 'content-type':
			return array('type' => $hdata);
		break;
	}
	return null;
}
function xthreads_fetch_url_header_curl($ch=null, $header) {
	$res = xthreads_fetch_url_header($header);
	if($res['size'] && $GLOBALS['xtfurl_max_size'] && $res['size'] > $GLOBALS['xtfurl_max_size']) {
		//$GLOBALS['xtfurl_ret']['error'] = ;
		$GLOBALS['xtfurl_ret']['size'] = $res['size'];
		curl_close($ch);
		return 0;
	}
	if($res['name']) {
		$GLOBALS['xtfurl_ret']['name'] = $res['name'];
	}
	if($res['type'])
		$GLOBALS['xtfurl_ret']['type'] = $res['type'];
	return strlen($header);
}
function xthreads_fetch_url_curl_write($ch, $data) {
	$len = strlen($data);
	global $xtfurl_datalen, $xtfurl_magicchecked;
	
	// check extension
	if(!$xtfurl_datalen) {
		if(!xthreads_fetch_url_validext($GLOBALS['xtfurl_ret']['name'], $GLOBALS['xtfurl_exts'])) {
			$xtfurl_magicchecked = 'invalid'; // dirty, but works...
			curl_close($ch);
			return 0;
		}
	}
	
	$xtfurl_datalen += $len;
	if($GLOBALS['xtfurl_max_size'] && $xtfurl_datalen > $GLOBALS['xtfurl_max_size']) {
		$GLOBALS['xtfurl_ret']['size'] = $xtfurl_datalen;
		curl_close($ch);
		return 0;
	}
	if(!$xtfurl_magicchecked && !empty($GLOBALS['xtfurl_validmagic'])) {
		global $xtfurl_databuf;
		if($xtfurl_datalen >= 255) {
			// check magic
			$xtfurl_databuf .= substr($data, 0, min(255, $xtfurl_datalen)-$xtfurl_datalen+$len);
			if(!xthreads_fetch_url_validmagic($xtfurl_databuf, $GLOBALS['xtfurl_validmagic'])) {
				$xtfurl_magicchecked = 'invalid';
				curl_close($ch);
				return 0;
			}
			$xtfurl_magicchecked = true;
		} else {
			$xtfurl_databuf .= $data;
		}
	}
	return $len;
}


// since fread'ing won't necessarily fill the requested buffer size...
function &fill_fread(&$fp, $len) {
	$fill = 0;
	$ret = '';
	while(!feof($fp) && $len > 0) {
		$data = fread($fp, $len);
		$len -= strlen($data);
		$ret .= $data;
	}
	return $ret;
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

?>