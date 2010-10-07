<?php
/**
 * All update (eg newthread etc) hooks placed here to make main plugin file smaller
 */

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

// filters an input tfcache, removing items which cannot be modified by current user
function xthreads_filter_tfeditable(&$tf, $fid=0) {
	$ug =& $GLOBALS['mybb']->usergroup;
	foreach($tf as $k => &$v) {
		if($v['editable_gids']) {
			if(strpos(','.$v['editable_gids'].',', ','.$ug['gid'].',') === false)
				unset($tf[$k]);
		}
		elseif(($v['editable'] == XTHREADS_EDITABLE_MOD && !is_moderator($fid)) ||
		   ($v['editable'] == XTHREADS_EDITABLE_ADMIN && $ug['cancp'] != 1) ||
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
	global $threadfield_cache, $fid, $lang, $forum;
	
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
	
	if(!$fid) {
		global $thread, $post, $foruminfo;
		if($forum['fid']) $fid = $forum['fid'];
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
	
	//xthreads_upload_attachments();
	
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
			elseif($v['multival'] && is_array($mybb->input['xthreads_'.$k])) {
				$inval = array_unique(array_map('trim', $mybb->input['xthreads_'.$k]));
				foreach($inval as $valkey => &$val)
					if(!$val) unset($inval[$valkey]);
			}
			else
				$inval = trim($mybb->input['xthreads_'.$k]);
		}
		else {
			$inval = null;
			if($update) continue;
		}
		
		if($v['editable'] == XTHREADS_EDITABLE_REQ && (!isset($inval) || empty($inval))) {
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
					if(!$val) continue; // means that if the field wasn't set and isn't a necessary field, ignore it
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
					elseif($v['textmask'] && !preg_match('~'.str_replace('~', '\\~', $v['textmask']).'~si', $val)) {
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
	
	
	global $threadfield_cache, $fid, $db;
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($fid);
	if(empty($threadfield_cache)) return;
	
	$updates = array();
	$xtaupdates = array();
	foreach($threadfield_cache as $k => &$v) {
		if(isset($ph->data['xthreads_'.$k])) {
			if(($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) && is_numeric($ph->data['xthreads_'.$k]))
				$xtaupdates[] = $ph->data['xthreads_'.$k];
			
			$updates[$k] = $db->escape_string($ph->data['xthreads_'.$k]);
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
		$db->update_query('threadfields_data', $updates, 'tid='.$tid);
		// check if actually updated (it may be possible that an entry for this thread isn't added yet)
		if($db->affected_rows() > 0)
			return;
		// otherwise, fall through and run a replace query
	}
	
	$updates['tid'] = $tid;
	$db->replace_query('threadfields_data', $updates);
}
function xthreads_delete_thread($tid) {
	global $db;
	// awesome thing about this is that it will delete threadfields even if the thread was moved to a different forum
	$db->delete_query('threadfields_data', 'tid='.$tid);
	
	xthreads_rm_attach_query('tid='.$tid);
}

function xthreads_inputdisp() {
	global $thread, $post, $fid, $mybb, $plugins;
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
		//xthreads_upload_attachments();
		
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
	if($editpost && $GLOBALS['templates']->cache['editpost_first']) {
		$plugins->add_hook('editpost_end', 'xthreads_editpost_first_tplhack');
	}
	
	if($mybb->input['previewpost']) {
		global $threadfields;
		
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
							if(empty($curthreaddata))
								$curthreaddata = $db->fetch_array($db->simple_select('threadfields_data', '`'.implode('`,`', array_keys($threadfield_cache)).'`', 'tid='.$thread['tid']));
							
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
				/*
				if($thread['tid']) {
					// just do an extra query to grab the threadfields
					$threadfields = $db->fetch_array($db->simple_select('threadfields_data', '`'.implode('`,`', array_keys($threadfield_cache)).'`', 'tid='.$thread['tid']));
					foreach($threadfields as $k => &$v) {
						xthreads_get_xta_cache($threadfield_cache[$k], $thread['tid']);
						xthreads_sanitize_disp($v, $threadfield_cache[$k], $thread['username']);
					}
				} else {
					// threadfields from input
					$nullstr = '';
					foreach($threadfield_cache as $k => &$v) {
						xthreads_get_xta_cache($v, $nullstr, $mybb->input['posthash']);
						$threadfields[$k] = $mybb->input['xthreads_'.$k];
						xthreads_sanitize_disp($threadfields[$k], $v, $mybb->user['username']);
					}
				}
				*/
			}
			
			// do first post hack if applicable
			if($GLOBALS['forum']['xthreads_firstpostattop']) {
				//require_once MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php';
				// above file should already be included
				if(function_exists('xthreads_tpl_postbithack'))
					xthreads_tpl_postbithack();
			}
		} else {
			// block preview if there's errors
			/* unset($GLOBALS['mybb']->input['previewpost']);
			$mybb->input['action'] = ($editpost ? 'editpost' : 'newthread');
			$errorvar = ($editpost ? 'post_errors' : 'thread_errors');
			if(!$GLOBALS[$errorvar])
				$GLOBALS[$errorvar] = ' '; */
			
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
		if(!$editpost && $GLOBALS['forum']['xthreads_allow_blankmsg'] && my_strlen($mybb->input['message']) == 0) {
			//$GLOBALS['xthreads_restore_blank_msg'] = true;
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
	if($GLOBALS['rand'] > 1) return; // dirty hack to speed things up a little
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
	global $tfinput, $tfinputrow, $extra_threadfields, $lang;
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
		
		if(isset($data[$k]))
			$defval = $data[$k];
		else
			$defval = $tf['defaultval'];
		
		unset($defvals);
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_SELECT:
			case XTHREADS_INPUT_RADIO:
			case XTHREADS_INPUT_CHECKBOX:
				$vals = array_map('htmlspecialchars_uni', $tf['vallist']);
				if($tf['multival']) {
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
		
		$tfinput[$k] = '';
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_TEXTAREA:
				if($tf['fieldheight'])
					$tf_fh = ' rows="'.intval($tf['fieldheight']).'"';
				$tfinput[$k] = '<textarea'.$tfname.$maxlen.$tf_fh.$tf_fw_cols.'>'.$defval.'</textarea>';
				break;
			case XTHREADS_INPUT_SELECT:
				if($tf['fieldheight'])
					$tf_fh = ' size="'.intval($tf['fieldheight']).'"';
				elseif($tf['multival'])
					$tf_fh = ' size="5"';
				$tfinput[$k] = '<select name="xthreads_'.$tf['field'].($tf['multival'] ? '[]" multiple="multiple"':'"').$tf_fh.$tf_fw_style.'>';
				foreach($vals as &$val) {
					$selected = ((isset($defvals) && in_array($val, $defvals)) || $defval == $val ? ' selected="selected"':'');
					if(!$val && $tf['editable'] != XTHREADS_EDITABLE_REQ)
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
					$checked = ((isset($defvals) && in_array($val, $defvals)) || $defval == $val ? ' checked="checked"':'');
					if(!$val && $tf['editable'] != XTHREADS_EDITABLE_REQ)
						$tfinput[$k] .= '<label style="display: block; font-style: italic;"><input'.$tfname.' type="'.$tftype.'" class="'.$tftype.'" value=""'.$checked.' />'.$lang->xthreads_val_blank.'</label>';
					else
						$tfinput[$k] .= '<label style="display: block;"><input'.$tfname.' type="'.$tftype.'" class="'.$tftype.'" value="'.$val.'"'.$checked.' />'.unhtmlentities($val).'</label>';
				}
				break;
			case XTHREADS_INPUT_FILE:
				$tfinput[$k] = '';
				$jsext = '';
				if($defval) {
					//if(!is_array($defval))
					//	$defval = unserialize($defval);
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
							$md5hash = unpack('H*', $this_xta['md5hash']);
							$md5hash = reset($md5hash);
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
				
				$fileinput = '<input type="file" class="fileupload"'.$tfname.$tf_fw_size.' id="xthreads_'.$tf['field'].'" />';
				if(XTHREADS_ALLOW_URL_FETCH) {
					$input_url =& $GLOBALS['mybb']->input['xtaurl_'.$tf['field']];
					if($input_url || $GLOBALS['mybb']->input['xtasel_'.$tf['field']] == 'url') {
						$check_file = '';
						$check_url = ' checked="checked"';
					} else {
						$check_file = ' checked="checked"';
						$check_url = '';
					}
					if(!$input_url) $input_url = 'http://';
					
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
				//eval('$tfinput[$k] = "'.strtr($tf['formhtml'], array('\\' => '\\\\', '"' => '\\"')).'";');
				break;
				
			default: // text
				$tfinput[$k] = '<input type="text" class="textbox"'.$tfname.$maxlen.$tf_fw_size.' value="'.$defval.'" />';
				break;
		}
		
		$altbg = alt_trow();
		$inputfield =& $tfinput[$k];
		eval('$tfinputrow[$k] = "'.$GLOBALS['templates']->get('threadfields_inputrow').'";');
		if(!$tf['hideedit'])
			$extra_threadfields .= $tfinputrow[$k];
	}
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
	/*
	$aids = array();
	foreach($threadfield_cache as $k => &$v) {
		if(($v['inputtype'] == XTHREADS_INPUT_FILE || $v['inputtype'] == XTHREADS_INPUT_FILE_URL) && $mybb->input['xthreads_'.$k] && empty($_FILES['xthreads_'.$k]) && is_numeric($mybb->input['xthreads_'.$k])) {
			$aid = intval($mybb->input['xthreads_'.$k]);
			if(!isset($xta_cache[$aid]))
				$aids[] = $aid;
		}
	}
	if(!empty($aids)) {
		global $db;
		if($GLOBALS['thread']['tid'])
			$attachwhere = 'tid='.intval($GLOBALS['thread']['tid']);
		else
			$attachwhere = 'posthash="'.$db->escape_string($mybb->input['posthash']).'"';
		$query = $db->simple_select('xtattachments', '*', 'aid IN ('.implode(',', $aids).') AND '.$attachwhere);
		while($attachment = $db->fetch_array($query))
			$xta_cache[$attachment['aid']] = $attachment;
		unset($aids);
	}
	*/
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
	
	$errors = '';
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
				
				/*
				$mybb->input['xthreads_'.$k] = intval($mybb->input['xthreads_'.$k]);
				$aid =& $mybb->input['xthreads_'.$k];
				
				// validate passed aid
				if($aid) {
					if(!isset($xta_cache[$aid]) || $xta_cache[$aid]['field'] != $k)
						$aid = 0; // invalid aid supplied
				}
				
				// if the thread already exists, we totally won't trust the supplied aid - use one stored in DB
				global $threadfields, $thread;
				if(!empty($threadfields))
					$aid = $threadfields[$k];
				elseif(!empty($thread)) {
					
				}
				*/
			}
			
			
			
			// handle file upload
			$ul = null;
			if(!empty($_FILES['xthreads_'.$k]) && !empty($_FILES['xthreads_'.$k]['name'])) {
				$ul =& $_FILES['xthreads_'.$k];
				if($mybb->input['xtaurl_'.$k])
					unset($mybb->input['xtaurl_'.$k]);
			}
			elseif($v['inputtype'] == XTHREADS_INPUT_FILE && XTHREADS_ALLOW_URL_FETCH && $mybb->input['xtaurl_'.$k]) {
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
					$errors .= '<li>'.$lang->sprintf($lang->xthreads_threadfield_attacherror, htmlspecialchars_uni($v['title']), $attachedfile['error']).'</li>';
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
		$db->update_query('threadfields_data', $threadfield_updates, 'tid='.$GLOBALS['thread']['tid']);
	}
	
	@ignore_user_abort(false);
	
	if($errors) {
		global $theme, $templates;
		$attachedfile = array('error' => '<ul>'.$errors.'</ul>');
		eval('$GLOBALS[\'attacherror\'] .= "'.$templates->get('error_attacherror').'";');
		// if there's going to be a MyBB attachment error, and it's not been evaluated yet, shove it in the template to force it through - safe since this function is guaranteed to run only once
		$templates->cache['error_attacherror'] = str_replace('{$attachedfile[\'error\']}', '<ul>'.strtr($errors, array('\\' => '\\\\', '$' => '\\$', '{' => '\\{', '}' => '\\}')).'<li>{$attachedfile[\'error\']}</li></ul>', $templates->cache['error_attacherror']);
		
		$mybb->input['action'] = ($GLOBALS['current_page'] == 'newthread.php' ? 'newthread' : 'editpost');
		
		// block the preview, since a failed upload can stuff it up
		$GLOBALS['plugins']->add_hook('newthread_start', 'xthreads_newthread_ulattach_blockpreview');
		$GLOBALS['plugins']->add_hook('editpost_start', 'xthreads_editthread_ulattach_blockpreview');
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

