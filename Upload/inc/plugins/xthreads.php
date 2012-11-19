<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


define('XTHREADS_VERSION', 1.61);
@include_once(MYBB_ROOT.'cache/xthreads.php'); // include defines/settings
if(!defined('IN_ADMINCP')) {
	@include_once MYBB_ROOT.'cache/xthreads_evalcache.php'; // if missing, we'll regenerate only in the AdminCP; weird heuristic, but one would imagine that a visit there is likely if the evalcache stuffs up
}

$plugins->add_hook('forumdisplay_start', 'xthreads_forumdisplay_sortlang');
$plugins->add_hook('forumdisplay_thread', 'xthreads_format_thread_date');
$plugins->add_hook('showthread_start', 'xthreads_format_thread_date');
$plugins->add_hook('global_start', 'xthreads_global', 5); // load this before most plugins so that they will utilise our modified system
$plugins->add_hook('archive_start', 'xthreads_archive_breadcrumb');

//xthreads_preglobal();

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

$plugins->add_hook('newthread_start', 'xthreads_inputdisp', 10, $updatehooks_file);
$plugins->add_hook(($GLOBALS['mybb']->version_code >= 1412 ? 'editpost_action_start' : 'editpost_start'), 'xthreads_inputdisp', 10, $updatehooks_file);
$plugins->add_hook('editpost_do_editpost_start', 'xthreads_editpost_autofill', 10, $updatehooks_file);

$plugins->add_hook('newreply_do_newreply_end', 'xthreads_js_remove_noreplies_notice', 10, $updatehooks_file);

$modupdhooks_file = MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
$plugins->add_hook('class_moderation_delete_thread', 'xthreads_delete_thread', 10, $modupdhooks_file);
$plugins->add_hook('class_moderation_copy_thread', 'xthreads_copy_thread', 10, $modupdhooks_file);
$plugins->add_hook('moderation_start', 'xthreads_moderation', 10, $modupdhooks_file);
//$plugins->add_hook('class_moderation_split_posts', 'xthreads_split_posts', 10, $modupdhooks_file);
$plugins->add_hook('usercp_do_drafts_start', 'xthreads_purge_draft', 10, $modupdhooks_file);


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

define('XTHREADS_HIDE_INPUT', 0x1);
define('XTHREADS_HIDE_THREAD', 0x2);
define('XTHREADS_HIDE_FORUM_SORT', 0x4);

define('XTHREADS_INPUT_TEXT', 0);
define('XTHREADS_INPUT_TEXTAREA', 1);
define('XTHREADS_INPUT_SELECT', 2);
define('XTHREADS_INPUT_RADIO', 3);
define('XTHREADS_INPUT_CHECKBOX', 4);
define('XTHREADS_INPUT_FILE', 5);
define('XTHREADS_INPUT_FILE_URL', 6);

define('XTHREADS_DATATYPE_TEXT', 0);
define('XTHREADS_DATATYPE_INT', 1);
define('XTHREADS_DATATYPE_UINT', 2);
define('XTHREADS_DATATYPE_BIGINT', 3);
define('XTHREADS_DATATYPE_BIGUINT', 4);
define('XTHREADS_DATATYPE_FLOAT', 5);

define('XTHREADS_FILTER_NONE', 0);
define('XTHREADS_FILTER_EXACT', 1);
define('XTHREADS_FILTER_PREFIX', 2);
define('XTHREADS_FILTER_ANYWHERE', 3);
define('XTHREADS_FILTER_WILDCARD', 4);


if(defined('IN_ADMINCP')) {
	require MYBB_ROOT.'inc/xthreads/xt_admin.php';
}

/**
 * Grab all information on thread fields
 * 
 * @param fid: forum ID to filter relevance from; 0 = no filtering (get all fields), -1 = only get fields applying to all forums
 * @return array of thread fields
 */
function &xthreads_gettfcache($fid=0) {
	global $cache;
	$tf = $cache->read('threadfields');
	if($fid && !empty($tf)) {
		foreach($tf as $k => &$v) {
			if($v['forums'] && ($fid == -1 || strpos(','.$v['forums'].',', ','.$fid.',') === false)) {
				unset($tf[$k]);
			}
		}
	}
	return $tf;
}


// the additional sorter options - need to load lang file
function xthreads_forumdisplay_sortlang() {
	global $lang;
	if(!$lang->sort_by_icon)
		$lang->load('xthreads');
	// prefix sort
	if($GLOBALS['mybb']->version_code >= 1500) {
		$tpl =& $GLOBALS['templates']->cache['forumdisplay_threadlist'];
		$tpl = str_replace('{$sort_by_prefix}', '<option value="prefix" {$sortsel[\'prefix\']}>{$lang->sort_by_prefix}</option>', $tpl);
	}
}

function xthreads_format_thread_date() {
	// since this is so useful, always format start time/date for each thread
	global $thread, $mybb;
	$thread['threaddate'] = my_date($mybb->settings['dateformat'], $thread['dateline']);
	$thread['threadtime'] = my_date($mybb->settings['timeformat'], $thread['dateline']);
	
	// I'm lazy, also do threadurl evaluation here
	xthreads_set_threadforum_urlvars('thread', $thread['tid']);
	
	// issue on forumdisplay: it's possible for subforums to override the forum URL variable, so set it again here just in case
	static $done=false;
	if(!$done) {
		$done = true;
		xthreads_set_threadforum_urlvars('forum', $thread['fid']);
	}
}

function xthreads_set_threadforum_urlvars($where, $id) {
	$url = $where.'url';
	$urlq = $url.'_q';
	$func = 'get_'.$where.'_link';
	$GLOBALS[$urlq] = $GLOBALS[$url] = $func($id);
	if(strpos($GLOBALS[$urlq], '?')) $GLOBALS[$urlq] .= '&amp;';
	else $GLOBALS[$urlq] .= '?';
}


function xthreads_global() {
	global $current_page, $mybb, $templatelist, $templates;
	
	if($current_page == 'index.php' || $current_page == 'forumdisplay.php') {
		global $plugins;
		require_once MYBB_ROOT.'inc/xthreads/xt_forumdhooks.php';
		$plugins->add_hook('forumdisplay_start', 'xthreads_forumdisplay');
		$plugins->add_hook('build_forumbits_forum', 'xthreads_tpl_forumbits');
		xthreads_global_forumbits_tpl();
	}
	
	switch($current_page) {
		case 'misc.php':
			if($mybb->input['action'] != 'rules') break;
		case 'forumdisplay.php': case 'newthread.php': case 'moderation.php':
			$fid = (int)$mybb->input['fid'];
			if($fid) break;
			
		case 'polls.php':
			switch($mybb->input['action']) {
				case 'editpoll':
				case 'do_editpoll':
				case 'showresults':
				case 'vote':
				case 'do_undovote':
					// no cached poll getting function, dupe a query then...
					global $db;
					$tid = $db->fetch_field($db->simple_select('polls', 'tid', 'pid='.(int)$mybb->input['pid']), 'tid');
			}
			// fall through
		case 'showthread.php': case 'newreply.php': case 'ratethread.php': case 'sendthread.php': case 'printthread.php':
			if(isset($tid) || $tid = (int)$mybb->input['tid']) {
				$thread = get_thread($tid);
				if($thread['fid']) {
					$fid = $thread['fid'];
					$set_thread_urlvar = $thread['tid'];
				}
			}
			if($fid || $current_page == 'polls.php') break;
			
		case 'editpost.php':
			if($pid = (int)$mybb->input['pid']) {
				$post = get_post($pid);
				if($post['fid']) {
					$fid = $post['fid'];
					$set_thread_urlvar = $post['tid'];
				}
			}
			break;
		
		case 'announcements.php':
			if($aid = (int)$mybb->input['aid']) {
				// unfortunately MyBB doesn't have a cache for announcements
				// so we can have fun and double query!
				global $db;
				$fid = $db->fetch_field($db->simple_select('announcements', 'fid', 'aid='.$aid), 'fid');
				// note, $fid can be 0, for invalid aid, or announcement applying to all forums
			}
			break;
		default: return;
	}
	$fid = (int)$fid; // paranoia
	
	if($fid) {
		global $forum;
		$forum = get_forum($fid);
		if(function_exists('xthreads_evalcacheForums')) {
			$xtforum = xthreads_evalcacheForums($fid);
			if($xtforum['tplprefix'] !== '') {
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
				$templates->xt_tpl_prefix = $xtforum['tplprefix'];
			}
			if($xtforum['langprefix'] !== '') {
				global $lang;
				// this forum has a custom lang prefix, hook into lang system
				control_object($lang, '
					function load($section, $isdatahandler=false, $supress_error=false) {
						$this->__xt_load($section, $isdatahandler, $supress_error);
						foreach($this->__xt_lang_prefix as &$pref)
							if($pref !== \'\')
								$this->__xt_load($pref.$section, $isdatahandler, true);
					}
					function __xt_load($section, $isdatahandler=false, $supress_error=false) {
						return parent::load($section, $isdatahandler, $supress_error);
					}
				');
				$lang->__xt_lang_prefix = $xtforum['langprefix'];
				// load global lang messages that we couldn't before
				foreach($lang->__xt_lang_prefix as &$pref) if($pref !== '' && preg_match('~^[a-zA-Z0-9_]+$~', $pref)) {
					$lang->__xt_load($pref.'global', false, true);
					$lang->__xt_load($pref.'messages', false, true);
				}
			}
			if($xtforum['settingoverrides'] !== '') {
				foreach($xtforum['settingoverrides'] as $k => &$v)
					$mybb->settings[$k] = $v;
			}
		}
		//if($forum['xthreads_firstpostattop']) {
			switch($current_page) {
				case 'showthread.php':
					require_once MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php';
					xthreads_showthread_firstpost();
					break;
				case 'newthread.php':
				case 'editpost.php':
					if($mybb->input['previewpost']) {
						$do_preload = true;
						if($current_page == 'editpost.php') {
							global $thread;
							// check if first post
							$post = get_post((int)$mybb->input['pid']);
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
		//}
		// settings overrides
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
					$templatelist .= ',post_threadfields_inputrow';
			break;
			case 'editpost.php':
				$templatelist .= ',editpost_first';
			case 'newthread.php':
				$templatelist .= ',post_threadfields_inputrow';
			case 'showthread.php':
				$templatelist .= ',showthread_threadfields,showthread_threadfield_row';
			break;
		}
		
		// hide breadcrumb business
		if($current_page != 'printthread.php') {
			xthreads_breadcrumb_hack($fid);
		} else {
			// printthread needs some whacky attention
			$GLOBALS['plugins']->add_hook('printthread_start', 'xthreads_breadcrumb_hack_printthread', 10, MYBB_ROOT.'inc/xthreads/xt_mischooks.php');
		}
		
		xthreads_set_threadforum_urlvars('forum', $forum['fid']);
	}
	if(isset($set_thread_urlvar)) {
		xthreads_set_threadforum_urlvars('thread', $set_thread_urlvar); // since it's convenient...
	}
}

function xthreads_tpl_cache(&$t, &$obj) {
	if(xthreads_empty($t)) return;
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

// get template prefixes for multiple forums
// returns an array (forums) of arrays (prefixes), unless $firstonly is true, in which case, it's an array of strings (first prefix)
function &xthreads_get_tplprefixes($firstonly=false, &$forums=null) {
	$ret = array();
	if(!function_exists('xthreads_evalcacheForums')) return $ret;
	if($forums) $fids = array_keys($forums);
	else $fids = array_keys($GLOBALS['cache']->read('forums'));
	foreach($fids as $fid) {
		$xtforum = xthreads_evalcacheForums($fid);
		if($xtforum['tplprefix'] === '') continue;
		$ret[$fid] = $xtforum['tplprefix'];
		if($firstonly) {
			$ret[$fid] = $ret[$fid][0];
		}
	}
	return $ret;
}


function xthreads_archive_breadcrumb() {
	if($GLOBALS['action'] == 'thread' || $GLOBALS['action'] == 'forum')
		xthreads_breadcrumb_hack($GLOBALS[$GLOBALS['action']]['fid']);
}

function xthreads_breadcrumb_hack($fid) {
	global $pforumcache, $forum_cache;
	if(!$pforumcache) {
		if(!is_array($forum_cache))
			cache_forums();
		foreach($forum_cache as &$val) {
			// MyBB does this very weirdly... I mean, like
			// ...the second dimension of the array is useless, since fid
			// is pulling a unique $val already...
			$pforumcache[$val['fid']][$val['pid']] = $val;
		}
	}
	if(!is_array($pforumcache[$fid])) return;
	
	// our strategy works by rewriting parents of forums below hidden forums
	foreach($pforumcache[$fid] as &$pforum) { // will only ever loop once
		if($pforum['fid'] != $fid) continue; // paranoia
		
		// we can't really hide the active breadcrumb, so ignore current forum...
		// (actually, it might be possible if we rewrite forum ids)
		if($pforum['pid']) {
			$prevforum =& $pforum;
			$forum =& xthreads_get_array_first($pforumcache[$pforum['pid']]);
			while($forum) {
				if(!$forum['xthreads_hidebreadcrumb']) {
					// rewrite parent fid (won't actually change if there's no hidden breadcrumbs in-between)
					$prevforum['pid'] = $forum['fid'];
					$prevforum =& $forum;
				}
				if(!$forum['pid']) break;
				$forum =& xthreads_get_array_first($pforumcache[$forum['pid']]);
			}
			$prevforum['pid'] = 0;
		}
	}
}

function xthreads_handle_uploads() {
	// hijack this function to do something else:
	// slight optimisation - since it's possible for multiple template prefixes to cause the template list to become large (maybe a few KB? - not really that large is it?)
	// and the fact that I don't think anything uses it afterwards, we can unset it to save some memory
	unset($GLOBALS['templatelist']);
	
	global $mybb, $current_page;
	if($mybb->request_method == 'post' && ($current_page == 'newthread.php' || ($current_page == 'editpost.php' && $mybb->input['action'] != 'deletepost'))) {
		require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
		xthreads_upload_attachments_global();
	}
}

function xthreads_xmlhttp_blankpost_hack() {
	global $mybb;
	if($mybb->input['action'] == 'edit_post' && $mybb->input['do'] == 'get_post') {
		$post = get_post((int)$mybb->input['pid']);
		if($post['pid']) {
			$thread = get_thread($post['tid']);
			$forum = get_forum($thread['fid']);
			
			if(!$forum['xthreads_allow_blankmsg'] || $thread['firstpost'] != $post['pid']) return;
			global $templates;
			if(!isset($templates->cache['xmlhttp_inline_post_editor']))
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
		foreach($tfinfo['fileimgthumbs'] as $th => $complex)
			if(!isset($s['thumbs'][$th]))
				$s['thumbs'][$th] = array('w' => 0, 'h' => 0, 'url' => '');
		if(!isset($s['thumbs']['orig']))
			$s['thumbs']['orig'] = array();
		$s['dims'] =& $s['thumbs']['orig'];
	}
}
// compatibility with XThreads < 1.60: we need to have the &$s variable here (rather than return $s) because we need to allow usage of {$threadfields[key]..} usage which was allowed
function xthreads_sanitize_disp_set_xta_fields(&$s, $aid, &$tfinfo, $dispfmt='', $evalfunc='') {
	global $xta_cache;
	if(!is_numeric($aid) || !isset($xta_cache[$aid])) {
		// fallback - prevent templating errors if this file happens to not exist
		$s = array();
		xthreads_sanitize_disp_set_blankthumbs($s, $tfinfo);
		//return $s;
	}
	$s = $xta_cache[$aid];
	$s['downloads_friendly'] = my_number_format($s['downloads']);
	$s['url'] = xthreads_get_xta_url($s); // this must be placed before filename so that it isn't htmlspecialchar'd!
	$s['filename'] = htmlspecialchars_uni($s['filename']);
	$s['uploadmime'] = htmlspecialchars_uni($s['uploadmime']);
	if(!$s['updatetime']) $s['updatetime'] = $s['uploadtime'];
	$s['filesize_friendly'] = get_friendly_size($s['filesize']);
	if(isset($s['md5hash'])) {
		$s['md5hash'] = bin2hex($s['md5hash']);
	}
	$s['icon'] = get_attachment_icon(get_extension($s['filename']));
	
	$settings =& $GLOBALS['mybb']->settings;
	$s['upload_time'] = my_date($settings['timeformat'], $s['uploadtime']);
	$s['upload_date'] = my_date($settings['dateformat'], $s['uploadtime']);
	$s['update_time'] = my_date($settings['timeformat'], $s['updatetime']);
	$s['update_date'] = my_date($settings['dateformat'], $s['updatetime']);
	$s['modified'] = ($s['updatetime'] != $s['uploadtime'] ? 'modified' :'');
	if($s['thumbs'])
		$s['thumbs'] = unserialize($s['thumbs']);
	if(isset($s['thumbs']['orig']))
		$s['dims'] =& $s['thumbs']['orig'];
	if(!empty($s['thumbs'])) foreach($s['thumbs'] as $thumbdim => &$thumb) {
		if($thumbdim == 'orig')
			$thumb['url'] =& $s['url'];
		else
			$thumb['url'] = xthreads_get_xta_url($s, $thumbdim);
	}
	xthreads_sanitize_disp_set_blankthumbs($s, $tfinfo);
	
	if($dispfmt) {
		$s['value'] = '';
		$vars = array();
		if($tfinfo[$dispfmt]) {
			foreach($s as $k => &$v)
				$vars[strtoupper($k)] =& $v;
		}
		$s['value'] = $evalfunc($dispfmt, $vars);
	}
	//return $s;
}
function xthreads_sanitize_disp(&$s, &$tfinfo, $mename=null, $noextra=false) {
	$evalfunc = 'xthreads_evalcache_'.$tfinfo['field'];
	if(!$noextra) {
		// this "hack" stops this function being totally independent of the outside world :(
		global $threadfields_x;
		if(!isset($threadfields_x))
			$threadfields_x = array();
		$sx =& $threadfields_x[$tfinfo['field']];
	} // otherwise, let the following line dummy the variable
	$sx = array('title' => htmlspecialchars_uni($tfinfo['title']), 'desc' => htmlspecialchars_uni($tfinfo['desc']), 'num_values' => 1, 'num_values_friendly' => my_number_format(1));
	
	$dispfmt = 'dispformat';
	
	if(!xthreads_user_in_groups($tfinfo['viewable_gids'])) {
		$dispfmt = 'unviewableval';
	}
	
	if($tfinfo['inputtype'] == XTHREADS_INPUT_FILE || ($tfinfo['inputtype'] == XTHREADS_INPUT_FILE_URL && !preg_match('~^[a-z]+\://~i', $s))) {
		global $xta_cache, $mybb;
		// attached file
		if(!$s) {
			$s = array('value' => $evalfunc('blankval'));
			xthreads_sanitize_disp_set_blankthumbs($s, $tfinfo);
			return;
		}
		if(xthreads_empty($tfinfo['multival'])) {
			xthreads_sanitize_disp_set_xta_fields($s, $s, $tfinfo, $dispfmt, $evalfunc);
			$sx['value'] =& $s['value'];
		} else {
			$aids = explode(',', $s);
			$s = array(
				'total_downloads' => 0,
				'total_filesize' => 0,
				'updatetime' => 0,
				'uploadtime' => 0,
				'value' => '',
				'num_files' => 0,
			);
			$sx['items'] = $sx['value'] = array();
			$comma = '';
			foreach($aids as $aid) {
				$xta =& $sx['items'][];
				xthreads_sanitize_disp_set_xta_fields($xta, $aid, $tfinfo, 'dispitemformat', $evalfunc);
				if(!$xta['aid']) continue;
				$sx['value'][] = $xta['value'];
				$s['total_downloads'] += $xta['downloads'];
				$s['total_filesize'] += $xta['filesize'];
				$s['uploadtime'] = max($xta['uploadtime'], $s['uploadtime']);
				++$s['num_files'];
				
				$s['value'] .= $comma.$xta['value'];
				$comma = $tfinfo['multival'];
			}
			$s['total_downloads_friendly'] = my_number_format($s['total_downloads']);
			$s['total_filesize_friendly'] = get_friendly_size($s['total_filesize']);
			$s['upload_time'] = my_date($mybb->settings['timeformat'], $s['uploadtime']);
			$s['upload_date'] = my_date($mybb->settings['dateformat'], $s['uploadtime']);
			$s['value'] = $evalfunc($dispfmt, array('VALUE' => $s['value']));
			
			$sx['num_values'] = $s['num_files'];
			$sx['num_values_friendly'] = $s['num_files_friendly'] = my_number_format($s['num_files']);
		}
	}
	else {
		if($s === '' || $s === null) {
			$sx['num_values'] = 0;
			$sx['num_values_friendly'] = my_number_format(0);
			$s = $evalfunc('blankval');
			return;
		}
		if(!xthreads_empty($tfinfo['multival'])) {
			// we _could_ optimise this out if the final $dispformat never actually refers to {VALUE}, but this is perhaps an unlikely situation, and we don't know whether the dispitemformat has some special eval'd code we should run
			$vals = explode("\n", str_replace("\r", '', $s));
			$i = 0;
			$sx['value'] = array();
			foreach($vals as &$v) {
				xthreads_sanitize_disp_field($v, $tfinfo, 'dispitemformat', $mename);
				$sx['value'][$i++] = $v;
			}
			$sx['num_values'] = $i;
			$sx['num_values_friendly'] = my_number_format($i);
			$s = implode($tfinfo['multival'], $vals);
			$s = $evalfunc($dispfmt, array('VALUE' => $s));
		}
		else {
			xthreads_sanitize_disp_field($s, $tfinfo, $dispfmt, $mename);
			$sx['value'] =& $s;
		}
		
	}
}

function xthreads_sanitize_disp_field(&$v, &$tfinfo, $dispfmt, $mename) {
	$raw_v = $v;
	if(isset($tfinfo['formatmap']) && isset($tfinfo['formatmap'][($vnl = str_replace("\r", '', $v))])) {
		$v = eval_str($tfinfo['formatmap'][$vnl]); // not sanitized obviously
	} else {
		$type = $tfinfo['sanitize'];
		$v = xthreads_sanitize_disp_string($type, $v, $parser_opts, $mename);
	}
	
	$evalfunc = 'xthreads_evalcache_'.$tfinfo['field'];
	if($tfinfo[$dispfmt]) {
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
							$vars['VALUE$'][$i] = xthreads_sanitize_disp_string($type, $val, $parser_opts, $mename);
						}
						break;
					default:
						$vars['VALUE$'] =& $match;
				}
			}
		}
		$v = $evalfunc($dispfmt, $vars);
	} else
		$v = $evalfunc($dispfmt);
}

function xthreads_sanitize_disp_string($type, &$v, &$parser_opts = null, $mename='') {
	switch($type & XTHREADS_SANITIZE_MASK) {
		case XTHREADS_SANITIZE_HTML:
			return htmlspecialchars_uni($v);
		case XTHREADS_SANITIZE_HTML_NL:
			return strtr(nl2br(htmlspecialchars_uni($v)), array("\r" => '', "\n" => ''));
		case XTHREADS_SANITIZE_PARSER:
			global $parser;
			if(!is_object($parser)) {
				require_once MYBB_ROOT.'inc/class_parser.php';
				$parser = new postParser;
			}
			if(empty($parser_opts)) {
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
			}
			return $parser->parse_message($v, $parser_opts);
	}
	return $v;
}

function eval_str(&$s, $vars=array()) {
	// sanitisation done in cache build - don't need to do it here
	return eval('return "'.$s.'";');
}

// gets array of all usergroups user is in, stored in keys
function &xthreads_get_user_usergroups(&$user) {
	if($user['additionalgroups'])
		$ug = array_flip(explode(',', $user['additionalgroups']));
	else
		$ug = array();
	$ug[$user['usergroup']] = 1;
	return $ug;
}

// determines whether current user is in a list of groups
// empty gids list implies all usergroups (so will always return true if this is the case)
function xthreads_user_in_groups(&$gids) {
	if(empty($gids)) return true;
	
	static $ingroups = null;
	if(!isset($ingroups))
		$ingroups = xthreads_get_user_usergroups($GLOBALS['mybb']->user);
	foreach($gids as $gid)
		if(isset($ingroups[$gid]))
			return true;
	return false;
}

// note, $tf isn't used for loading xtattachment cache - it's only there to simplify loop-logic
function xthreads_get_xta_cache(&$tf, &$tids, $posthash='') {
	//if(!$tids) return;
	// our special query needed to get download info across
	static $done_attach_dl_count = null;
	if(!isset($done_attach_dl_count)) $done_attach_dl_count = array();
	$donekey = ($posthash ? $posthash : $tids);
	
	if(!isset($done_attach_dl_count[$donekey]) && ($tf['inputtype'] == XTHREADS_INPUT_FILE || $tf['inputtype'] == XTHREADS_INPUT_FILE_URL)) {
		$done_attach_dl_count[$donekey] = true;
		
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
function xthreads_get_xta_url(&$xta, $thumb='') {
	$delim = '/';
	if(defined('XTHREADS_ATTACH_USE_QUERY') && XTHREADS_ATTACH_USE_QUERY == 2)
		$delim = '|';
	if(isset($xta['md5hash'])) {
		$md5hash = $xta['md5hash'];
		if(isset($md5hash{15}) && !isset($md5hash{16})) {
			$md5hash = bin2hex($md5hash);
		} elseif(!isset($md5hash{31}) || isset($md5hash{32}))
			$md5hash = '';
		if($md5hash) $md5hash .= $delim;
	} else
		$md5hash = '';
	$updatetime = $xta['updatetime'];
	if(!$updatetime) $updatetime = $xta['uploadtime'];
	
	static $use_qstr = null;
	// to use query strings, or not to use; that is the question...
	if(!isset($use_qstr)) {
		if(defined('XTHREADS_ATTACH_USE_QUERY') && XTHREADS_ATTACH_USE_QUERY)
			$use_qstr = (XTHREADS_ATTACH_USE_QUERY > 0);
		else
			$use_qstr = (!in_array(PHP_SAPI, array('apache', 'apache2filter', 'apache2handler', 'cgi', 'cgi-fcgi')) || defined('ARCHIVE_QUERY_STRINGS'));
	}
	
	if($thumb) $thumb = $delim.'thumb'.$thumb;
	else $thumb = '';
	
	$attachhash = substr($xta['attachname'], 0, 8);
	if(XTHREADS_EXPIRE_ATTACH_LINK || XTHREADS_ATTACH_LINK_IPMASK) {
		if(!function_exists('xthreads_attach_encode_hash')) {
			require_once MYBB_ROOT.'inc/xthreads/xt_attachfuncs.php';
		}
		$attachhash = dechex(xthreads_attach_encode_hash(hexdec($attachhash)));
	}
	return 'xthreads_attach.php'.($use_qstr?'?file=':'/').$xta['aid'].'_'.$updatetime.'_'.$attachhash.$delim.$md5hash.rawurlencode($xta['filename']).$thumb;
}

// only 'username' and 'fid' keys are used from the $thread array
function xthreads_get_threadfields($tid, &$threadfields, $noextra=true, $thread=array()) {
	$tid = (int)$tid;
	if(!$tid) return;
	
	if(empty($thread))
		$thread = get_thread($tid);
	
	if($thread['fid'] == $GLOBALS['fid']) // use global cache if we're referring to current forum
		$threadfield_cache =& $GLOBALS['threadfield_cache'];
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache((int)$thread['fid']);
	
	if(!empty($threadfield_cache)) {
		global $db;
		$threadfields = $db->fetch_array($db->simple_select('threadfields_data', '`'.implode('`,`', array_keys($threadfield_cache)).'`', 'tid='.$tid));
		if(!isset($threadfields)) $threadfields = array();
		foreach($threadfield_cache as $k => &$v) {
			xthreads_get_xta_cache($v, $tid);
			xthreads_sanitize_disp($threadfields[$k], $v, $thread['username'], $noextra);
		}
	}
}


function xthreads_phptpl_eval_expr($s) {
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	return eval('return ('.xthreads_phptpl_expr_parse($s).');');
}

function xthreads_phptpl_eval_text($s) {
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	xthreads_sanitize_eval($s);
	return eval_str($s);
}

// it's annoying that '0' is considered "empty" by PHP...
function xthreads_empty(&$v) {
	return empty($v) && $v !== '0';
}
// PHP doesn't seem to give an easy way to get a reference to the first element
function &xthreads_get_array_first(&$a) {
	foreach($a as &$e)
		return $e;
	$null = null;
	return $null;
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

