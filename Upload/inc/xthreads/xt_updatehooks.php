<?php
/**
 * All "common" update (eg newthread etc) hooks placed here to make main plugin file smaller
 */

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_editpost_autofill() {
	global $mybb, $post, $thread;
	if($thread['firstpost'] != $post['pid']) return;
	// fill in missing stuff in edit post
	
	// auto-filling postoptions is difficult because we can't differentiate between unticked and not sent
	// so use heuristic -> if no message/subject sent and no options, we'll assume postoptions not sent
	if(!isset($mybb->input['postoptions']) && !isset($mybb->input['subject']) && !isset($mybb->input['message'])) {
		$mybb->input['postoptions'] = array(
			'signature' => $post['includesig'],
			'disablesmilies' => $post['smilieoff'],
		);
		// we don't need to worry about 'subscriptionmethod'
	}
	
	// it would be nicer to simply unset posthandler vars, but this doesn't seem possible to do (plus the post-validate hack is ugly); this solution is short and simple and works(tm)
	foreach(array('subject','icon','message') as $key) {
		if(!isset($mybb->input[$key]))
			$mybb->input[$key] = $post[$key];
	}
	if($mybb->version_code >= 1500 && !isset($mybb->input['threadprefix']))
		$mybb->input['threadprefix'] = $thread['prefix'];
}

// filters an input tfcache, removing items which cannot be modified by current user
function xthreads_filter_tfeditable(&$tf, $fid=0) {
	foreach($tf as $k => &$v) {
		if(!empty($v['editable_gids'])) {
			if(!xthreads_user_in_groups($v['editable_gids']))
				unset($tf[$k]);
		}
		elseif(($v['editable'] == XTHREADS_EDITABLE_MOD && !is_moderator($fid)) ||
		   ($v['editable'] == XTHREADS_EDITABLE_ADMIN && $GLOBALS['mybb']->usergroup['cancp'] != 1) ||
		   ($v['editable'] == XTHREADS_EDITABLE_NONE))
			unset($tf[$k]);
	}
}

function xthreads_tfvalue_settable(&$tf, $val) {
	if(empty($tf['editable_values'])) return true;
	$cv = (string)xthreads_convert_str_to_datatype($val, $tf['datatype']);
	$allow_groups = $tf['editable_values'][$cv];
	return (!isset($allow_groups)
		|| (!xthreads_empty($allow_groups) && xthreads_user_in_groups($allow_groups)));
}

function xthreads_input_posthandler_postvalidate(&$ph) {
	// determine if first post
	$pid = (int)$ph->data['pid'];
	if(!$pid) return;
	$post = get_post($pid); // will be cached so won't actually cause additional query
	$thread = get_thread($post['tid']); // should be cached from editpost.php, so doesn't cost another query
	if($thread['firstpost'] == $pid)
		xthreads_input_posthandler_validate($ph, true);
}
function xthreads_input_posthandler_validate(&$ph, $update=false) {
	global $threadfield_cache, $lang, $forum;
	
	// blank message hack
	if($forum['xthreads_allow_blankmsg'] && (isset($ph->data['message']) || $ph->method == 'insert') && my_strlen($ph->data['message']) == 0) {
		// enforce blank message if we got here simply by a quirk in my_strlen
		$ph->data['message'] = '';
		// remove errors that MyBB added
		foreach($ph->errors as $k => &$v) {
			if($v['error_code'] == 'missing_message' || $v['error_code'] == 'message_too_short')
				unset($ph->errors[$k]);
		}
	}
	
	$fid = $ph->data['fid'];
	if(!$fid) {
		global $thread, $post, $foruminfo;
		if($GLOBALS['fid']) $fid = $GLOBALS['fid'];
		elseif($forum['fid']) $fid = $forum['fid'];
		elseif($thread['fid']) $fid = $thread['fid'];
		elseif($post['fid']) $fid = $post['fid'];
		elseif($foruminfo['fid']) $fid = $foruminfo['fid'];
	}
	
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($fid);
	// remove uneditable fields
	xthreads_filter_tfeditable($threadfield_cache, $fid); // NOTE: modifies the global tfcache!
	if(empty($threadfield_cache)) return;
	
	// language setup
	$lang->load('xthreads');
	
	$data = array();
	$update_tid = false;
	if($update) { // try to retrieve tid
		if($ph->data['tid']) $update_tid = $ph->data['tid'];
		elseif($ph->tid) $update_tid = $ph->tid;
		elseif($ph->data['pid']) $pid = $ph->data['pid'];
		elseif($ph->pid) $pid = $ph->pid;
		else { // start trying global vars
			global $tid, $thread, $post, $pid;
			if($thread['tid']) $update_tid = $thread['tid'];
			elseif($post['tid']) $update_tid = $post['tid'];
			elseif($tid) $update_tid = $tid;
			// elseif($pid) will automatically go below
		}
		
		if(isset($pid) && $pid) {
			$p = get_post($pid);
			$update_tid = $p['tid'];
		}
	}
	$errors = xthreads_input_validate($data, $threadfield_cache, $update_tid);
	xthreads_posthandler_add_errors($ph, $errors);
	
	foreach($data as $k => &$v)
		$ph->data['xthreads_'.$k] = $v;
}
function xthreads_input_validate(&$data, &$threadfield_cache, $update=false) {
	global $mybb;
	
	$errors = array();
	// set things from input
	foreach($threadfield_cache as $k => &$v) {
		if($v['editable'] == XTHREADS_EDITABLE_NONE) continue;
		$singleval = xthreads_empty($v['multival']);
		
		$input =& $mybb->input['xthreads_'.$k];
		if(isset($input)) {
			if($v['inputtype'] == XTHREADS_INPUT_FILE) {
				// value should be safe as it should've been sanitised in xthreads_upload_attachments, but we'll be pedantic just in case
				if($singleval)
					$inval = (int)$input;
				elseif(!is_array($input))
					$inval = array();
				else {
					$inval = array_unique(array_filter(array_map('intval', $input)));
				}
			}
			elseif($v['inputtype'] == XTHREADS_INPUT_FILE_URL) {
				if(is_numeric($input))
					$inval = (int)$input;
				else
					$inval = trim($input);
			}
			elseif(!$singleval && (
					($input_is_array = is_array($input)) || ($v['inputtype'] == XTHREADS_INPUT_TEXT || $v['inputtype'] == XTHREADS_INPUT_TEXTAREA)
			)) {
				$inval = $input;
				if(!$input_is_array) {
					$tr = array("\r" => '');
					if($v['inputtype'] == XTHREADS_INPUT_TEXT)
						$tr["\n"] = '';
					$inval = explode(($v['inputtype'] == XTHREADS_INPUT_TEXTAREA ? "\n":','), strtr($inval, $tr));
				}
				$inval = array_unique(array_map('trim', $inval));
				foreach($inval as $valkey => &$val)
					if(xthreads_empty($val)) unset($inval[$valkey]);
				if($v['multival_limit'] && count($inval) > $v['multival_limit'])
					$errors[] = array('threadfield_multival_limit', array($v['multival_limit'], htmlspecialchars_uni($v['title'])));
			}
			else
				$inval = trim($input);
		}
		else {
			$inval = null;
			if($update) continue;
		}
		
		$evalfunc = 'xthreads_evalcache_'.$k;
		if($v['editable'] == XTHREADS_EDITABLE_REQ && (!isset($inval) || xthreads_empty($inval))) {
			$errors[] = array('threadfield_required', htmlspecialchars_uni($v['title']));
		}
		elseif(isset($inval)) {
			if($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) {
				// TODO: perhaps have URL validation here (for type FILE_URL)
				if($v['inputvalidate']) {
					// one may think that it makes more sense to do the input validation when the file is actually attached, however, we do it here to maintain a constant environment of evaluation
					foreach((is_array($inval) ? $inval:array($inval)) as $aid) {
						$attachedfile =& $GLOBALS['xta_cache'][$aid];
						if(!empty($attachedfile) && ($error = trim($evalfunc('inputvalidate', array('FILENAME' => $attachedfile['filename'], 'FILESIZE' => $attachedfile['filesize'], 'NUM_FILES' => count($inval))))) !== '') {
							$errors[] = $error;
							break;
						}
					}
				}
				$data[$k] = (is_array($inval) ? implode(',', $inval):$inval);
			}
			else {
				// validate input
				if(is_array($inval))
					$inval_list =& $inval;
				else
					$inval_list = array($inval); // &$inval generates recursion for some odd reason at times
				foreach($inval_list as &$val) {
					// check usergroup perms for values
					if(!xthreads_tfvalue_settable($v, $val)) {
						// if updating, double-check for current value
						$settable = false;
						if($update) {
							static $tfd_cache=null;
							if(!isset($tfd_cache)) $tfd_cache = array();
							if(!isset($tfd_cache[$update])) { // we should only ever have one thread, but we'll be flexible...
								global $db;
								$tfd_cache[$update] = $db->fetch_array($db->simple_select('threadfields_data', '*', 'tid='.$update));
							}
							$tfd =& $tfd_cache[$update];
							
							if($val == $tfd[$k])
								$settable = true;
						}
						if(!$settable) {
							$errors[] = array('threadfield_cant_set', htmlspecialchars_uni($v['title']));
							break;
						}
					}
					
					if(xthreads_empty($val)) continue; // means that if the field wasn't set and isn't a necessary field, ignore it
					if($v['maxlen'] && my_strlen($val) > $v['maxlen']) {
						$errors[] = array('threadfield_toolong', array(htmlspecialchars_uni($v['title']), $v['maxlen']));
						break;
					}
					elseif(!empty($v['vallist'])) {
						if(!isset($v['vallist'][$val])) {
							$errors[] = array('threadfield_invalidvalue', htmlspecialchars_uni($v['title']));
							break;
						}
					}
					elseif($v['textmask'] && !preg_match('~'.str_replace('~', '\\~', $v['textmask']).'~si', $val)) {
						$errors[] = array('threadfield_invalidvalue', htmlspecialchars_uni($v['title']));
						break;
					}
				}
				
				if(is_array($inval))
					$data[$k] = implode("\n", $inval);
				else
					$data[$k] = $inval;
				
				if($v['inputvalidate']) {
					if(($error = trim($evalfunc('inputvalidate', array('VALUE' => $data[$k])))) !== '') {
						$errors[] = $error;
					}
				}
			}
		}
		elseif(!$update) {
			if(!xthreads_tfvalue_settable($v, null)) // value not set - double check that this isn't denied by value permissions
				$errors[] = array('threadfield_cant_set', htmlspecialchars_uni($v['title']));
			if($v['inputvalidate']) {
				if(($error = trim($evalfunc('inputvalidate', array('VALUE' => null)))) !== '') {
					$errors[] = $error;
				}
			}
		}
	}
	return $errors;
}
function xthreads_input_posthandler_insert(&$ph) {
	if(!empty($ph->thread_update_data)) { //!!! TODO: Note, bug in earlier versions of MyBB!
		$data = &$ph->thread_update_data;
		$update = true;
	}
	elseif(!empty($ph->thread_insert_data)) {
		$data = &$ph->thread_insert_data;
		$update = false;
		// try to determine if updating a draft (dirty, but should work)
		if(!isset($data['fid']) && !isset($data['uid']))
			$update = true;
	}
	else return;
	
	
	global $threadfield_cache, $db;
	$fid = $data['fid']; // TODO: will this ever NOT be set?
	if(!$fid) $fid = $GLOBALS['fid'];
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($fid);
	if(empty($threadfield_cache)) return;
	
	$updates = array();
	$xtaupdates = array();
	foreach($threadfield_cache as $k => &$v) {
		$evalfunc = 'xthreads_evalcache_'.$k;
		if(isset($ph->data['xthreads_'.$k])) {
			if(($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) && is_numeric(str_replace(',','',$ph->data['xthreads_'.$k])))
				$xtaupdates[] = $ph->data['xthreads_'.$k]; // if multiple, it's already comma delimited, so naturally works after imploding :)
			
			$updates[$k] = $ph->data['xthreads_'.$k];
			if($v['inputformat']) {
				$updates[$k] = $evalfunc('inputformat', array('VALUE' => $updates[$k]));
			}
		}
		// special case for newthread: value not supplied and there's a custom input format -> we need to run through it
		elseif(!$update && $v['inputformat']) {
			$updates[$k] = $evalfunc('inputformat', array('VALUE' => null));
		}
		if(isset($updates[$k]))
			$updates[$k] = xthreads_convert_str_to_datatype($updates[$k], $v['datatype']);
		else
			unset($updates[$k]); // I don't think this will ever do anything, because inputformat is forced to a string, but we'll stick it here nonetheless :P
	}
	
	if(empty($updates)) return;
	
	if($ph->tid)
		$tid = $ph->tid;
	elseif($data['tid'])
		$tid = $data['tid'];
	else
		$tid = $ph->data['tid'];
	
	if(!empty($xtaupdates)) {
		$db->update_query('xtattachments', array('tid' => $tid), 'aid IN ('.implode(',', $xtaupdates).')');
	}
	
	if($update) {
		xthreads_db_update('threadfields_data', $updates, 'tid='.$tid);
		// check if actually updated (it may be possible that an entry for this thread isn't added yet)
		if($db->affected_rows() > 0)
			return;
		// otherwise, fall through and run a replace query
	}
	
	$updates['tid'] = $tid;
	xthreads_db_replace('threadfields_data', $updates, 'tid='.$tid);
}

function xthreads_convert_str_to_datatype($s, $type) {
	if($type == XTHREADS_DATATYPE_TEXT) return $s;
	$sl = strtoupper($s);
	if($s === '' || $sl === 'NULL' || $sl === 'NUL') return null;
	switch($type) {
		case XTHREADS_DATATYPE_INT:
		case XTHREADS_DATATYPE_BIGINT:
			return (int)$s;
		case XTHREADS_DATATYPE_UINT:
		case XTHREADS_DATATYPE_BIGUINT:
			return (int)abs((int)$s);
		case XTHREADS_DATATYPE_FLOAT:
			return doubleval($s);
	}
}

function xthreads_inputdisp() {
	global $thread, $post, $fid, $mybb, $plugins;
	
	// work around for editpost bug in MyBB prior to 1.4.12 (http://dev.mybboard.net/issues/374)
	// this function should only ever be run once
	static $called = false;
	if($called) return;
	$called = true;
	
	$editpost = ($GLOBALS['current_page'] == 'editpost.php');
	if($editpost) {
		// because the placement of the editpost_start hook really sucks...
		if(!$post) {
			$post = get_post((int)$mybb->input['pid']); // hopefully MyBB will also use get_post in their code too...
		}
		if(!$thread) {
			if(!empty($post))
				$thread = get_thread($post['tid']);
			if(empty($thread)) return;
		}
		if(!$fid) $fid = $thread['fid'];
		// check if first post
		if($post['pid'] != $thread['firstpost']) return;
	}
	
	if($mybb->request_method == 'post') {
		$recvfields = array();
		foreach($mybb->input as $k => &$v)
			if(substr($k, 0, 9) == 'xthreads_')
				$recvfields[substr($k, 9)] =& $v;
		_xthreads_input_generate($recvfields, $fid, $thread['tid']);
	}
	elseif($editpost || ($mybb->input['action'] == 'editdraft' && $thread['tid'])) {
		$blank = array();
		_xthreads_input_generate($blank, $fid, $thread['tid']);
	}
	else { // newthread.php
		$blank = array();
		_xthreads_input_generate($blank, $fid);
		
		// is this really a good idea? it may be possible to delete the current attachments connected to this post!
		// Update: hmm, perhaps unlikely, since this will only run if a POST request isn't made
		$plugins->add_hook('newthread_end', 'xthreads_attach_clear_posthash');
	}
	
	// editpost_first template hack
	if($editpost && !xthreads_empty($GLOBALS['templates']->cache['editpost_first'])) {
		$plugins->add_hook('editpost_end', 'xthreads_editpost_first_tplhack');
	}
	
	if($mybb->input['previewpost'] || $editpost) {
		global $threadfields, $forum;
		
		// $forum may not exist for editpost
		if(empty($forum)) {
			if(!empty($GLOBALS['thread'])) // should be set
				$fid = $GLOBALS['thread']['fid'];
			else {
				// last ditch resort, grab everything from the post
				$pid = (int)$mybb->input['pid'];
				$post = get_post($pid);
				$fid = $post['fid'];
			}
			$forum = get_forum($fid);
		}
		
		$threadfields = array();
		$threadfield_cache = xthreads_gettfcache($fid); // don't use global cache as that will probably have been cleared of uneditable fields
		$errors = xthreads_input_validate($threadfields, $threadfield_cache, ($editpost ? $thread['tid'] : false));
		// don't validate here if on editpost, as MyBB will do it later (does a full posthandler validate call)
		// unfortunately, this method has the side effect of running our validation function twice :( [but not a big issue I guess]
		if($editpost || empty($errors)) {
			// grab threadfields
			global $db;
			
			if(!empty($threadfield_cache)) {
				if($thread['tid']) {
					$curthreaddata = array();
					foreach($threadfield_cache as $k => &$v)
						if(!isset($threadfields[$k])) {
							if(empty($curthreaddata)) {
								$curthreaddata = $db->fetch_array($db->simple_select('threadfields_data', '`'.implode('`,`', array_keys($threadfield_cache)).'`', 'tid='.$thread['tid']));
								if(empty($curthreaddata)) break; // there isn't anything set for this thread
							}
							
							$threadfields[$k] =& $curthreaddata[$k];
						}
					
					$tidstr = $thread['tid'];
					$posthashstr = '';
					$usernamestr = $thread['username'];
				} else {
					$tidstr = '';
					$posthashstr = $mybb->input['posthash'];
					$usernamestr = $mybb->user['username'];
				}
				foreach($threadfield_cache as $k => &$v) {
					xthreads_get_xta_cache($v, $tidstr, $posthashstr);
					xthreads_sanitize_disp($threadfields[$k], $v, $usernamestr);
				}
			}
			
			// do first post hack if applicable
			//if($forum['xthreads_firstpostattop']) {
				//require_once MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php';
				// above file should already be included
				if(function_exists('xthreads_tpl_postbithack'))
					xthreads_tpl_postbithack();
			//}
		} else {
			// block preview if there's errors
			// <removed previously commented out code which blocked preview by unsetting the previewpost input>
			// we'll block by forcing an error, so we can get a hook in somewhere...
			if(!$mybb->input['ajax']) { // will not happen, but be pedantic
				$GLOBALS['xthreads_backup_subject'] = $mybb->input['subject'];
				$GLOBALS['xthreads_preview_errors'] =& $errors;
				$mybb->input['subject'] = '';
				control_object($GLOBALS['templates'], '
					function get($title, $eslashes=1, $htmlcomments=1) {
						static $done = false;
						if(!$done && $title == "error_inline") {
							$done = true;
							return str_replace(\'{$errorlist}\', xthreads_blockpreview_hook(), parent::get($title, $eslashes, $htmlcomments));
						}
						return parent::get($title, $eslashes, $htmlcomments);
					}
				');
				function xthreads_blockpreview_hook() {
					global $posthandler;
					$posthandler->data['subject'] = $GLOBALS['mybb']->input['subject'] = $GLOBALS['xthreads_backup_subject'];
					// remove the error
					foreach($posthandler->errors as $k => &$v)
						if($v['error_code'] == 'missing_subject' || $v['error_code'] == 'firstpost_no_subject')
							unset($posthandler->errors[$k]);
					// and recheck
					$posthandler->verify_subject();
					
					xthreads_posthandler_add_errors($posthandler, $GLOBALS['xthreads_preview_errors']);
					
					$GLOBALS['lang']->load('xthreads');
					$errorlist = '';
					foreach($posthandler->get_friendly_errors() as $error)
						$errorlist .= '<li>'.$error.'</li>';
					return $errorlist;
				}
			}
		}
		
		// message length hack for newthread
		if(!$editpost && $forum['xthreads_allow_blankmsg'] && my_strlen($mybb->input['message']) == 0) {
			$mybb->input['message'] = str_repeat('-', max((int)$mybb->settings['minmessagelength'], 1));
			function xthreads_newthread_prev_blankmsg_hack() {
				static $done=false;
				if($done) return;
				$done=true;
				$GLOBALS['message'] = '';
				$GLOBALS['mybb']->input['message'] = '';
			}
			function xthreads_newthread_prev_blankmsg_hack_postbit(&$p) {
				$p['message'] = '';
				xthreads_newthread_prev_blankmsg_hack();
			}
			$plugins->add_hook('newthread_end', 'xthreads_newthread_prev_blankmsg_hack');
			$plugins->add_hook('postbit_prev', 'xthreads_newthread_prev_blankmsg_hack_postbit');
		}
	}
}
function xthreads_posthandler_add_errors(&$ph, &$errors) {
	// force uniquifier onto the end of the error code
	static $cnt=0;
	global $lang;
	foreach($errors as $error) {
		// ugly hack; alternative is to push errors directly into the property, since it's declared public, which is ugly too
		if(is_string($error)) {
			$ph->set_error($newname = 'strerror__'.($cnt++));
			$newlangname = $ph->language_prefix.'_'.$newname;
			$lang->$newlangname = $error;
		} else {
			$newname = $error[0].'__'.($cnt++);
			$langname = $ph->language_prefix.'_'.$error[0];
			$newlangname = $ph->language_prefix.'_'.$newname;
			$lang->$newlangname =& $lang->$langname;
			isset($error[1]) or $error[1] = '';
			$ph->set_error($newname, $error[1]);
			//call_user_func_array(array($ph, 'set_error'), $error);
		}
	}
}

// be a little pedantic and delete any xtattachments which has the same posthash as the one selected
// should be very rare, but we'll be extra careful
// also can potentially be problematic too, but deleting an attachment not abandoned is perhaps even rarer
function xthreads_attach_clear_posthash() {
	if(mt_rand(0, 10) > 1) return; // dirty hack to speed things up a little
	require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
	xthreads_rm_attach_query('posthash="'.$GLOBALS['db']->escape_string($GLOBALS['posthash']).'"');
}
function xthreads_editpost_first_tplhack() {
	control_object($GLOBALS['templates'], '
		function get($title, $eslashes=1, $htmlcomments=1) {
			static $done=false;
			if(!$done && $title == "editpost") {
				$done = true;
				return parent::get($title, $eslashes, $htmlcomments).\'"; $editpost = "\'.$this->get("editpost_first", $eslashes, $htmlcomments);
			}
			return parent::get($title, $eslashes, $htmlcomments);
		}
	');
}

// TODO: merge this function into ...
function _xthreads_input_generate(&$data, $fid, $tid=0) {
	global $threadfield_cache;
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($fid);
	xthreads_filter_tfeditable($threadfield_cache, $fid); // NOTE: modifies the global tfcache!
	xthreads_input_generate($data, $threadfield_cache, $fid, $tid);
}

function xthreads_input_generate(&$data, &$threadfields, $fid, $tid=0) {
	global $tfinput, $tfinputrow, $extra_threadfields, $lang, $xthreads_threadin_tabindex_shift, $mybb;
	if(!$lang->xthreads_attachfile) $lang->load('xthreads');
	
	// if a thread ID is supplied, grab the current values
	if($tid) {
		static $tfd_cache=null;
		if(!isset($tfd_cache)) $tfd_cache = array();
		if(!isset($tfd_cache[$tid])) { // we should only ever have one thread, but we'll be flexible...
			global $db;
			$tfd_cache[$tid] = $db->fetch_array($db->simple_select('threadfields_data', '*', 'tid='.$tid));
		}
		$tfd =& $tfd_cache[$tid];
	}
	
	$tfinput = $tfinputrow = array();
	$extra_threadfields = '';
	foreach($threadfields as $k => $tf) {
		$tf['title'] = htmlspecialchars_uni($tf['title']);
		$tf['field'] = htmlspecialchars_uni($tf['field']);
		$tf['desc'] = htmlspecialchars_uni($tf['desc']);
		$vars = array(
			'KEY' => $tf['field'],
			'NAME_PROP' => ' name="xthreads_'.$tf['field'].'"',
			'MAXLEN' => (int)$tf['maxlen'],
			'WIDTH' => (int)$tf['fieldwidth'],
			'HEIGHT' => (int)$tf['fieldheight'],
			'TABINDEX' => '',
			'TABINDEX_PROP' => '',
			'REQUIRED' => ($tf['editable'] == XTHREADS_EDITABLE_REQ),
			'MULTIPLE' => (xthreads_empty($tf['multival'])?'':1),
			'MULTIPLE_LIMIT' => $tf['multival_limit'],
			'MULTIPLE_PROP' => '',
		);
		if($vars['MAXLEN']) $vars['MAXLEN_PROP'] = ' maxlength="'.$vars['MAXLEN'].'"';
		if($vars['WIDTH']) {
			$vars['WIDTH_PROP_SIZE'] = ' size="'.$vars['WIDTH'].'"';
			$vars['WIDTH_CSS'] = 'width: '.($vars['WIDTH']/2).'em;'; // only used for select box [in Firefox, seems we need to divide by 2 to get the equivalent width]
			$vars['WIDTH_PROP_COLS'] = ' cols="'.$vars['WIDTH'].'"';
		}
		if(!$vars['HEIGHT'] && !xthreads_empty($tf['multival']))
			$vars['HEIGHT'] = 5;
		if($vars['HEIGHT']) {
			$vars['HEIGHT_PROP_SIZE'] = ' size="'.$vars['HEIGHT'].'"';
			$vars['HEIGHT_CSS'] = 'height: '.($vars['HEIGHT']/2).'em;';
			$vars['HEIGHT_PROP_ROWS'] = ' rows="'.$vars['HEIGHT'].'"';
		}
		if($vars['MULTIPLE'])
			$vars['MULTIPLE_PROP'] = ' multiple="multiple"';
		if($vars['REQUIRED'])
			$vars['REQUIRED_PROP'] = ' required="required"';
		
		$using_default = false;
		if(!isset($data)) // no threadfield data set for this thread
			$defval = '';
		elseif(isset($data[$k]))
			$defval = $data[$k];
		elseif($tid) // currently set value
			$defval = $tfd[$k];
		elseif($tf['inputtype'] != XTHREADS_INPUT_FILE) {
			$defval = eval_str($tf['defaultval']);
			// we don't want $defval to be an array for textual inputs, so split it later
			$using_default = true;
		}
		
		unset($defvals);
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_SELECT:
			case XTHREADS_INPUT_RADIO:
			case XTHREADS_INPUT_CHECKBOX:
				$vals = $tf['vallist'];
				if(!xthreads_empty($tf['multival'])) {
					if($using_default)
						$defval = explode("\n", str_replace("\r", '', $defval));
					if(is_array($defval))
						$defvals =& $defval;
					else
						$defvals = explode("\n", str_replace("\r", '', $defval));
					$defvals = array_map('htmlspecialchars_uni', $defvals);
					unset($vals['']);
				}
				// give blank option if none is actually required
				elseif($tf['editable'] != XTHREADS_EDITABLE_REQ && $tf['inputtype'] != XTHREADS_INPUT_CHECKBOX) {
					if(!isset($vals['']))
						// can't array_unshift with a key...
						$vals = array('' => '<span style="font-style: italic;">'.$lang->xthreads_val_blank.'</span>') + $vals;
				} else
					unset($vals['']);
			break;
			case XTHREADS_INPUT_FILE:
				if(!xthreads_empty($tf['multival']) && !is_array($defval)) {
					$defval = explode(',', $defval);
				}
				
		}
		if(!isset($defvals) && ($tf['inputtype'] != XTHREADS_INPUT_FILE && $tf['inputtype'] != XTHREADS_INPUT_FILE_URL))
			$defval = htmlspecialchars_uni($defval);
		
		if($tf['tabstop']) {
			$vars['TABINDEX'] = ++$xthreads_threadin_tabindex_shift +1;
			$vars['TABINDEX_PROP'] = ' tabindex="__xt_'.$vars['TABINDEX'].'"';
			xthreads_fix_tabindexes();
		}
		
		if($tf['formhtml'])
			$evalfunc = 'xthreads_evalcache_'.$tf['field'];
		else
			$evalfunc = 'xthreads_input_generate_defhtml_'.$tf['inputtype'];
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_TEXTAREA:
				$vars['VALUE'] =& $defval;
				break;
			case XTHREADS_INPUT_SELECT:
				if(!xthreads_empty($tf['multival'])) {
					$vars['NAME_PROP'] = ' name="xthreads_'.$tf['field'].'[]"';
				}
				$vars['ITEMS'] = '';
				foreach($vals as $val => $valdisp) {
					if((!$tid || $tfd[$k] != $val) && !xthreads_tfvalue_settable($tf, $val)) continue;
					$val = htmlspecialchars_uni($val);
					$vars['VALUE'] =& $val;
					$vars['SELECTED'] = ((isset($defvals) && in_array($val, $defvals)) || $defval === $val ? ' selected="selected"':'');
					
					if(preg_match('~^\<span style\="([^"]*?)"\>(.*)\</span\>$~is', $valdisp, $style)) {
						$vars['LABEL'] = $style[2];
						$vars['STYLECSS'] = $style[1];
						$vars['STYLE'] = ' style="'.$vars['STYLECSS'].'"';
					} else {
						$vars['LABEL'] = $valdisp;
						$vars['STYLE'] = $vars['STYLECSS'] = '';
					}
					$vars['LABEL'] = htmlspecialchars_uni($vars['LABEL']);
					$vars['ITEMS'] .= $evalfunc('formhtml_item', $vars);
				}
				break;
			case XTHREADS_INPUT_CHECKBOX:
				$vars['NAME_PROP'] = ' name="xthreads_'.$tf['field'].'[]"';
				// fall through
			case XTHREADS_INPUT_RADIO:
				$vars['ITEMS'] = '';
				foreach($vals as $val => &$valdisp) {
					if((!$tid || $tfd[$k] != $val) && !xthreads_tfvalue_settable($tf, $val)) continue;
					$val = htmlspecialchars_uni($val);
					
					if((isset($defvals) && in_array($val, $defvals)) || $defval === $val) {
						$vars['SELECTED'] = ' selected="selected"';
						$vars['CHECKED'] = ' checked="checked"';
					} else
						$vars['SELECTED'] = $vars['CHECKED'] = '';
					
					$vars['VALUE'] =& $val;
					$vars['LABEL'] =& $valdisp;
					$vars['ITEMS'] .= $evalfunc('formhtml_item', $vars);
					$vars['TABINDEX_PROP'] = ''; // or maybe make each thing tabbable?
				}
				break;
			case XTHREADS_INPUT_FILE:
				if(!xthreads_empty($tf['multival'])) {
					$vars['NAME_PROP'] = ' name="xthreads_'.$tf['field'].'[]"';
					// lame language hack
					$GLOBALS['lang_xthreads_attachfile'] = $lang->xthreads_attachfile_plural;
					$GLOBALS['lang_xthreads_attachurl'] = $lang->xthreads_attachurl_plural;
				} else {
					$GLOBALS['lang_xthreads_attachfile'] = $lang->xthreads_attachfile;
					$GLOBALS['lang_xthreads_attachurl'] = $lang->xthreads_attachurl;
				}
				$vars['MAXSIZE'] = $tf['filemaxsize'];
				$vars['RESTRICT_TYPE'] = ($tf['fileimage']?'image':'');
				$vars['ACCEPT_PROP'] = ($vars['RESTRICT_TYPE']?' accept="'.$vars['RESTRICT_TYPE'].'/*"':'');
				if(XTHREADS_ALLOW_URL_FETCH) {
					// TODO: test if this environment can really fetch URLs
					$vars['VALUE_URL'] = htmlspecialchars_uni($mybb->input['xtaurl_'.$tf['field']]);
					if(xthreads_empty($vars['VALUE_URL'])) $vars['VALUE_URL'] = 'http://';
					if($vars['VALUE_URL'] != 'http://' || $mybb->input['xtasel_'.$tf['field']] == 'url') {
						$vars['CHECKED_UPLOAD'] = '';
						$vars['SELECTED_UPLOAD'] = '';
						$vars['CHECKED_URL'] = ' checked="checked"';
						$vars['SELECTED_URL'] = ' selected="selected"';
					} else {
						$vars['CHECKED_UPLOAD'] = ' checked="checked"';
						$vars['SELECTED_UPLOAD'] = ' selected="selected"';
						$vars['CHECKED_URL'] = '';
						$vars['SELECTED_URL'] = '';
					}
				}
				$vars['ITEMS'] = '';
				global $xta_cache, $db;
				if($defval) foreach((is_array($defval) ? $defval:array($defval)) as $aid) {
					if(!$aid || !is_numeric($aid)) continue;
					if(!isset($xta_cache[$aid])) {
						static $done_xta_cache = false;
						// need to cache them
						if(!$done_xta_cache) {
							$done_xta_cache = true;
							$qextra = '';
							if($mybb->input['posthash'])
								$qextra .= ' OR posthash="'.$db->escape_string($mybb->input['posthash']).'"';
							if($GLOBALS['thread']['tid'])
								$qextra .= ' OR tid='.$GLOBALS['thread']['tid'];
							$query = $db->simple_select('xtattachments', '*', 'aid IN('.(is_array($defval) ? implode(',',$defval):$defval).')'.$qextra);
							while($xta = $db->fetch_array($query))
								$xta_cache[$xta['aid']] = $xta;
							$db->free_result($query);
							unset($xta);
						}
					}
					
					xthreads_sanitize_disp_set_xta_fields($vars['ATTACH'], $aid, $tf);
					if(isset($vars['ATTACH']['md5hash'])) {
						$vars['ATTACH_MD5_TITLE'] = ' title="'.$lang->sprintf($lang->xthreads_md5hash, $vars['ATTACH']['md5hash']).'" ';
					}
					if(is_array($mybb->input['xtarm_'.$tf['field']])) {
						if($mybb->input['xtarm_'.$tf['field']][$aid])
							$vars['REMOVE_CHECKED'] = ' checked="checked"';
					} else {
						if($mybb->input['xtarm_'.$tf['field']])
							$vars['REMOVE_CHECKED'] = ' checked="checked"';
					}
					
					$vars['ITEMS'] .= $evalfunc('formhtml_item', $vars);
				}
				break;
				
			case XTHREADS_INPUT_FILE_URL: // TODO:
				break;
				
			default: // text
				$vars['VALUE'] =& $defval;
				if(!xthreads_empty($tf['multival']))
					$defval = str_replace("\n", ', ', $defval);
				break;
		}
		$tfinput[$k] = $evalfunc('formhtml', $vars);
		
		$altbg = alt_trow();
		$inputfield =& $tfinput[$k];
		eval('$tfinputrow[$k] = "'.$GLOBALS['templates']->get('post_threadfields_inputrow').'";');
		if(!($tf['hidefield'] & XTHREADS_HIDE_INPUT))
			$extra_threadfields .= $tfinputrow[$k];
	}
}

function xthreads_upload_attachments_global() {
	//if($mybb->request_method == 'post' && ($current_page == 'newthread.php' || ($current_page == 'editpost.php' && $mybb->input['action'] != 'deletepost'))
	// the above line is always checked and true
	global $mybb, $current_page, $thread;
	if($current_page == 'editpost.php') {
		// check if first post
		$pid = (int)$mybb->input['pid'];
		if(!$thread) {
			$post = get_post($pid);
			if(!empty($post))
				$thread = get_thread($post['tid']);
			if(empty($thread)) return;
			$pid = $post['pid'];
		}
		if($thread['firstpost'] != $pid)
			return;
	} elseif($mybb->input['tid']) { /* ($mybb->input['action'] == 'editdraft' || $mybb->input['action'] == 'savedraft') && */
		$thread = get_thread((int)$mybb->input['tid']);
		if($thread['visible'] != -2 || $thread['uid'] != $mybb->user['uid']) // ensure that this is, indeed, a draft
			unset($GLOBALS['thread']);
	}
	
	// permissions check - ideally, should get MyBB to do this, but I see no easy way to implement it unfortunately
	if($mybb->user['suspendposting'] == 1) return;
	if($thread['fid']) $fid = $thread['fid'];
	else $fid = (int)$mybb->input['fid'];
	$forum = get_forum($fid);
	if(!$forum['fid'] || $forum['open'] == 0 || $forum['type'] != 'f') return;
	
	$forumpermissions = forum_permissions($fid);
	if($forumpermissions['canview'] == 0) return;
	if($current_page == 'newthread.php' && $forumpermissions['canpostthreads'] == 0) return;
	elseif($current_page == 'editpost.php') {
		if(!is_moderator($fid, 'caneditposts')) {
			if($thread['closed'] == 1 || $forumpermissions['caneditposts'] == 0 || $mybb->user['uid'] != $thread['uid']) return;
			if($mybb->settings['edittimelimit'] != 0 && $thread['dateline'] < (TIME_NOW-($mybb->settings['edittimelimit']*60))) return;
		}
	}
	
	if(!verify_post_check($mybb->input['my_post_key'], true)) return;
	check_forum_password($forum['fid']);
	
	xthreads_upload_attachments();
}

function xthreads_upload_attachments() {
	global $xta_cache, $threadfield_cache, $mybb, $db, $lang, $fid;
	
	// only ever execute this function once per page
	static $done=false;
	if($done) return;
	$done = true;
	
	if(!$fid) {
		if($GLOBALS['forum']['fid'])
			$fid = $GLOBALS['forum']['fid'];
		elseif($GLOBALS['foruminfo']['fid'])
			$fid = $GLOBALS['foruminfo']['fid'];
		elseif($mybb->input['pid']) { // editpost - not good to trust user input, but should be fine
			$post = get_post((int)$mybb->input['pid']);
			if($post['pid'])
				$fid = $post['fid'];
		}
		elseif($mybb->input['fid']) // newthread
			$fid = (int)$mybb->input['fid'];
		// we _should_ now have an fid
	}
	
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($fid);
	// remove uneditable fields
	xthreads_filter_tfeditable($threadfield_cache, $fid); // NOTE: modifies the global tfcache!
	if(empty($threadfield_cache)) return;
	
	if(!is_array($xta_cache))
		$xta_cache = array();
	
	// first, run through to see if we have already uploaded some attachments
	// this code totally relies on the posthash being unique...
	if($GLOBALS['thread']['tid'])
		$attachwhere = 'tid='.(int)$GLOBALS['thread']['tid'];
	else
		$attachwhere = 'posthash="'.$db->escape_string($mybb->input['posthash']).'"';
	$query = $db->simple_select('xtattachments', '*', $attachwhere);
	$attach_fields = array();
	while($attach = $db->fetch_array($query)) {
		$xta_cache[$attach['aid']] = $attach;
		$attach_fields[$attach['field']][] = $attach['aid'];
	}
	$db->free_result($query);
	
	@ignore_user_abort(true);
	
	$errors = array();
	$xta_remove = $threadfield_updates = array();
	foreach($threadfield_cache as $k => &$v) {
		if($v['inputtype'] != XTHREADS_INPUT_FILE && $v['inputtype'] != XTHREADS_INPUT_FILE_URL) continue;
		
		$aid =& $mybb->input['xthreads_'.$k];
		if($v['inputtype'] != XTHREADS_INPUT_FILE_URL || is_numeric($mybb->input['xthreads_'.$k])) {
			$singleval = xthreads_empty($v['multival']);
			
			// now, we're ignoring what the user sends us, totally...
			if($attach_fields[$k]) {
				if($singleval)
					$aid = (int)reset($attach_fields[$k]);
				else {
					$aid = array_unique(array_map('intval', $attach_fields[$k]));
					// re-ordering support
					if(is_array($mybb->input['xtaorder'])) {
						$aid_order = array_unique(array_map('intval', $mybb->input['xtaorder']));
						if(count($aid) == count($aid_order) && $aid != $aid_order && !count(array_diff($aid, $aid_order))) {
							$aid = $aid_order;
							$threadfield_updates[$k] = implode(',', $aid);
						}
					}
					$aid = array_combine($aid, $aid);
				}
			} else
				$aid = 0;
		}
		
		
		
		// handle file upload
		$ul = null;
		if($singleval) {
			if(!empty($_FILES['xthreads_'.$k]) && !xthreads_empty($_FILES['xthreads_'.$k]['name']) && is_string($_FILES['xthreads_'.$k]['name'])) {
				$ul = $_FILES['xthreads_'.$k];
			}
			elseif($v['inputtype'] == XTHREADS_INPUT_FILE && XTHREADS_ALLOW_URL_FETCH && !xthreads_empty($mybb->input['xtaurl_'.$k])) {
				// the preg_match is just a basic prelim check - the real URL checking is done later; we need this prelim check to stop it erroring out on the defalt "http://" string
				if(preg_match('~^[a-z0-9\\-]+\\://[^/]+(?:/.*)?$~', $mybb->input['xtaurl_'.$k]))
					$ul = $mybb->input['xtaurl_'.$k];
			}
			!isset($ul) or $ul = array($ul);
		} else {
			$ul = array();
			if(is_array($mybb->input['xtaurl_'.$k])) {
				$input_urls = $mybb->input['xtaurl_'.$k];
				$input_key_match = true; // if URL input is an array, we'll match with equivalent file input keys
			} else {
				$input_urls = explode("\n", str_replace("\r", '', $mybb->input['xtaurl_'.$k]));
				$input_key_match = false;
			}
			if(!empty($_FILES['xthreads_'.$k]) && is_array($_FILES['xthreads_'.$k])) {
				foreach($_FILES['xthreads_'.$k]['name'] as $file_k => $filename) {
					if(!xthreads_empty($filename)) {
						$file_v = array(); // why does PHP does this and make our life difficult?
						foreach($_FILES['xthreads_'.$k] as $fvkey => $fvval)
							$file_v[$fvkey] = $fvval[$file_k];
						if(($input_key_match && is_numeric($file_k)) || preg_match('~^aid\d+$~', $file_k))
							$ul[$file_k] = $file_v;
						else
							$ul[] = $file_v;
					}
				}
			}
			if($v['inputtype'] == XTHREADS_INPUT_FILE && XTHREADS_ALLOW_URL_FETCH && !empty($input_urls) && is_array($input_urls)) {
				foreach($input_urls as $url_k => $url_v) {
					$url_v = trim($url_v);
					if(preg_match('~^[a-z0-9\\-]+\\://[a-z0-9_\\-@:.]+(?:/.*)?$~', $url_v)) {
						if(($input_key_match && is_numeric($url_k)) || preg_match('~^aid\d+$~', $url_k))
							isset($ul[$url_k]) or $ul[$url_k] = $url_v;
						else
							$ul[] = $url_v;
					}
				}
			}
		}
		unset($mybb->input['xtaurl_'.$k], $_FILES['xthreads_'.$k]);
		
		// remove files from list first (so we can properly measure the correct final number of attachments when uploading)
		// fix the threadfield_updates array later on
		if($singleval) {
			if(empty($ul) && $mybb->input['xtarm_'.$k] && $v['editable'] != XTHREADS_EDITABLE_REQ) {
				// user wants to remove attachment
				$xta_remove[$aid] = $aid;
				$aid = 0;
			}
		} elseif(!empty($mybb->input['xtarm_'.$k]) && is_array($mybb->input['xtarm_'.$k])) {
			foreach($mybb->input['xtarm_'.$k] as $rm_aid => $rm_confirm) {
				if(!$rm_confirm) continue; // double-check they really do want to remove this
				$xta_remove[$rm_aid] = $rm_aid;
				unset($aid[$rm_aid]);
			}
		}
		// upload new stuff
		if(!empty($ul)) {
			require_once MYBB_ROOT.'inc/xthreads/xt_upload.php';
			$update_aid = (is_array($aid) ? 0 : $aid);
			$failed_urls = array(); // list of any URLs that failed to fetch
			foreach($ul as $ul_key => $ul_file) {
				// hard limit number of files to at least 20
				if(!$singleval && is_array($aid)) {
					// hard limit
					if(strlen(implode(',', $aid)) >= 245) {
						if(!$lang->xthreads_xtaerr_error_attachhardlimit) $lang->load('xthreads');
						$errors[] = $lang->sprintf($lang->xthreads_xtaerr_error_attachhardlimit, htmlspecialchars_uni($v['title']));
						break;
					}
					// admin defined limit
					if($v['multival_limit'] && count($aid) >= $v['multival_limit']) {
						if(!$lang->xthreads_xtaerr_error_attachnumlimit) $lang->load('xthreads');
						$errors[] = $lang->sprintf($lang->xthreads_xtaerr_error_attachnumlimit, $v['multival_limit'], htmlspecialchars_uni($v['title']));
						break;
					}
				}
				
				// allow updating a specific attachment in a multi-field thing
				$update_aid2 = $update_aid;
				if(!$update_aid2 && is_array($aid) && substr($ul_key, 0, 3) == 'aid') {
					$update_aid2 = (int)substr($ul_key, 3);
					if(!in_array($update_aid2, $aid)) $update_aid2 = 0;
				}
				$attachedfile = upload_xtattachment($ul_file, $v, $mybb->user['uid'], $update_aid2, $GLOBALS['thread']['tid']);
				if($attachedfile['error']) {
					if(!$lang->xthreads_threadfield_attacherror) $lang->load('xthreads');
					$errors[] = $lang->sprintf($lang->xthreads_threadfield_attacherror, htmlspecialchars_uni($v['title']), $attachedfile['error']);
					
					if(is_string($ul_file)) $failed_urls[] = $ul_file;
				}
				else {
					//unset($attachedfile['posthash'], $attachedfile['tid'], $attachedfile['downloads']);
					
					$xta_cache[$attachedfile['aid']] = $attachedfile;
					if($singleval) {
						unset($mybb->input['xtarm_'.$k]); // since successful upload, don't tick remove box
						$aid = $attachedfile['aid'];
					} else {
						if(is_array($mybb->input['xtarm_'.$k]))
							unset($mybb->input['xtarm_'.$k][$attachedfile['aid']]);
						is_array($aid) or $aid = array(); // if no aid already set, it will be 0, so turn into array if necessary
						$aid[$attachedfile['aid']] = $attachedfile['aid'];
					}
					
					// if we were going to remove this file, don't
					if(isset($xta_remove[$attachedfile['aid']]))
						unset($xta_remove[$attachedfile['aid']]);
					
					if($attachedfile['aid'] != $update_aid2) { // adding a new attachment
						$threadfield_updates[$k] = ($singleval ? $aid:true);
					}
				}
			}
			// list failed URLs in textboxes
			if(!empty($failed_urls)) {
				$mybb->input['xtaurl_'.$k] = implode("\n", $failed_urls);
				unset($failed_urls);
			}
		}
		// fix threadfield update if removing an item and not already done
		if(!empty($xta_remove) && !isset($threadfield_updates[$k]))
			$threadfield_updates[$k] = ($singleval ? 0:true);
		// fix placeholder value
		if($threadfield_updates[$k] === true)
			$threadfield_updates[$k] = implode(',', $aid);
		unset($aid);
	}
	
	if(!empty($xta_remove)) {
		$db->delete_query('xtattachments', 'aid IN ('.implode(',',$xta_remove).')');
		foreach($xta_remove as $aid) {
			xthreads_rm_attach_fs($xta_cache[$aid]);
		}
	}
	// if editing post, also commit change to thread field immediately (waiting for user to submit is unreliable)
	if(($GLOBALS['current_page'] == 'editpost.php' || ($GLOBALS['thread']['tid'] && $GLOBALS['current_page'] == 'newthread.php')) && !empty($threadfield_updates)) {
		xthreads_db_update_replace('threadfields_data', $threadfield_updates, 'tid', $GLOBALS['thread']['tid']);
		//$db->update_query('threadfields_data', $threadfield_updates, 'tid='.$GLOBALS['thread']['tid']);
	}
	
	@ignore_user_abort(false);
	
	if(!empty($errors)) {
		// MyBB 1.4 - 1.5
		// and MyBB 1.6 is inconsistent (does different things on newthread/editpost)...
		if($mybb->version_code < 1600 || $GLOBALS['current_page'] == 'editpost.php') { // can't find a better way to check other than to check version numbers
			global $theme, $templates;
			$errstr = '<li>'.implode('</li><li>', $errors).'</li>';
			$attachedfile = array('error' => '<ul>'.$errstr.'</ul>');
			eval('$GLOBALS[\'attacherror\'] .= "'.$templates->get('error_attacherror').'";');
			// if there's going to be a MyBB attachment error, and it's not been evaluated yet, shove it in the template to force it through - safe since this function is guaranteed to run only once
			$templates->cache['error_attacherror'] = str_replace('{$attachedfile[\'error\']}', '<ul>'.strtr($errstr, array('\\' => '\\\\', '$' => '\\$', '{' => '\\{', '}' => '\\}')).'<li>{$attachedfile[\'error\']}</li></ul>', $templates->cache['error_attacherror']);
		} else {
			// for MyBB 1.6
			if(empty($GLOBALS['errors']))
				$GLOBALS['errors'] =& $errors;
			else
				$GLOBALS['errors'] = array_merge($GLOBALS['errors'], $errors);
		}
		$mybb->input['action'] = ($GLOBALS['current_page'] == 'newthread.php' ? 'newthread' : 'editpost');
		
		// block the preview, since a failed upload can stuff it up
		// lower priority to go before inputdisp function (contention, the function checks for 'previewpost')
		$GLOBALS['plugins']->add_hook('newthread_start', 'xthreads_newthread_ulattach_blockpreview', 5);
		$GLOBALS['plugins']->add_hook('editpost_start', 'xthreads_editthread_ulattach_blockpreview', 5);
	}
}
function xthreads_newthread_ulattach_blockpreview() {
	if(!$GLOBALS['thread_errors'])
		$GLOBALS['thread_errors'] = ' ';
	unset($GLOBALS['mybb']->input['previewpost']);
}
function xthreads_editthread_ulattach_blockpreview() {
	if(!$GLOBALS['post_errors'])
		$GLOBALS['post_errors'] = ' ';
	unset($GLOBALS['mybb']->input['previewpost']);
}

function xthreads_get_attach_path(&$xta) {
	static $path=null;
	if(!isset($path)) {
		$path = $GLOBALS['mybb']->settings['uploadspath'].'/xthreads_ul/';
		if(defined('IN_ADMINCP')) {
			if($path{0} != '/') $path = '../'.$path; // TODO: perhaps check for absolute Windows paths as well?  but then, who uses Windows on a production server? :>
		}
	}
	return $path.$xta['indir'].'file_'.$xta['aid'].'_'.$xta['attachname'];
}

// removes xtattachment from filesystem
function xthreads_rm_attach_fs(&$xta) {
	$name = xthreads_get_attach_path($xta);
	$path = dirname($name).'/';
	$success = true;
	// remove thumbnails
	if($thumbs = @glob(substr($name, 0, -6).'*.thumb')) {
		foreach($thumbs as &$thumb) {
			$success = $success && @unlink($path.basename($thumb));
		}
	}// else // glob _should_ succeed...
	//	$success = false;
	if(!$success) return false;
	$success = $success && @unlink($name);
	// remove month dir if possible
	if($xta['indir']) {
		$rmdir = true;
		// check for other files
		if($od = @opendir($path)) {
			while(($file = readdir($od)) !== false) {
				if($file != '.' && $file != '..' && $file != 'index.html') {
					$rmdir = false;
					break;
				}
			}
			closedir($od);
		}
		if($rmdir) {
			@unlink($path.'index.html');
			@rmdir($path);
		}
	}
	return $success;
}


function xthreads_fix_tabindexes() {
	static $done = false;
	if($done) return;
	$done = true;
	
	if($GLOBALS['current_page'] == 'editpost.php') {
		$find = '<form action="editpost.php?pid={$pid}&amp;processed=1" method="post" enctype="multipart/form-data" name="input">';
		$tpl =& $GLOBALS['templates']->cache['editpost'];
	} else {
		$find = '<form action="newthread.php?fid={$fid}&amp;processed=1" method="post" enctype="multipart/form-data" name="input">';
		$tpl =& $GLOBALS['templates']->cache['newthread'];
	}
	$p = strpos($tpl, $find);
	if(!$p) return;
	$p += strlen($find);
	if(!($p2 = strpos($tpl, '</form>', $p))) return;
	$tpl = substr($tpl, 0, $p).'<!-- __XTHREADS_FIX_TABINDEXES_START -->'.substr($tpl, $p, $p2-$p).'<!-- __XTHREADS_FIX_TABINDEXES_END -->'.substr($tpl, $p2);
	
	function xthreads_fix_tabindexes_out(&$page) {
		// find boundaries
		$p = strpos($page, '<!-- __XTHREADS_FIX_TABINDEXES_START -->');
		$plen = 40; // strlen('<!-- __XTHREADS_FIX_TABINDEXES_START -->')
		$p2 = strpos($page, '<!-- __XTHREADS_FIX_TABINDEXES_END -->', $p+$plen);
		$p2len = 38; // strlen('<!-- __XTHREADS_FIX_TABINDEXES_END -->')
		if(!$p || !$p2) return $page;
		
		// fix up tabindexes in them
		$str = substr($page, $p+$plen, $p2-$p-$plen);
		$str = preg_replace_callback('~(\<(?:input|select|textarea) [^>]*tabindex=")(__xt_)?(\d+)("[^>]*\>)~i', 'xthreads_fix_tabindexes_out_preg', $str);
		
		return substr($page, 0, $p).$str.substr($page, $p2+$p2len);
	}
	function xthreads_fix_tabindexes_out_preg($match) {
		if($match[2]) // xthreads elements
			return $match[1].$match[3].$match[4];
		$ti = (int)$match[3];
		if($ti > 1)
			return $match[1].($ti+$GLOBALS['xthreads_threadin_tabindex_shift']).$match[4];
		else // no change (eg subject input)
			return $match[0];
	}
	$GLOBALS['plugins']->add_hook('pre_output_page', 'xthreads_fix_tabindexes_out');
}

// removes the showthread_noreplies template if being used
function xthreads_js_remove_noreplies_notice() {
	global $mybb;
	if(!$mybb->input['ajax'] || $GLOBALS['visible'] != 1) return;
	// stick our Javascript into the template cache thing
	global $templates;
	// the classic postbit should _really_ be cached... - we'll write a workaround until MyBB fixes this
	if($mybb->settings['postlayout'] == 'classic' && !$templates->cache['postbit_classic'])
		$templates->cache('postbit_classic');
	// assume $templates->cache['postbit'] is already set, as it should be
	
	$js = '
<script type="text/javascript">
<!--
	if($("xthreads_noreplies")) 
		$("xthreads_noreplies").style.display = "none";
//-->
</script>
';
	$templates->cache['postbit'] = $js.$templates->cache['postbit'];
	$templates->cache['postbit_classic'] = $js.$templates->cache['postbit_classic'];
}

// because MyBB's str_ireplace workaround is buggy...
function xthreads_str_ireplace($find, $replace, $subject) {
	if(str_ireplace('a','b','A') == 'A') { // buggy workaround
		if(is_array($find)) {
			foreach($find as &$s)
				$s = '#'.preg_quote($s, '#').'#i';
		} else
			$find = '#'.preg_quote($find, '#').'#i';
		if(is_array($replace)) {
			foreach($replace as &$s)
				$s = strtr($s, array('\\' => '\\\\', '$' => '\\$'));
		} else
			$replace = strtr($replace, array('\\' => '\\\\', '$' => '\\$'));
		return preg_replace($find, $replace, $subject);
	}
	else
		return str_ireplace($find, $replace, $subject);
}


// --- some functions to fix up MyBB's bad DB methods ---
// escape function which handles NULL values properly, and enquotes strings
function xthreads_db_escape($s) {
	if($s === null) return 'NULL';
	if($s === true) return '1';
	if($s === false) return '0';
	if(is_string($s)) return '\''.$GLOBALS['db']->escape_string($s).'\'';
	return (string)$s;
}

// $db->update_query function which uses above escape method automatically
function xthreads_db_update($table, $update, $where='') {
	global $db;
	if($db->type == 'pgsql')
		$fd = '"';
	else
		$fd = '`';
	
	$sql = '';
	foreach($update as $k => &$v)
		$sql .= ($sql?', ':'').$fd.$k.$fd.'='.xthreads_db_escape($v);
	
	if($where) $sql .= ' WHERE '.$where;
	
	return $db->write_query('UPDATE '.$db->table_prefix.$table.' SET '.$sql);
}

function xthreads_db_insert($table, $insert, $replace=false) {
	global $db;
	if($db->type == 'pgsql')
		$fd = '"';
	else
		$fd = '`';
	
	$db->write_query(($replace?'REPLACE':'INSERT').' INTO '.$db->table_prefix.$table.'('.$fd.implode($fd.','.$fd, array_keys($insert)).$fd.') VALUES('.implode(',', array_map('xthreads_db_escape', $insert)).')');
	if($replace) return true;
	return $db->insert_id();
}

// emulation for replace query
// this function is NOT thread safe on non-MySQL
function xthreads_db_replace($table, $insert, $where) {
	global $db;
	if($db->type == 'mysql' || $db->type == 'mysqli')
		return xthreads_db_insert($table, $insert, true);
	
	$query = $db->simple_select($table, '*', $where, array('limit' => 1));
	$exists = $db->num_rows($query);
	$db->free_result($query);
	if($exists)
		return xthreads_db_update($table, $insert, $where);
	else
		return xthreads_db_insert($table, $insert);
}

// try to update, if unsuccessful, will run replace query
function xthreads_db_update_replace($table, $update, $idname, $idval) {
	if($GLOBALS['db']->type == 'pgsql')
		$fd = '"';
	else
		$fd = '`';
	
	$where = $fd.$idname.$fd.'='.xthreads_db_escape($idval);
	xthreads_db_update($table, $update, $where);
	if($GLOBALS['db']->affected_rows() == 0) {
		$update[$idname] = $idval;
		xthreads_db_replace($table, $update, $where);
	}
}
