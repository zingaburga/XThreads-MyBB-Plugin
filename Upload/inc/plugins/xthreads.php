<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


define('XTHREADS_VERSION', 1.33);


// XThreads defines
// controls some things for remote file fetching
// allows users to upload files through URL fetching
define('XTHREADS_ALLOW_URL_FETCH', true);
// hosts which URLs cannot be fetched from, note that this is based on the supplied URL - hosts or IPs are not resolved; separate with commas
define('XTHREADS_URL_FETCH_DISALLOW_HOSTS', 'localhost,127.0.0.1');
// disallow users to specify custom ports in URL, eg http://example.com:1234/ [default=enabled (false)]
define('XTHREADS_URL_FETCH_DISALLOW_PORT', false);

// try to stop xtattachment flooding through orphaning (despite MyBB itself being vulnerable to it); we'll silently remove orphaned xtattachments that are added within a certain timeframe; note, this does not apply to guests, if you allow them to upload xtattachments...
// by default, we'll start removing old xtattachments made by a user within the last half hour if there's more than 50 orphaned xtattachments
define('XTHREADS_UPLOAD_FLOOD_TIME', 1800); // in seconds
define('XTHREADS_UPLOAD_FLOOD_NUMBER', 50);
// also, automatically remove xtattachments older than 3 hours when they try to upload something new
define('XTHREADS_UPLOAD_EXPIRE_TIME', 3*3600); // in seconds

// the size a file must be above to be considered a "large file"; large files will have their MD5 calculation deferred to a task; set to 0 to disable deferred MD5 hashing
define('XTHREADS_UPLOAD_LARGEFILE_SIZE', 10*1048576); // in bytes, default is 10MB

// allow PHP in threadfields' display format, unviewable format etc; note that if you change this value after XThreads has been installed, you may need to rebuild your "threadfields" cache
// 0=disable, 1=enable, 2=enable only if PHP in Templates plugin is activated (default)
define('XTHREADS_ALLOW_PHP_THREADFIELDS', 2);

// some more defines can be found in xthreads_attach.php






$plugins->add_hook('forumdisplay_thread', 'xthreads_format_thread_date');
$plugins->add_hook('showthread_start', 'xthreads_format_thread_date');
$plugins->add_hook('global_start', 'xthreads_tplhandler');

//$plugins->add_hook('global_end', 'xthreads_fix_stats');
$plugins->add_hook('global_end', 'xthreads_handle_uploads');

$plugins->add_hook('xmlhttp', 'xthreads_xmlhttp_blankpost_hack');

// remote hooks
$mischooks_file = MYBB_ROOT.'inc/xthreads/xt_mischooks.php';
$plugins->add_hook('search_results_start', 'xthreads_search', 10, $mischooks_file);
$plugins->add_hook('fetch_wol_activity_end', 'xthreads_wol_patch_init', 10, $mischooks_file);
$plugins->add_hook('build_friendly_wol_location_end', 'xthreads_wol_patch', 10, $mischooks_file);

$plugins->add_hook('index_start', 'xthreads_fix_stats_index', 10, $mischooks_file);
$plugins->add_hook('member_profile_start', 'xthreads_fix_stats', 10, $mischooks_file);
$plugins->add_hook('portal_start', 'xthreads_fix_stats_portal', 10, $mischooks_file);
$plugins->add_hook('stats_start', 'xthreads_fix_stats_stats', 10, $mischooks_file);
$plugins->add_hook('usercp_start', 'xthreads_fix_stats_usercp', 10, $mischooks_file);

$plugins->add_hook('portal_start', 'xthreads_portal', 10, $mischooks_file);
$plugins->add_hook('portal_announcement', 'xthreads_portal_announcement', 10, $mischooks_file);


$plugins->add_hook('showthread_start', 'xthreads_showthread', 10, MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php');

$updatehooks_file = MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
//$plugins->add_hook('newthread_do_newthread_start', 'xthreads_input_handler');
$plugins->add_hook('datahandler_post_validate_thread', 'xthreads_input_posthandler_validate', 10, $updatehooks_file);
$plugins->add_hook('datahandler_post_insert_thread_post', 'xthreads_input_posthandler_insert', 10, $updatehooks_file);
$plugins->add_hook('datahandler_post_update_thread', 'xthreads_input_posthandler_insert', 10, $updatehooks_file);
$plugins->add_hook('datahandler_post_validate_post', 'xthreads_input_posthandler_postvalidate', 10, $updatehooks_file);
$plugins->add_hook('class_moderation_delete_thread', 'xthreads_delete_thread', 10, $updatehooks_file);
$plugins->add_hook('class_moderation_copy_thread', 'xthreads_copy_thread', 10, $updatehooks_file);
//$plugins->add_hook('class_moderation_split_posts', 'xthreads_split_posts', 10, $updatehooks_file);

$plugins->add_hook('newthread_start', 'xthreads_inputdisp', 10, $updatehooks_file);
$plugins->add_hook(($GLOBALS['mybb']->version_code >= 1412 ? 'editpost_action_start' : 'editpost_start'), 'xthreads_inputdisp', 10, $updatehooks_file);

$plugins->add_hook('newreply_do_newreply_end', 'xthreads_js_remove_noreplies_notice', 10, $updatehooks_file);


// uses lower 2 bits
define('XTHREADS_SANITIZE_HTML', 0);     // plaintext only
define('XTHREADS_SANITIZE_HTML_NL', 1);  // as above, but allow newlines
define('XTHREADS_SANITIZE_PARSER', 2);   // run through MyCode
define('XTHREADS_SANITIZE_NONE', 3);     // no filter
define('XTHREADS_SANITIZE_MASK', 0x03);

define('XTHREADS_SANITIZE_PARSER_NL2BR', 0x04);
define('XTHREADS_SANITIZE_PARSER_NOBADW', 0x08);
define('XTHREADS_SANITIZE_PARSER_HTML', 0x10);
define('XTHREADS_SANITIZE_PARSER_MYCODE', 0x20);
define('XTHREADS_SANITIZE_PARSER_MYCODEIMG', 0x40);
define('XTHREADS_SANITIZE_PARSER_SMILIES', 0x80);
define('XTHREADS_SANITIZE_PARSER_VIDEOCODE', 0x100); // 1.6 only, but harmless in 1.4

define('XTHREADS_EDITABLE_ALL', 0);   // editable by all
define('XTHREADS_EDITABLE_REQ', 1);   // required field; implies editable by all
define('XTHREADS_EDITABLE_MOD', 2);   // editable by mods
define('XTHREADS_EDITABLE_ADMIN', 3); // editable by admins only
define('XTHREADS_EDITABLE_NONE', 4);  // not editable

define('XTHREADS_INPUT_TEXT', 0);
define('XTHREADS_INPUT_TEXTAREA', 1);
define('XTHREADS_INPUT_SELECT', 2);
define('XTHREADS_INPUT_RADIO', 3);
define('XTHREADS_INPUT_CHECKBOX', 4);
define('XTHREADS_INPUT_FILE', 5);
define('XTHREADS_INPUT_FILE_URL', 6);
define('XTHREADS_INPUT_CUSTOM', 7);


if(defined('IN_ADMINCP')) {
	require MYBB_ROOT.'inc/xthreads/xt_admin.php';
}

function &xthreads_gettfcache($fid=0) {
	global $cache;
	$tf = $cache->read('threadfields');
	if($fid && !empty($tf)) {
		foreach($tf as $k => &$v) {
			if($v['forums'] && strpos(','.$v['forums'].',', ','.$fid.',') === false) {
				unset($tf[$k]);
			}
		}
	}
	return $tf;
}


function xthreads_format_thread_date() {
	// since this is so useful, always format start time/date for each thread
	global $thread, $mybb, $threadurl, $threadurl_q;
	$thread['threaddate'] = my_date($mybb->settings['dateformat'], $thread['dateline']);
	$thread['threadtime'] = my_date($mybb->settings['timeformat'], $thread['dateline']);
	
	// I'm lazy, also do threadurl evaluation here
	xthreads_set_threadforum_urlvars('thread', $thread['tid']);
}

function xthreads_set_threadforum_urlvars($where, $id) {
	$url = $where.'url';
	$urlq = $url.'_q';
	$func = 'get_'.$where.'_link';
	$GLOBALS[$urlq] = $GLOBALS[$url] = $func($id);
	if(strpos($GLOBALS[$urlq], '?')) $GLOBALS[$urlq] .= '&amp;';
	else $GLOBALS[$urlq] .= '?';
}


function xthreads_tplhandler() {
	global $current_page, $mybb, $templatelist, $templates;
	switch($current_page) {
		case 'forumdisplay.php': case 'newthread.php': case 'moderation.php':
			$fid = $mybb->input['fid'];
			if($fid) break;
			
		case 'showthread.php': case 'newreply.php': case 'ratethread.php': case 'polls.php': case 'sendthread.php': case 'printthread.php':
			if($tid = intval($mybb->input['tid'])) {
				$thread = get_thread($tid);
				if($thread['fid']) {
					$fid = $thread['fid'];
					xthreads_set_threadforum_urlvars('thread', $thread['tid']); // since it's convenient...
				}
			}
			if($fid) break;
			
		case 'editpost.php':
			if($pid = intval($mybb->input['pid'])) {
				$post = get_post($pid);
				if($post['fid']) {
					$fid = $post['fid'];
					xthreads_set_threadforum_urlvars('thread', $post['tid']); // since it's convenient...
				}
			}
			if($fid) {
				$templatelist .= ',editpost_first';
			}
			break;
		
		case 'index.php':
			// we're only here for the forumbit fix
			$fid = 0;
			break;
		default: return;
	}
	
	$fid = intval($fid);
	
	if($fid) {
		$forum = get_forum($fid);
		if($forum['xthreads_tplprefix']) {
			// this forum has a custom tpl prefix, hook into templates system
			control_object($templates, '
				function cache($templates) {
					xthreads_tpl_cache($templates, $this);
				}
				
				function get($title, $eslashes=1, $htmlcomments=1) {
					xthreads_tpl_get($this, $title);
					return parent::get($title, $eslashes, $htmlcomments);
				}
			');
			$templates->non_existant_templates = array();
			$templates->xt_tpl_prefix = explode(',', $forum['xthreads_tplprefix']);
		}
		if($forum['xthreads_firstpostattop']) {
			switch($current_page) {
				case 'showthread.php':
					require_once MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php';
					xthreads_showthread_firstpost();
					break;
				case 'newthread.php':
				case 'editpost.php': // preload regardless of it being the first post or not
					if($mybb->input['previewpost']) {
						$do_preload = true;
						if($current_page == 'editpost.php') {
							global $thread;
							// check if first post
							$post = get_post(intval($mybb->input['pid']));
							if(!empty($post))
								$thread = get_thread($post['tid']);
							$do_preload = (!empty($thread) && $thread['firstpost'] == $post['pid']);
						}
						if($do_preload) {
							require_once MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php';
							xthreads_firstpost_tpl_preload();
						}
						break;
					}
			}
		}
		// settings overrides
		if($forum['xthreads_force_postlayout'])
			$mybb->settings['postlayout'] = $forum['xthreads_force_postlayout'];
		if($forum['xthreads_threadsperpage'])
			$mybb->settings['threadsperpage'] = $forum['xthreads_threadsperpage'];
		if($forum['xthreads_postsperpage'])
			$mybb->settings['postsperpage'] = $forum['xthreads_postsperpage'];
		
		// cache some more templates if necessary
		switch($current_page) {
			case 'forumdisplay.php':
				if($forum['xthreads_grouping'])
					$templatelist .= ',forumdisplay_group_sep,forumdisplay_thread_null';
				if($forum['xthreads_inlinesearch'])
					$templatelist .= ',forumdisplay_searchforum_inline';
				if(function_exists('quickthread_run')) // Quick Thread plugin
					$templatelist .= ',threadfields_inputrow';
			break;
			case 'editpost.php':
			case 'newthread.php':
				$templatelist .= ',threadfields_inputrow';
			break;
		}
		
		xthreads_set_threadforum_urlvars('forum', $forum['fid']);
	}
	if($current_page == 'index.php' || $current_page == 'forumdisplay.php') {
		global $plugins;
		require_once MYBB_ROOT.'inc/xthreads/xt_forumdhooks.php';
		$plugins->add_hook('forumdisplay_start', 'xthreads_forumdisplay');
		$plugins->add_hook('build_forumbits_forum', 'xthreads_tpl_forumbits');
		xthreads_global_forumbits_tpl();
	}
}

function xthreads_tpl_cache(&$t, &$obj) {
	if(!$t) return;
	global $db, $theme;
	
	$sql = '';
	$ta = explode(',', $t);
	foreach($ta as &$tpl) {
		$tpl = trim($tpl);
		$sql .= ',"'.$tpl.'"';
		foreach($obj->xt_tpl_prefix as &$prefix)
			$sql .= ',"'.$db->escape_string($prefix.$tpl).'"';
	}
	$query = $db->simple_select('templates', 'title,template', 'title IN (""'.$sql.') AND sid IN ("-2","-1","'.$theme['templateset'].'")', array('order_by' => 'sid', 'order_dir' => 'asc'));
	while($template = $db->fetch_array($query))
		$obj->cache[$template['title']] = $template['template'];
	$db->free_result($query);
	
	// now override default templates - this code actually ensures that all requested templates will have an entry in the cache array
	foreach($ta as &$tpl) {
		foreach($obj->xt_tpl_prefix as &$prefix)
			if(isset($obj->cache[$prefix.$tpl])) {
				$obj->cache[$tpl] =& $obj->cache[$prefix.$tpl];
				break;
			}
		if(!isset($obj->cache[$tpl])) { // we'll add a possible optimisation thing here that MyBB doesn't do :P
			$obj->cache[$tpl] = '';
			$obj->non_existant_templates[$tpl] = true; // workaround for forumbits and postbit_first template prefixing
		}
	}
	// note: above won't affect portal/search templates, so isset() check does check whether template actually exists
}
function xthreads_tpl_get(&$obj, &$t) {
	if(!isset($obj->cache[$t])) {
		// template not loaded, load it
		if($GLOBALS['mybb']->debug_mode)
			$obj->uncached_templates[$t] = $t;
		$obj->cache($t);
	}
}


function xthreads_handle_uploads() {
	global $mybb, $current_page;
	if($mybb->request_method == 'post' && ($current_page == 'newthread.php' || $current_page == 'editpost.php')) {
		global $thread;
		if($current_page == 'editpost.php') {
			if($mybb->input['action'] == 'deletepost' && $mybb->request_method == 'post') return;
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
		
		require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
		xthreads_upload_attachments();
	}
}

function xthreads_xmlhttp_blankpost_hack() {
	global $mybb;
	if($mybb->input['action'] == 'edit_post' && $mybb->input['do'] == 'get_post') {
		$post = get_post(intval($mybb->input['pid']));
		if($post['pid']) {
			$thread = get_thread($post['tid']);
			$forum = get_forum($thread['fid']);
			
			if(!$forum['xthreads_allow_blankmsg'] || $thread['firstpost'] != $post['pid']) return;
			global $templates;
			if(!$templates->cache['xmlhttp_inline_post_editor'])
				$templates->cache('xmlhttp_inline_post_editor');
			$templates->cache['xmlhttp_inline_post_editor'] = str_replace(
				'onclick="Thread.quickEditSave({$post[\'pid\']});"',
				'onclick="Thread.spinner = new ActivityIndicator(\'body\', {image: imagepath+\'/spinner_big.gif\'}); new Ajax.Request(\'xmlhttp.php?action=edit_post&do=update_post&pid={$post[\'pid\']}&my_post_key=\'+my_post_key, {method: \'post\', postBody: \'value=\'+encodeURIComponent($(\'quickedit_{$post[\'pid\']}\').value).replace(/\+/g, \'%2B\'), onComplete: function(request) { Thread.quickEditSaved(request, {$post[\'pid\']}); }});"',
				$templates->cache['xmlhttp_inline_post_editor']
			);
		}
	}
}

// function to set thumbnails into array to prevent PHP giving "cannot use string as array index" errors if a thumbnail doesn't exist (eg thread moved into gallery)
function xthreads_sanitize_disp_set_blankthumbs(&$s, &$tfinfo) {
	if(!empty($tfinfo['fileimgthumbs'])) {
		if(!isset($s['thumbs']))
			$s['thumbs'] = array();
		foreach($tfinfo['fileimgthumbs'] as &$$th)
			if(!isset($s['thumbs'][$th]))
				$s['thumbs'][$th] = array('w' => 0, 'h' => 0);
		if(!isset($s['thumbs']['orig']))
			$s['thumbs']['orig'] = array();
		$s['dims'] =& $s['thumbs']['orig'];
	}
}
function xthreads_sanitize_disp(&$s, &$tfinfo, $mename=null) {
	if($s === '' || $s === null) { // won't catch file inputs, as they are integer type
		if($tfinfo['blankval']) $s = eval_str($tfinfo['blankval']);
		return;
	}
	
	$dispfmt = $tfinfo['dispformat'];
	
	global $mybb;
	if(!empty($tfinfo['viewable_gids'])) {
		$ingroups = xthreads_get_user_usergroups($mybb->user);
		$viewable = false;
		foreach($tfinfo['viewable_gids'] as $gid)
			if(isset($ingroups[$gid])) {
				$viewable = true;
				break;
			}
		if(!$viewable) {
			$dispfmt = $tfinfo['unviewableval'];
		}
	}
	
	if($tfinfo['inputtype'] == XTHREADS_INPUT_FILE || ($tfinfo['inputtype'] == XTHREADS_INPUT_FILE_URL && !preg_match('~^[a-z]+\://~i', $s))) {
		global $xta_cache;
		// attached file
		if(!$s) {
			$s = array();
			if($tfinfo['blankval']) $s['value'] = eval_str($tfinfo['blankval']);
			xthreads_sanitize_disp_set_blankthumbs($s, $tfinfo);
			return;
		}
		if(!is_numeric($s) || !isset($xta_cache[$s])) {
			// fallback - prevent templating errors if this file happens to not exist
			$s = array();
			xthreads_sanitize_disp_set_blankthumbs($s, $tfinfo);
			return;
		}
		$s = $xta_cache[$s];
		$s['downloads_friendly'] = my_number_format($s['downloads']);
		$s['filename'] = htmlspecialchars_uni($s['filename']);
		$s['uploadmime'] = htmlspecialchars_uni($s['uploadmime']);
		if(!$s['updatetime']) $s['updatetime'] = $s['uploadtime'];
		$s['filesize_friendly'] = get_friendly_size($s['filesize']);
		if(isset($s['md5hash'])) {
			$s['md5hash'] = bin2hex($s['md5hash']);
		}
		$s['url'] = xthreads_get_xta_url($s);
		$s['icon'] = get_attachment_icon(get_extension($s['filename']));
		
		$s['upload_time'] = my_date($mybb->settings['timeformat'], $s['uploadtime']);
		$s['upload_date'] = my_date($mybb->settings['dateformat'], $s['uploadtime']);
		$s['update_time'] = my_date($mybb->settings['timeformat'], $s['updatetime']);
		$s['update_date'] = my_date($mybb->settings['dateformat'], $s['updatetime']);
		$s['modified'] = ($s['updatetime'] != $s['uploadtime'] ? 'modified' :'');
		if($s['thumbs'])
			$s['thumbs'] = unserialize($s['thumbs']);
		if(isset($s['thumbs']['orig']))
			$s['dims'] =& $s['thumbs']['orig'];
		xthreads_sanitize_disp_set_blankthumbs($s, $tfinfo);
		
		$s['value'] = '';
		if($dispfmt) {
			$vars = array();
			foreach($s as $k => &$v)
				if(!is_array($v))
					$vars[strtoupper($k)] =& $v;
			$s['value'] = eval_str($dispfmt, $vars);
		}
	}
	else {
		if($tfinfo['multival']) {
			$vals = explode("\n", str_replace("\r", '', $s));
			foreach($vals as &$v) {
				xthreads_sanitize_disp_field($v, $tfinfo, $tfinfo['dispitemformat'], $mename);
			}
			$s = implode($tfinfo['multival'], $vals);
			if($dispfmt) {
				$s = eval_str($dispfmt, array('VALUE' => $s));
			}
		}
		else {
			xthreads_sanitize_disp_field($s, $tfinfo, $dispfmt, $mename);
		}
		
	}
}

function xthreads_sanitize_disp_field(&$v, &$tfinfo, &$dispfmt, $mename) {
	$raw_v = $v;
	if(isset($tfinfo['formatmap']) && isset($tfinfo['formatmap'][$v])) {
		$v = eval_str($tfinfo['formatmap'][$v]); // not sanitized obviously
	} else {
		$type = $tfinfo['sanitize'];
		switch($type & XTHREADS_SANITIZE_MASK) {
			case XTHREADS_SANITIZE_HTML:
				$v = htmlspecialchars_uni($v);
				break;
			case XTHREADS_SANITIZE_HTML_NL:
				$v = nl2br(htmlspecialchars_uni($v));
				break;
			case XTHREADS_SANITIZE_PARSER:
				global $parser;
				if(!is_object($parser)) {
					require_once MYBB_ROOT.'inc/class_parser.php';
					$parser = new postParser;
				}
				$parser_opts = array(
					'nl2br' => ($type & XTHREADS_SANITIZE_PARSER_NL2BR ?1:0),
					'filter_badwords' => ($type & XTHREADS_SANITIZE_PARSER_NOBADW ?1:0),
					'allow_html' => ($type & XTHREADS_SANITIZE_PARSER_HTML ?1:0),
					'allow_mycode' => ($type & XTHREADS_SANITIZE_PARSER_MYCODE ?1:0),
					'allow_imgcode' => ($type & XTHREADS_SANITIZE_PARSER_MYCODEIMG ?1:0),
					'allow_smilies' => ($type & XTHREADS_SANITIZE_PARSER_SMILIES ?1:0),
					'allow_videocode' => ($type & XTHREADS_SANITIZE_PARSER_VIDEOCODE ?1:0),
					'me_username' => $mename
				);
				$v = $parser->parse_message($v, $parser_opts);
				break;
		}
	}
	
	if($dispfmt) {
		$vars = array(
			'VALUE' => $v, 
			'RAWVALUE' => $raw_v, 
		);
		if($tfinfo['regex_tokens']) {
			if(preg_match('~'.str_replace('~', '\\~', $tfinfo['textmask']).'~si', $raw_v, $match)) {
				$vars['RAWVALUE$'] =& $match;
				switch($type & XTHREADS_SANITIZE_MASK) {
					case XTHREADS_SANITIZE_HTML:
					case XTHREADS_SANITIZE_HTML_NL:
					case XTHREADS_SANITIZE_PARSER:
						$vars['VALUE$'] = array();
						foreach($match as $i => &$val) {
							$setvar =& $vars['VALUE$'][$i];
							switch($type & XTHREADS_SANITIZE_MASK) {
								case XTHREADS_SANITIZE_HTML:
									$setvar = htmlspecialchars_uni($val); break;
								case XTHREADS_SANITIZE_HTML_NL:
									$setvar = nl2br(htmlspecialchars_uni($val)); break;
								case XTHREADS_SANITIZE_PARSER:
									$setvar = $parser->parse_message($val, $parser_opts); break;
							}
						}
						break;
					default:
						$vars['VALUE$'] =& $match;
				}
			}
		}
		$v = eval_str($dispfmt, $vars);
	}
}

function eval_str(&$s, $vars=array()) {
	// sanitisation done in cache build - don't need to do it here
	return eval('return "'.$s.'";');
}

// wildcard match like the *nix filesystem
function xthreads_wildcard_match($str, $wc) {
	return preg_match('~'.strtr(preg_quote($wc, '~'), array(
		'\\*' => '.*',
		'\\?' => '.',
		'\\[' => '[', // hmm, won't properly match groups; [a-z] format should still work
		'\\]' => ']',
	)).'~i', $str);
}


function &xthreads_get_user_usergroups(&$user) {
	if($user['additionalgroups'])
		$ug = array_flip(explode(',', $user['additionalgroups']));
	else
		$ug = array();
	$ug[$user['usergroup']] = 1;
	return $ug;
}

// note, $tf isn't used for loading xtattachment cache - it's only there to simplify loop-logic
function xthreads_get_xta_cache(&$tf, &$tids, $posthash='') {
	if(!$tids) return;
	// our special query needed to get download info across
	static $done_attach_dl_count = false;
	if(!$done_attach_dl_count && $tf['inputtype'] == XTHREADS_INPUT_FILE || $tf['inputtype'] == XTHREADS_INPUT_FILE_URL) {
		$done_attach_dl_count = true;
		
		global $xta_cache, $db;
		if(!is_array($xta_cache))
			$xta_cache = array();
		if($posthash)
			$where = 'posthash="'.$db->escape_string($posthash).'"';
		else
			$where = 'tid IN ('.$tids.')';
		$query = $db->simple_select('xtattachments', '*', $where);
		while($xta = $db->fetch_array($query))
			$xta_cache[$xta['aid']] = $xta;
		$db->free_result($query);
	}
}
function xthreads_get_xta_url(&$xta) {
	if(isset($xta['md5hash'])) {
		$md5hash = $xta['md5hash'];
		if(isset($md5hash{15}) && !isset($md5hash{16})) {
			$md5hash = bin2hex($md5hash);
		} elseif(!isset($md5hash{31}) || isset($md5hash{32}))
			$md5hash = '';
		if($md5hash) $md5hash .= '/';
	} else
		$md5hash = '';
	$updatetime = $xta['updatetime'];
	if(!$updatetime) $updatetime = $xta['uploadtime'];
	
	static $use_qstr = null;
	// to use query strings, or not to use; that is the question...
	if(!isset($use_qstr))
		$use_qstr = ((DIRECTORY_SEPARATOR == '\\' && stripos($_SERVER['SERVER_SOFTWARE'], 'apache') === false) || stripos(SAPI_NAME, 'cgi') !== false || defined('ARCHIVE_QUERY_STRINGS'));
	// yes, this is copied from the archive, even though you won't be defining ARCHIVE_QUERY_STRINGS...
	
	return 'xthreads_attach.php'.($use_qstr?'?file=':'/').$xta['aid'].'_'.$updatetime.'_'.substr($xta['attachname'], 0, 8).'/'.$md5hash.rawurlencode($xta['filename']);
}

function xthreads_phptpl_iif($condition, $true)
{
	$args = func_get_args();
	for($i=1, $c=count($args); $i<$c; $i+=2)
		if($args[$i-1]) return $args[$i];
	return (isset($args[$i-1]) ? $args[$i-1] : '');
}

function xthreads_db_type($type=null) {
	if(!isset($type)) $type = $GLOBALS['db']->type;
	switch($type) {
		case 'sqlite3': case 'sqlite2': case 'sqlite':
			return 'sqlite';
		case 'pgsql':
			return 'pg';
		case 'mysql': case 'mysqli':
			return 'mysql';
	}
	return $type;
}


if(!function_exists('control_object')) {
	function control_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr) {
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v) {
				if($p = strrpos($k, "\0"))
					$k = substr($k, $p+1);
				$vars[$k] = $v;
			}
			if(!empty($vars))
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
				$obj->___setvars($vars);
		}
		// else not a valid object or PHP serialize has changed
	}
}

