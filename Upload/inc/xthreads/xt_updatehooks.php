<?php
/**
 * All update (eg newthread etc) hooks placed here to make main plugin file smaller
 */

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

// filters an input tfcache, removing items which cannot be modified by current user
function xthreads_filter_tfeditable(&$tf, $fid=0) {
	foreach($tf as $k => &$v) {
		if(!empty($v['editable_gids'])) {
			$editable = false;
			if(!isset($ingroups))
				$ingroups = xthreads_get_user_usergroups($GLOBALS['mybb']->user);
			foreach($v['editable_gids'] as $gid)
				if(isset($ingroups[$gid])) {
					$editable = true;
					break;
				}
			if(!$editable)
				unset($tf[$k]);
		}
		elseif(($v['editable'] == XTHREADS_EDITABLE_MOD && !is_moderator($fid)) ||
		   ($v['editable'] == XTHREADS_EDITABLE_ADMIN && $GLOBALS['mybb']->usergroup['cancp'] != 1) ||
		   ($v['editable'] == XTHREADS_EDITABLE_NONE))
			unset($tf[$k]);
	}
}


function xthreads_input_posthandler_postvalidate(&$ph) {
	// determine if first post
	$pid = intval($ph->data['pid']);
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
	$errors = xthreads_input_validate($data, $threadfield_cache, $update);
	foreach($errors as &$error)
		call_user_func_array(array($ph, 'set_error'), $error);
	
	foreach($data as $k => &$v)
		$ph->data['xthreads_'.$k] = $v;
}
function xthreads_input_validate(&$data, &$threadfield_cache, $update=false) {
	global $mybb;
	
	$errors = array();
	// set things from input
	foreach($threadfield_cache as $k => &$v) {
		if($v['editable'] == XTHREADS_EDITABLE_NONE) continue;
		
		if(isset($mybb->input['xthreads_'.$k])) {
			if($v['inputtype'] == XTHREADS_INPUT_FILE) {
				$inval = intval($mybb->input['xthreads_'.$k]);
			}
			elseif($v['inputtype'] == XTHREADS_INPUT_FILE_URL) {
				if(is_numeric($mybb->input['xthreads_'.$k]))
					$inval = intval($mybb->input['xthreads_'.$k]);
				else
					$inval = trim($mybb->input['xthreads_'.$k]);
			}
			elseif($v['multival'] && (
					($input_is_array = is_array($mybb->input['xthreads_'.$k])) || ($v['inputtype'] == XTHREADS_INPUT_TEXT || $v['inputtype'] == XTHREADS_INPUT_TEXTAREA)
			)) {
				if(!$input_is_array)
					$mybb->input['xthreads_'.$k] = explode(($v['inputtype'] == XTHREADS_INPUT_TEXTAREA ? "\n":','), str_replace("\r", '', $mybb->input['xthreads_'.$k]));
				$inval = array_unique(array_map('trim', $mybb->input['xthreads_'.$k]));
				foreach($inval as $valkey => &$val)
					if(xthreads_empty($val)) unset($inval[$valkey]);
			}
			else
				$inval = trim($mybb->input['xthreads_'.$k]);
		}
		else {
			$inval = null;
			if($update) continue;
		}
		
		if($v['editable'] == XTHREADS_EDITABLE_REQ && (!isset($inval) || xthreads_empty($inval))) {
			$errors[] = array('threadfield_required', htmlspecialchars_uni($v['title']));
		}
		elseif(isset($inval)) {
			if($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) {
				// TODO: perhaps have URL validation here (for type FILE_URL)
				$data[$k] = $inval;
			}
			else {
				// validate input
				if(is_array($inval))
					$inval_list =& $inval;
				else
					$inval_list = array($inval); // &$inval generates recursion for some odd reason at times
				foreach($inval_list as &$val) {
					if(xthreads_empty($val)) continue; // means that if the field wasn't set and isn't a necessary field, ignore it
					if($v['maxlen'] && my_strlen($val) > $v['maxlen']) {
						$errors[] = array('threadfield_toolong', array(htmlspecialchars_uni($v['title']), $v['maxlen']));
						break;
					}
					elseif(!empty($v['vallist'])) {
						if(!in_array($val, $v['vallist'])) {
							$errors[] = array('threadfield_invalidvalue', htmlspecialchars_uni($v['title']));
							break;
						}
					}
					// we'll apply datatype restrictions before testing textmask
					elseif($v['textmask'] && !preg_match('~'.str_replace('~', '\\~', $v['textmask']).'~si', xthreads_convert_str_to_datatype($val, $v['datatype']))) {
						$errors[] = array('threadfield_invalidvalue', htmlspecialchars_uni($v['title']));
						break;
					}
				}
				
				if(is_array($inval))
					$data[$k] = implode("\n", $inval);
				else
					$data[$k] = $inval;
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
		if(isset($ph->data['xthreads_'.$k])) {
			if(($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) && is_numeric($ph->data['xthreads_'.$k]))
				$xtaupdates[] = $ph->data['xthreads_'.$k];
			
			if($v['datatype'] != XTHREADS_DATATYPE_TEXT && $ph->data['xthreads_'.$k] === '')
				$updates[$k] = null;
			else
				$updates[$k] = xthreads_convert_str_to_datatype($ph->data['xthreads_'.$k], $v['datatype']);
		}
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
	switch($type) {
		case XTHREADS_DATATYPE_TEXT:
			return $s;
		case XTHREADS_DATATYPE_INT:
		case XTHREADS_DATATYPE_BIGINT:
			return intval($s);
		case XTHREADS_DATATYPE_UINT:
		case XTHREADS_DATATYPE_BIGUINT:
			return (int)abs(intval($s));
		case XTHREADS_DATATYPE_FLOAT:
			return doubleval($s);
	}
}

function xthreads_delete_thread($tid) {
	global $db;
	// awesome thing about this is that it will delete threadfields even if the thread was moved to a different forum
	$db->delete_query('threadfields_data', 'tid='.$tid);
	
	xthreads_rm_attach_query('tid='.$tid);
}

function xthreads_copy_thread(&$a) {
	control_object($GLOBALS['db'], '
		function insert_query($table, $array) {
			static $done = false;
			$ret = parent::insert_query($table, $array);
			if(!$done) {
				$done = true;
				xthreads_duplicate_threadfield_data($this->xthreads_copy_thread_tid, $ret);
			}
			return $ret;
		}
	');
	$GLOBALS['db']->xthreads_copy_thread_tid = $a['tid'];
}
/* function xthreads_split_posts(&$a) {
	// impossible to get the new tid!!
	// or maybe there's a way...
} */

function xthreads_duplicate_threadfield_data($tid_old, $tid_new) {
	global $db, $mybb;
	@ignore_user_abort(true); // not really that good, since cancelling elsewhere will break transaction, but, well, copies might be slow, so...
	$tf = $db->fetch_array($db->simple_select('threadfields_data', '*', 'tid='.$tid_old));
	if(empty($tf)) { // no threadfields set for this thread -> nothing to duplicate
		@ignore_user_abort(false);
		return;
	}
	$tf['tid'] = $tid_new;
	
	// copy xtattachments over
	$query = $db->simple_select('xtattachments', '*', 'tid='.$tid_old);
	while($xta = $db->fetch_array($query)) {
		// we have a file we need to duplicate
		$xta['tid'] = $tid_new;
		$oldname = xthreads_get_attach_path($xta);
		$oldpath = dirname($oldname).'/';
		$xta['attachname'] = substr(md5(uniqid(mt_rand(), true).substr($mybb->post_code, 16)), 12, 8).substr($xta['attachname'], 8);
		unset($xta['aid']);
		$tf[$xta['field']] = $xta['aid'] = xthreads_db_insert('xtattachments', $xta);
		
		$newname = xthreads_get_attach_path($xta);
		$newpath = dirname($newname).'/';
		
		$oldfpref = basename(substr($oldname, 0, -6));
		$newfpref = basename(substr($newname, 0, -6));
		if($thumbs = @glob($oldpath.$oldfpref.'*x*.thumb')) {
			foreach($thumbs as &$thumb) {
				$thumb = basename($thumb);
				xthreads_hardlink_file($oldpath.$thumb, $newpath.str_replace($oldfpref, $newfpref, $thumb));
			}
		}
		xthreads_hardlink_file($oldname, $newname);
	}
	
	xthreads_db_insert('threadfields_data', $tf);
	@ignore_user_abort(false);
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
			$post = get_post(intval($mybb->input['pid'])); // hopefully MyBB will also use get_post in their code too...
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
		_xthreads_input_generate($recvfields, $fid);
	}
	elseif($editpost || ($mybb->input['action'] == 'editdraft' && $thread['tid'])) {
		global $db;
		$fields = $db->fetch_array($db->simple_select('threadfields_data', '*', 'tid='.$thread['tid']));
		_xthreads_input_generate($fields, $fid);
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
				$pid = intval($mybb->input['pid']);
				$post = get_post($pid);
				$fid = $post['fid'];
			}
			$forum = get_forum($fid);
		}
		
		$threadfields = array();
		$threadfield_cache = xthreads_gettfcache($fid); // don't use global cache as that will probably have been cleared of uneditable fields
		$errors = xthreads_input_validate($threadfields, $threadfield_cache, $editpost);
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
					
					foreach($GLOBALS['xthreads_preview_errors'] as &$error)
						call_user_func_array(array($posthandler, 'set_error'), $error);
					
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
			$mybb->input['message'] = str_repeat('-', max(intval($mybb->settings['minmessagelength']), 1));
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
// be a little pedantic and delete any xtattachments which has the same posthash as the one selected
// should be very rare, but we'll be extra careful
// also can potentially be problematic too, but deleting an attachment not abandoned is perhaps even rarer
function xthreads_attach_clear_posthash() {
	if(mt_rand(0, 10) > 1) return; // dirty hack to speed things up a little
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
function _xthreads_input_generate(&$data, $fid) {
	global $threadfield_cache;
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($fid);
	xthreads_filter_tfeditable($threadfield_cache, $fid); // NOTE: modifies the global tfcache!
	xthreads_input_generate($data, $threadfield_cache, $fid);
}

function xthreads_input_generate(&$data, &$threadfields, $fid) {
	global $tfinput, $tfinputrow, $extra_threadfields, $lang, $xthreads_threadin_tabindex_shift;
	if(!$lang->xthreads_attachfile) $lang->load('xthreads');
	
	$tfinput = $tfinputrow = array();
	$extra_threadfields = '';
	foreach($threadfields as $k => $tf) {
		$tf['title'] = htmlspecialchars_uni($tf['title']);
		$tf['field'] = htmlspecialchars_uni($tf['field']);
		$tf['desc'] = htmlspecialchars_uni($tf['desc']);
		$maxlen = '';
		if($tf['maxlen'])
			$maxlen = ' maxlength="'.intval($tf['maxlen']).'"';
		$tfname = ' name="xthreads_'.$tf['field'].'"';
		
		$tf_fw_style = $tf_fw_size = $tf_fw_cols = $tf_fh = '';
		if($tf['fieldwidth']) {
			$tf_fw_size = ' size="'.intval($tf['fieldwidth']).'"';
			$tf_fw_style = ' style="width: '.(intval($tf['fieldwidth'])/2).'em;"'; // only used for select box [in Firefox, seems we need to divide by 2 to get the equivalent width]
			$tf_fw_cols = ' cols="'.intval($tf['fieldwidth']).'"';
		}
		
		$using_default = false;
		if(!isset($data)) // no threadfield data set for this thread
			$defval = '';
		elseif(isset($data[$k]))
			$defval = $data[$k];
		else {
			$defval = eval_str($tf['defaultval']);
			// we don't want $defval to be an array for textual inputs, so split it later
			$using_default = true;
		}
		
		unset($defvals);
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_SELECT:
			case XTHREADS_INPUT_RADIO:
			case XTHREADS_INPUT_CHECKBOX:
				$vals = array_map('htmlspecialchars_uni', $tf['vallist']);
				if(!xthreads_empty($tf['multival'])) {
					if($using_default)
						$defval = explode("\n", str_replace("\r", '', $defval));
					if(is_array($defval))
						$defvals =& $defval;
					else
						$defvals = explode("\n", str_replace("\r", '', $defval));
					$defvals = array_map('htmlspecialchars_uni', $defvals);
				}
				// give blank option if none is actually required
				elseif($tf['editable'] != XTHREADS_EDITABLE_REQ && $tf['inputtype'] != XTHREADS_INPUT_CHECKBOX)
					array_unshift($vals, '');
		}
		if(!isset($defvals) && ($tf['inputtype'] != XTHREADS_INPUT_FILE && $tf['inputtype'] != XTHREADS_INPUT_FILE_URL))
			$defval = htmlspecialchars_uni($defval);
		
		$tabindex = '';
		if($tf['tabstop']) {
			++$xthreads_threadin_tabindex_shift;
			$tabindex = ' tabindex="__xt_'.($xthreads_threadin_tabindex_shift+1).'"';
			xthreads_fix_tabindexes();
		}
		
		$tfinput[$k] = '';
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_TEXTAREA:
				if($tf['fieldheight'])
					$tf_fh = ' rows="'.intval($tf['fieldheight']).'"';
				$tfinput[$k] = '<textarea'.$tfname.$maxlen.$tf_fh.$tf_fw_cols.$tabindex.'>'.$defval.'</textarea>';
				break;
			case XTHREADS_INPUT_SELECT:
				if($tf['fieldheight'])
					$tf_fh = ' size="'.intval($tf['fieldheight']).'"';
				elseif(!xthreads_empty($tf['multival']))
					$tf_fh = ' size="5"';
				$tfinput[$k] = '<select name="xthreads_'.$tf['field'].(!xthreads_empty($tf['multival']) ? '[]" multiple="multiple"':'"').$tf_fh.$tf_fw_style.$tabindex.'>';
				foreach($vals as &$val) {
					$selected = ((isset($defvals) && in_array($val, $defvals)) || $defval === $val ? ' selected="selected"':'');
					if(xthreads_empty($val) && $tf['editable'] != XTHREADS_EDITABLE_REQ)
						$tfinput[$k] .= '<option value="" style="font-style: italic;"'.$selected.'>'.$lang->xthreads_val_blank.'</option>';
					else
						$tfinput[$k] .= '<option value="'.$val.'"'.$selected.'>'.$val.'</option>';
				}
				$tfinput[$k] .= '</select>';
				break;
			case XTHREADS_INPUT_CHECKBOX:
				$tfname = ' name="xthreads_'.$tf['field'].'[]"';
				// fall through
			case XTHREADS_INPUT_RADIO:
				$tftype = ($tf['inputtype'] == XTHREADS_INPUT_RADIO ? 'radio':'checkbox');
				foreach($vals as &$val) {
					$checked = ((isset($defvals) && in_array($val, $defvals)) || $defval === $val ? ' checked="checked"':'');
					if(xthreads_empty($val) && $tf['editable'] != XTHREADS_EDITABLE_REQ)
						$tfinput[$k] .= '<label style="display: block; font-style: italic;"><input'.$tfname.' type="'.$tftype.'" class="'.$tftype.'" value=""'.$checked.$tabindex.' />'.$lang->xthreads_val_blank.'</label>';
					else
						$tfinput[$k] .= '<label style="display: block;"><input'.$tfname.' type="'.$tftype.'" class="'.$tftype.'" value="'.$val.'"'.$checked.$tabindex.' />'.unhtmlentities($val).'</label>';
					$tabindex = ''; // or maybe make each thing tabbable?
				}
				break;
			case XTHREADS_INPUT_FILE:
				$tfinput[$k] = '';
				$jsext = '';
				if($defval) {
					if(is_numeric($defval)) {
						global $xta_cache, $db;
						if(!isset($xta_cache[$defval])) {
							static $done_xta_cache = false;
							// need to cache them
							if(!$done_xta_cache) {
								$done_xta_cache = true;
								$qextra = '';
								if($GLOBALS['mybb']->input['posthash'])
									$qextra .= ' OR posthash="'.$db->escape_string($GLOBALS['mybb']->input['posthash']).'"';
								if($GLOBALS['thread']['tid'])
									$qextra .= ' OR tid='.$GLOBALS['thread']['tid'];
								$query = $db->simple_select('xtattachments', '*', 'aid='.$defval.$qextra);
								while($xta = $db->fetch_array($query))
									$xta_cache[$xta['aid']] = $xta;
								$db->free_result($query);
							}
						}
						$this_xta =& $xta_cache[$defval];
						$md5title = '';
						$url = xthreads_get_xta_url($this_xta);
						if(isset($this_xta['md5hash'])) {
							$md5hash = bin2hex($this_xta['md5hash']);
							$md5title = 'title="'.$lang->sprintf($lang->xthreads_md5hash, $md5hash).'" ';
						}
						// <input type="hidden"'.$tfname.' value="'.$defval.'" />
						$tfinput[$k] = '<div><span '.$md5title.'id="xtaname_'.$tf['field'].'"><a href="'.$url.'" target="_blank">'.htmlspecialchars_uni($this_xta['filename']).'</a> ('.get_friendly_size($this_xta['filesize']).')</span>';
						if($GLOBALS['mybb']->input['xtarm_'.$tf['field']])
							$rmcheck = ' checked="checked"';
						else
							$rmcheck = '';
						if($tf['editable'] != XTHREADS_EDITABLE_REQ) {
							$tfinput[$k] .= ' <label id="xtarmlabel_'.$tf['field'].'"><input type="checkbox" id="xtarm_'.$tf['field'].'" name="xtarm_'.$tf['field'].'" value="1"'.$rmcheck.' />'.$lang->xthreads_rmattach.'</label>';
						} else {
							// javascript checkbox
							$tfinput[$k] .= ' <label id="xtarmlabel_'.$tf['field'].'" style="display: none;"><input type="checkbox" id="xtarm_'.$tf['field'].'" name="xtarm_'.$tf['field'].'" value="1"'.$rmcheck.' />'.$lang->xthreads_replaceattach.'</label>';
						}
						$tfinput[$k] .= '</div>';
						$jsext .= '($("xtarm_'.$tf['field'].'").onclick = function() {
							var c=$("xtarm_'.$tf['field'].'").checked;
							$("xtarow_'.$tf['field'].'").style.display = (c?"":"none");
							$("xtaname_'.$tf['field'].'").style.textDecoration = (c?"line-through":"");
						})();
						$("xtarmlabel_'.$tf['field'].'").style.display="";';
					}
				}
				
				$fileinput = '<input type="file" class="fileupload"'.$tfname.$tf_fw_size.$tabindex.' id="xthreads_'.$tf['field'].'" />';
				if(XTHREADS_ALLOW_URL_FETCH) {
					// TODO: test if this environment can really fetch URLs
					// no =& because we change $input_url potentially
					$input_url = $GLOBALS['mybb']->input['xtaurl_'.$tf['field']];
					if(xthreads_empty($input_url)) $input_url = 'http://';
					if($input_url != 'http://' || $GLOBALS['mybb']->input['xtasel_'.$tf['field']] == 'url') {
						$check_file = '';
						$check_url = ' checked="checked"';
					} else {
						$check_file = ' checked="checked"';
						$check_url = '';
					}
					
					$fileinput = '<div style="display: none; font-size: x-small;" id="xtasel_'.$tf['field'].'"><label style="margin: 0 0.6em;"><input type="radio" name="xtasel_'.$tf['field'].'" value="file"'.$check_file.' id="xtaselopt_file_'.$tf['field'].'" />'.$lang->xthreads_attachfile.'</label><label style="margin: 0 0.6em;"><input type="radio" name="xtasel_'.$tf['field'].'" value="url"'.$check_url.' id="xtaselopt_url_'.$tf['field'].'" />'.$lang->xthreads_attachurl.'</label></div>
					<div><span id="xtaseltext_file_'.$tf['field'].'">'.$lang->xthreads_attachfile.': </span>'.$fileinput.'</div>
					<div><span id="xtaseltext_url_'.$tf['field'].'">'.$lang->xthreads_attachurl.': </span><input type="text" class="textbox" id="xtaurl_'.$tf['field'].'" name="xtaurl_'.$tf['field'].'"'.$tf_fw_size.' value="'.htmlspecialchars($input_url).'" /></div>';
					$jsext .= '
						$("xtasel_'.$tf['field'].'").style.display="";
						$("xtaseltext_file_'.$tf['field'].'").style.display=$("xtaseltext_url_'.$tf['field'].'").style.display="none";
						($("xtaselopt_file_'.$tf['field'].'").onclick = $("xtaselopt_url_'.$tf['field'].'").onclick = function() {
							var f=$("xtaselopt_file_'.$tf['field'].'").checked;
							$("xthreads_'.$tf['field'].'").style.display = (f?"":"none");
							$("xtaurl_'.$tf['field'].'").style.display = (f?"none":"");
							if(!f) $("xthreads_'.$tf['field'].'").value = "";
						})();
					';
				}
				
				$tfinput[$k] .= '<div id="xtarow_'.$tf['field'].'">'.$fileinput.'</div>';
				if($jsext) {
					$tfinput[$k] .= '<script type="text/javascript"><!--
					'.$jsext.'
					//-->
					</script>';
				}
				break;
				
			case XTHREADS_INPUT_FILE_URL: // TODO:
				break;
				
			case XTHREADS_INPUT_CUSTOM:
				$tfinput[$k] = preg_replace('~\\{\\$([a-zA-Z_0-9]+)((-\\>[a-zA-Z_0-9]+|\\[[\'"]?[a-zA-Z_ 0-9]+[\'"]?\\])*)\\}~e', 'eval("return \\\\$$1".str_replace("\\\\\'", "\'", "$2").";")', $tf['formhtml']);
				break;
				
			default: // text
				if(!xthreads_empty($tf['multival']))
					$defval = str_replace("\n", ', ', $defval);
				$tfinput[$k] = '<input type="text" class="textbox"'.$tfname.$maxlen.$tf_fw_size.$tabindex.' value="'.$defval.'" />';
				break;
		}
		
		$altbg = alt_trow();
		$inputfield =& $tfinput[$k];
		eval('$tfinputrow[$k] = "'.$GLOBALS['templates']->get('threadfields_inputrow').'";');
		if(!$tf['hideedit'])
			$extra_threadfields .= $tfinputrow[$k];
	}
}

function xthreads_upload_attachments_global() {
	//if($mybb->request_method == 'post' && ($current_page == 'newthread.php' || ($current_page == 'editpost.php' && $mybb->input['action'] != 'deletepost'))
	// the above line is always checked and true
	global $mybb, $current_page, $thread;
	if($current_page == 'editpost.php') {
		// check if first post
		$pid = intval($mybb->input['pid']);
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
		$thread = get_thread(intval($mybb->input['tid']));
		if($thread['visible'] != -2 || $thread['uid'] != $mybb->user['uid']) // ensure that this is, indeed, a draft
			unset($GLOBALS['thread']);
	}
	
	// permissions check - ideally, should get MyBB to do this, but I see no easy way to implement it unfortunately
	if($mybb->user['suspendposting'] == 1) return;
	if($thread['fid']) $fid = $thread['fid'];
	else $fid = intval($mybb->input['fid']);
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
			$post = get_post(intval($mybb->input['pid']));
			if($post['pid'])
				$fid = $post['fid'];
		}
		elseif($mybb->input['fid']) // newthread
			$fid = intval($mybb->input['fid']);
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
		$attachwhere = 'tid='.intval($GLOBALS['thread']['tid']);
	else
		$attachwhere = 'posthash="'.$db->escape_string($mybb->input['posthash']).'"';
	$query = $db->simple_select('xtattachments', '*', $attachwhere);
	$attach_fields = array();
	while($attach = $db->fetch_array($query)) {
		$xta_cache[$attach['aid']] = $attach;
		$attach_fields[$attach['field']] = $attach['aid'];
	}
	$db->free_result($query);
	
	@ignore_user_abort(true);
	
	$errors = array();
	$xta_remove = $threadfield_updates = array();
	foreach($threadfield_cache as $k => &$v) {
		if($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) {
			$aid =& $mybb->input['xthreads_'.$k];
			if($v['inputtype'] != XTHREADS_INPUT_FILE_URL || is_numeric($mybb->input['xthreads_'.$k])) {
				
				// now, we're ignoring what the user sends us, totally...
				if($attach_fields[$k])
					$aid = $attach_fields[$k];
				else
					$aid = 0;
			}
			
			
			
			// handle file upload
			$ul = null;
			if(!empty($_FILES['xthreads_'.$k]) && !xthreads_empty($_FILES['xthreads_'.$k]['name'])) {
				$ul =& $_FILES['xthreads_'.$k];
				if($mybb->input['xtaurl_'.$k])
					unset($mybb->input['xtaurl_'.$k]);
			}
			elseif($v['inputtype'] == XTHREADS_INPUT_FILE && XTHREADS_ALLOW_URL_FETCH && !xthreads_empty($mybb->input['xtaurl_'.$k])) {
				// the preg_match is just a basic prelim check - the real URL checking is done later; we need this prelim check to stop it erroring out on the defalt "http://" string
				if(preg_match('~^[a-z0-9\\-]+\\://[a-z0-9_\\-@:.]+(?:/.*)?$~', $mybb->input['xtaurl_'.$k]))
					$ul = $mybb->input['xtaurl_'.$k];
				else
					unset($mybb->input['xtaurl_'.$k]);
			}
			
			if(isset($ul)) {
				require_once MYBB_ROOT.'inc/xthreads/xt_upload.php';
				$attachedfile = upload_xtattachment($ul, $v, $mybb->user['uid'], $aid, $GLOBALS['thread']['tid']);
				if($attachedfile['error']) {
					if(!$lang->xthreads_threadfield_attacherror) $lang->load('xthreads');
					$errors[] = $lang->sprintf($lang->xthreads_threadfield_attacherror, htmlspecialchars_uni($v['title']), $attachedfile['error']);
				}
				else {
					//unset($attachedfile['posthash'], $attachedfile['tid'], $attachedfile['downloads']);
					
					$xta_cache[$attachedfile['aid']] = $attachedfile;
					if($mybb->input['xtaurl_'.$k])
						unset($mybb->input['xtaurl_'.$k]);
					if($mybb->input['xtarm_'.$k]) // since successful upload, don't tick remove box
						unset($mybb->input['xtarm_'.$k]);
					
					if($attachedfile['aid'] != $aid) { // adding a new attachment
						$aid = $attachedfile['aid'];
						$threadfield_updates[$k] = $aid;
					}
				}
				unset($_FILES['xthreads_'.$k]);
			}
			elseif($mybb->input['xtarm_'.$k] == '1' && $v['editable'] != XTHREADS_EDITABLE_REQ) {
				// user wants to remove attachment
				$xta_remove[$k] = $aid;
				$threadfield_updates[$k] = 0;
			}
		}
	}
	
	if(!empty($xta_remove)) {
		$db->delete_query('xtattachments', 'aid IN ('.implode(',',$xta_remove).')');
		foreach($xta_remove as $k => $aid) {
			xthreads_rm_attach_fs($xta_cache[$aid]);
			$mybb->input['xthreads_'.$k] = 0;
			//unset($mybb->input['xthreads_'.$k]);
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
	if($thumbs = @glob(substr($name, 0, -6).'*x*.thumb')) {
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
function xthreads_rm_attach_query($where) {
	global $db;
	$has_attach = $successes = 0;
	$query = $db->simple_select('xtattachments', 'aid,indir,attachname', $where);
	$rmaid = '';
	while($xta = $db->fetch_array($query)) {
		if(xthreads_rm_attach_fs($xta)) {
			if($successes) $rmaid .= ',';
			$rmaid .= $xta['aid'];
			++$successes;
		}
		++$has_attach;
	}
	$db->free_result($query);
	if($has_attach) {
		if($has_attach == $successes)
			$db->delete_query('xtattachments', $where);
		elseif($successes)
			$db->delete_query('xtattachments', 'aid IN ('.$rmaid.')');
	}
	return $successes;
}

// will try to create a hardlink/copy of a file
function xthreads_hardlink_file($src, $dest) {
	if($src == $dest) return false;
	if(@link($src, $dest)) return true;
	if(DIRECTORY_SEPARATOR == '\\' && @ini_get('safe_mode') != 'On') {
		$allow_exec = true;
		// check if exec() is allowed
		if(($func_blacklist = @ini_get('suhosin.executor.func.blacklist')) && strpos(','.$func_blacklist.',', ',exec,') !== false)
			$allow_exec = false;
		if(($func_blacklist = @ini_get('disable_functions')) && strpos(','.$func_blacklist.',', ',exec,') !== false)
			$allow_exec = false;
		
		if($allow_exec) {
			// try mklink (Windows Vista / Server 2008 and later only)
			// assuming mklink refers to the correct executable is a little dangerous perhaps, but it should work
			@unlink($dest); // mklink won't overwrite
			@exec('mklink /H '.escapeshellarg(str_replace('/', '\\', $src)).' '.escapeshellarg(str_replace('/', '\\', $dest)).' >NUL 2>NUL', $null, $ret);
			if($ret==0 && @file_exists($dest)) return true;
		}
	}
	// fail, resort to copy
	return @copy($src, $dest);
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
		$ti = intval($match[3]);
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

function xthreads_moderation() {
	// try to hook into custom moderation
	// lovely MyBB provides no custom moderation hook, what gives?
	$modactions = array(
		'openclosethread',
		'stick',
		'removeredirects',
		'deletethread',
		'do_deletethread',
		'deletepoll',
		'do_deletepoll',
		'approvethread',
		'unapprovethread',
		'deleteposts',
		'do_deleteposts',
		'mergeposts',
		'do_mergeposts',
		'move',
		'do_move',
		'threadnotes',
		'do_threadnotes',
		'getip',
		'merge',
		'do_merge',
		'split',
		'do_split',
		'removesubscriptions',
		'multideletethreads',
		'do_multideletethreads',
		'multiopenthreads',
		'multiclosethreads',
		'multiapprovethreads',
		'multiunapprovethreads',
		'multistickthreads',
		'multiunstickthreads',
		'multimovethreads',
		'do_multimovethreads',
		'multideleteposts',
		'do_multideleteposts',
		'multimergeposts',
		'do_multimergeposts',
		'multisplitposts',
		'do_multisplitposts',
		'multiapproveposts',
		'multiunapproveposts',
	);
	if($GLOBALS['mybb']->version_code >= 1500) {
		$modactions[] = 'cancel_delayedmoderation';
		$modactions[] = 'do_delayedmoderation';
		$modactions[] = 'delayedmoderation';
	}
	if(in_array($GLOBALS['mybb']->input['action'], $modactions)) return;
	
	// we are probably now looking at custom moderation - let's get ourselves a hook into the system
	control_object($GLOBALS['db'], '
		function simple_select($table, $fields="*", $conditions="", $options=array()) {
			static $done=false;
			if(!$done && $table == "modtools" && $fields == "tid, type, name, description" && substr($conditions, 0, 4) == "tid=" && empty($options)) {
				$done = true;
				xthreads_moderation_custom();
			}
			return parent::simple_select($table, $fields, $conditions, $options);
		}
	');
}

function xthreads_moderation_custom() {
	//if($tool['type'] != 't') return;
	if(!is_object($GLOBALS['custommod'])) return;
	
	control_object($GLOBALS['custommod'], '
		function execute_thread_moderation($thread_options, $tids) {
			if($thread_options[\'deletethread\'] != 1 && !xthreads_empty($thread_options[\'edit_threadfields\']))
				xthreads_moderation_custom_do($tids, $thread_options[\'edit_threadfields\']);
			return parent::execute_thread_moderation($thread_options, $tids);
		}
	');
	
	// this function is executed before copy thread (yay!)
	function xthreads_moderation_custom_do(&$tids, $editstr) {
		$edits = array();
		
		// caching stuff
		static $threadfields = null;
		if(!isset($threadfields))
			$threadfields = xthreads_gettfcache(); // grab all threadfields
		
		foreach(explode("\n", str_replace("\r",'',$editstr)) as $editline) {
			$editline = trim($editline);
			list($n, $v) = explode('=', $editline, 2);
			if(!isset($v)) continue;
			
			// don't allow editing of file fields
			if(!isset($threadfields[$n]) || $threadfields[$n]['inputtype'] == XTHREADS_INPUT_FILE) continue;
			// we don't do much validation here as we trust admins, right?
			
			$upperv = strtoupper($v);
			if($upperv != '{VALUE}') {
				if(($upperv === '' || $upperv == 'NULL' || $upperv == 'NUL') && $threadfields[$n]['datatype'] != XTHREADS_DATATYPE_TEXT)
					$edits[$n] = null;
				else
					$edits[$n] = $v;
			}
		}
		if(empty($edits)) return;
		$modfields = array_keys($edits);
		
		global $db;
		$query = $db->query('
			SELECT t.tid, tfd.`'.implode('`, tfd.`', $modfields).'`
			FROM '.TABLE_PREFIX.'threads t
			LEFT JOIN '.TABLE_PREFIX.'threadfields_data tfd ON t.tid=tfd.tid
			WHERE t.tid IN ('.implode(',', $tids).')
		');
		//$query = $db->simple_select('threadfields_data', 'tid,`'.implode('`,`', $modfields).'`', 'tid IN ('.implode(',', $tids).')');
		while($thread = $db->fetch_array($query)) {
			$updates = array();
			foreach($edits as $n => $v) {
				// TODO: support variables/conditionals?
				if($v !== null)
					$v = trim(xthreads_str_ireplace(array('{value}', '{tid}'), array($thread[$n], $thread['tid']), $v));
				if($v !== $thread[$n]) {
					// we'll do some basic validation for multival fields
					if(!xthreads_empty($threadfields[$n]['multival'])) {
						$d = "\n";
						if($threadfields[$n]['inputtype'] == XTHREADS_INPUT_TEXT)
							$d = ',';
						$v = array_unique(array_map('trim', explode($d, str_replace("\r", '', $v))));
						foreach($v as $key => &$val)
							if(xthreads_empty($val))
								unset($v[$key]);
						$v = implode($d, $v);
					}
					$updates[$n] = $v;
				}
			}
			if(!empty($updates)) {
				xthreads_db_update_replace('threadfields_data', $updates, 'tid', $thread['tid']);
			}
		}
		$db->free_result($query);
	}
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
	$where = '`'.$idname.'`='.xthreads_db_escape($idval);
	xthreads_db_update($table, $update, $where);
	if($GLOBALS['db']->affected_rows() == 0) {
		$update[$idname] = $idval;
		xthreads_db_replace($table, $update, $where);
	}
}
