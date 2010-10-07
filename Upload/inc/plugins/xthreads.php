<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


define('XTHREADS_VERSION', 0.51);


// XThreads defines
// controls some things for remote file fetching
// allows users to upload files through URL fetching
define('XTHREADS_ALLOW_URL_FETCH', true);
// hosts which URLs cannot be fetched from, note that this is based on the supplied URL - hosts or IPs are not resolved; separate with commas
define('XTHREADS_URL_FETCH_DISALLOW_HOSTS', 'localhost,127.0.0.1');
// disallow users to specify custom ports in URL, eg http://example.com:1234/
define('XTHREADS_URL_FETCH_DISALLOW_PORT', false);

// try to stop xtattachment flooding through orphaning (despite MyBB itself being vulnerable to it); we'll silently remove orphaned xtattachments that are added within a certain timeframe; note, this does not apply to guests, if you allow them to upload xtattachments...
// by default, we'll start removing old xtattachments made by a user within the last half hour if there's more than 50 orphaned xtattachments
define('XTHREADS_UPLOAD_FLOOD_TIME', 1800); // in seconds
define('XTHREADS_UPLOAD_FLOOD_NUMBER', 50);
// also, automatically remove xtattachments older than 3 hours when they try to upload something new
define('XTHREADS_UPLOAD_EXPIRE_TIME', 3*3600); // in seconds

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


$plugins->add_hook('showthread_start', 'xthreads_showthread', 10, MYBB_ROOT.'inc/xthreads/xt_sthreadhooks.php');

$updatehooks_file = MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
//$plugins->add_hook('newthread_do_newthread_start', 'xthreads_input_handler');
$plugins->add_hook('datahandler_post_validate_thread', 'xthreads_input_posthandler_validate', 10, $updatehooks_file);
$plugins->add_hook('datahandler_post_insert_thread_post', 'xthreads_input_posthandler_insert', 10, $updatehooks_file);
$plugins->add_hook('datahandler_post_update_thread', 'xthreads_input_posthandler_insert', 10, $updatehooks_file);
$plugins->add_hook('datahandler_post_validate_post', 'xthreads_input_posthandler_postvalidate', 10, $updatehooks_file);
$plugins->add_hook('class_moderation_delete_thread', 'xthreads_delete_thread', 10, $updatehooks_file);

$plugins->add_hook('newthread_start', 'xthreads_inputdisp', 10, $updatehooks_file);
$plugins->add_hook(($GLOBALS['mybb']->version_code >= 1600 ? 'editpost_action_start' : 'editpost_start'), 'xthreads_inputdisp', 10, $updatehooks_file);

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
	global $thread, $mybb;
	$thread['threaddate'] = my_date($mybb->settings['dateformat'], $thread['dateline']);
	$thread['threadtime'] = my_date($mybb->settings['timeformat'], $thread['dateline']);
}


function xthreads_tplhandler() {
	global $current_page, $mybb, $templatelist, $templates;
	switch($current_page) {
		case 'forumdisplay.php': case 'newthread.php': case 'moderation.php':
			// add in custom thread group separator template
			if($current_page == 'forumdisplay.php') $templatelist .= ',forumdisplay_group_sep,forumdisplay_thread_null';
			$fid = $mybb->input['fid'];
			if($fid) break;
			
		case 'showthread.php': case 'newreply.php': case 'ratethread.php': case 'polls.php': case 'sendthread.php': case 'printthread.php':
			if($tid = intval($mybb->input['tid'])) {
				$thread = get_thread($tid);
				if($thread['fid'])
					$fid = $thread['fid'];
			}
			if($fid) break;
			
		case 'editpost.php':
			if($pid = intval($mybb->input['pid'])) {
				$post = get_post($pid);
				if($post['fid'])
					$fid = $post['fid'];
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
					xthreads_tpl_cache($templates, \''.$forum['xthreads_tplprefix'].'\', $this);
				}
				
				function get($title, $eslashes=1, $htmlcomments=1) {
					xthreads_tpl_get($this, $title, \''.$forum['xthreads_tplprefix'].'\');
					return parent::get($title, $eslashes, $htmlcomments);
				}
			');
			/*eval('
				class xthreads_tpl extends '.get_class($templates).' {
					function xthreads_tpl(&$o) {
						foreach(get_object_vars($o) as $k => $v)
							$this->$k = $v;
					}
					
					function cache($templates) {
						xthreads_tpl_cache($templates, \''.$forum['xthreads_tplprefix'].'\', $this);
					}
					
					function get($title, $eslashes=1, $htmlcomments=1) {
						xthreads_tpl_get($this, $title, \''.$forum['xthreads_tplprefix'].'\');
						return parent::get($title, $eslashes, $htmlcomments);
					}
				}
			');
			$templates = new xthreads_tpl($templates);*/
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
		if($forum['xthreads_force_postlayout']) {
			$mybb->settings['postlayout'] = $forum['xthreads_force_postlayout'];
		}
	}
	if($current_page == 'index.php' || $current_page == 'forumdisplay.php') {
		global $plugins;
		require_once MYBB_ROOT.'inc/xthreads/xt_forumdhooks.php';
		$plugins->add_hook('forumdisplay_start', 'xthreads_forumdisplay');
		$plugins->add_hook('build_forumbits_forum', 'xthreads_tpl_forumbits');
		xthreads_global_forumbits_tpl();
	}
}

function xthreads_tpl_cache(&$t, $prefix, &$obj) {
	if(!$t) return;
	global $db, $theme;
	
	$sql = '';
	$ta = explode(',', $t);
	foreach($ta as &$tpl) {
		$tpl = trim($tpl);
		$sql .= ',"'.$tpl.'","'.$prefix.$tpl.'"';
	}
	$query = $db->simple_select('templates', 'title,template', 'title IN (""'.$sql.') AND sid IN ("-2","-1","'.$theme['templateset'].'")', array('order_by' => 'sid', 'order_dir' => 'asc'));
	while($template = $db->fetch_array($query))
		$obj->cache[$template['title']] = $template['template'];
	$db->free_result($query);
	
	// now override default templates
	foreach($ta as &$tpl) {
		if(isset($obj->cache[$prefix.$tpl]))
			$obj->cache[$tpl] =& $obj->cache[$prefix.$tpl];
		elseif(!isset($obj->cache[$tpl])) // we'll add a possible optimisation thing here that MyBB doesn't do :P
			$obj->cache[$tpl] = '';
	}
}
function xthreads_tpl_get(&$obj, &$t, $prefix) {
	if(!isset($obj->cache[$t])) {
		// template not loaded, load it
		if($GLOBALS['mybb']->debug_mode)
			$obj->uncached_templates[$t] = $t;
		$obj->cache($t);
		/*
		$obj->cache($t.','.$prefix.$t);
		if(isset($obj->cache[$prefix.$t]))
			$obj->cache[$t] =& $obj->cache[$prefix.$t];
		elseif(!isset($obj->cache[$t])) // template doesn't exist?  make blank so we don't query again
			$obj->cache[$t] = '';
		*/
	}
}
/* old func
function xthreads_tpl_get(&$obj, &$t, $prefix) {
	if(!isset($obj->cache[$prefix.$t])) {
		if(!isset($obj->cache[$t])) {
			// template not loaded, load it
			if($GLOBALS['mybb']->debug_mode)
				$obj->uncached_templates[$t] = $t;
			$obj->cache($t.','.$prefix.$t);
			// template doesn't exist?  make blank so we don't query again
			if(!isset($obj->cache[$t]))
				$obj->cache[$t] = '';
			// if custom template exists, use it
			if(isset($obj->cache[$prefix.$t]))
				$t = $prefix.$t;
			else
				$obj->cache[$prefix.$t] = '';
		}
		//otherwise, we simply just don't have a custom template, so use default
	} else {
		// custom template exists, hack system to use it
		$t = $prefix.$t;
	}
}*/


function xthreads_handle_uploads() {
	global $mybb, $current_page;
	if($mybb->request_method == 'post' && ($current_page == 'newthread.php' || $current_page == 'editpost.php')) {
		if($current_page == 'editpost.php') {
			global $thread;
			// check if first post
			if(!$thread) {
				$post = get_post(intval($mybb->input['pid']));
				if(!empty($post))
					$thread = get_thread($post['tid']);
				if(empty($thread)) return;
			}
			if($thread['firstpost'] != $post['pid'])
				return;
		}
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

// TODO: test displayed fields in search (posts view)

// TODO: admin logs - use proper text
// TODO: silent upgrader
// TODO: child threads?
// TODO: check internal db state / data consistency function in admincp
/* - unreferenced attachments in xtattachments (ignore orphaned attachments)
   - referenced attachments in threadfields_data (check aid, tid, field, md5hash)
   - non existent attachments + file system scan
   - thumbnail integrity checks
   - validate file magic, image (+dimensions), size + extensions for xtattachments
   - threadfields_data - invalid tid references
   - threadfields <> threadfields_data fields mismatch
   - ensure threadfields_data field types are correct + right indexes are being used
   - invalid allowed forums? + editable gids
   - invalid values for editable, textmask, sanitize, inputtype
   - invalid states for fields, eg can't have allowfilter if inputtype is textarea
   - data missing when required field set in threadfields_data
 */
// TODO: better file upload URL/file switch + remove attachment system

// TODO: make the forumbits template prefix feature loader a bit smarter
// TODO: forum admin -> separate XThreads options into a separate table
// TODO: format/hide posts in newreply's Thread Review?

// TODO: FILE_URL input (+ validation that input is valid URL)
// TODO: xtattachment load user session + wol patch
// TODO: include stuff for forumdisplay_threadlist template to include inline search with listboxes?

// TODO: override settings per forum
/*
	- max avatar display dims
	- threaded view
	- show quick reply
	- show multiquote
	- show similar threads
	
	- replies/views for hot topic
	- announcement limit
	
	- min/max post length
	- max img+attach per post
	- show attachment as img/thumb/download link
	- edit time limit
	- word wrapping
	- max poll length/options
*/

// TODO: template prefixes in WOL?
// TODO: colspan offset for forumdisplay??
// TODO: newthread/editpost input field display - tabordering

// TODO: implement data types?
// TODO: forumdisplay threadfield sorting
// TODO: default forumdisplay sorting/filtering

// TODO: extra postfields?
// TODO: search - also filter by threadfields?

// TODO: preparse stuff

function xthreads_sanitize_disp(&$s, &$tfinfo, $mename=null) {
	if($s === '' || $s === null) {
		if($tfinfo['blankval']) $s = eval_str($tfinfo['blankval']);
		return;
	}
	
	if($tfinfo['inputtype'] == XTHREADS_INPUT_FILE || ($tfinfo['inputtype'] == XTHREADS_INPUT_FILE_URL && !preg_match('~^[a-z]+\://~i', $s))) {
		global $mybb, $xta_cache;
		// attached file
		if(!$s) {
			if($tfinfo['blankval']) $s = array('value' => eval_str($tfinfo['blankval']));
			return;
		}
		if(!is_numeric($s) || !isset($xta_cache[$s]))
			return;
		$s = $xta_cache[$s];
		$s['downloads_friendly'] = my_number_format($s['downloads']);
		//$s = unserialize($s);
		//$s['thumbnail'] = htmlspecialchars_uni($s['thumbnail']);
		//$s['thumbext'] = '&amp;thumbnail=1';
		//if($s['thumbnail'] == 'SMALL') $s['thumbext'] = '';
		$s['filename'] = htmlspecialchars_uni($s['filename']);
		$s['uploadmime'] = htmlspecialchars_uni($s['uploadmime']);
		if(!$s['updatetime']) $s['updatetime'] = $s['uploadtime'];
		$s['filesize_friendly'] = get_friendly_size($s['filesize']);
		$s['md5hash'] = unpack('H*', $s['md5hash']);
		$s['md5hash'] = reset($s['md5hash']); // dunno why list($s['md5hash']) = unpack(...) doesn't work... - maybe need list($dummy, $s['md5hash']) = unpack() ?
		$s['url'] = 'xthreads_attach.php/'.$s['aid'].'_'.$s['updatetime'].'_'.substr($s['attachname'], 0, 8).'/'.$s['md5hash'].'/'.urlencode($s['filename']);
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
		
		$s['value'] = '';
		if($tfinfo['dispformat']) {
			$tr = array();
			foreach($s as $k => &$v)
				$tr['{'.strtoupper($k).'}'] =& $v;
			$s['value'] = strtr(eval_str($tfinfo['dispformat']), $tr);
		}
	}
	else {
		/* $fmtmap = null;
		if($tfinfo['formatmap']) { // cache map format in case of multivals
			$fmtmap = unserialize($tfinfo['formatmap']);
		} */
		
		if($tfinfo['multival']) {
			$vals = explode("\n", str_replace("\r", '', $s));
			foreach($vals as &$v) {
				$raw_v = $v;
				xthreads_sanitize_disp_field($v, $tfinfo, $tfinfo['formatmap'], $mename);
				if($tfinfo['dispitemformat']) {
					$v = strtr(eval_str($tfinfo['dispitemformat']), array(
						'{VALUE}' => $v, 
						'{RAWVALUE}' => $raw_v, 
					));
				}
			}
			$s = implode($tfinfo['multival'], $vals);
			if($tfinfo['dispformat']) {
				$s = str_replace('{VALUE}', $s, eval_str($tfinfo['dispformat']));
			}
		}
		else {
			$raw_s = $s;
			xthreads_sanitize_disp_field($s, $tfinfo, $tfinfo['formatmap'], $mename);
			if($tfinfo['dispformat']) {
				$s = strtr(eval_str($tfinfo['dispformat']), array(
					'{VALUE}' => $s, 
					'{RAWVALUE}' => $raw_s, 
				));
			}
		}
		
	}
}

function xthreads_sanitize_disp_field(&$v, &$tfinfo, &$fmtmap, $mename) {
	if(isset($fmtmap) && isset($fmtmap[$v])) {
		$v = eval_str($fmtmap[$v]); // not sanitized obviously
		return;
	}
	
	$type = $tfinfo['sanitize'];
	switch($type & XTHREADS_SANITIZE_MASK) {
		case XTHREADS_SANITIZE_HTML:
			$v = htmlspecialchars_uni($v);
			return;
		case XTHREADS_SANITIZE_HTML_NL:
			$v = nl2br(htmlspecialchars_uni($v));
			return;
		case XTHREADS_SANITIZE_PARSER:
			global $parser;
			if(!is_object($parser)) {
				require_once MYBB_ROOT.'inc/class_parser.php';
				$parser = new postParser;
			}
			$v = $parser->parse_message($v, array(
				'nl2br' => ($type & XTHREADS_SANITIZE_PARSER_NL2BR ?1:0),
				'filter_badwords' => ($type & XTHREADS_SANITIZE_PARSER_NOBADW ?1:0),
				'allow_html' => ($type & XTHREADS_SANITIZE_PARSER_HTML ?1:0),
				'allow_mycode' => ($type & XTHREADS_SANITIZE_PARSER_MYCODE ?1:0),
				'allow_imgcode' => ($type & XTHREADS_SANITIZE_PARSER_MYCODEIMG ?1:0),
				'allow_smilies' => ($type & XTHREADS_SANITIZE_PARSER_SMILIES ?1:0),
				'me_username' => $mename
			));
			return;
	}
}

function eval_str(&$s) {
	if(strpos($s, '{$') === false) return $s;
	//return eval('return "'.strtr(preg_replace('~\\{\\$([a-zA-Z_0-9]+)~', '{$GLOBALS[\'$1\']', $s), array('\\' => '\\\\', '"' => '\\"')).'";');
	// cause of PHP's f***ing magic quotes, we need a second eval
	return preg_replace('~\\{\\$([a-zA-Z_0-9]+)((-\\>[a-zA-Z_0-9]+|\\[[\'"]?[a-zA-Z_ 0-9]+[\'"]?\\])*)\\}~e', 'eval("return \\\\$GLOBALS[\'$1\']".str_replace("\\\\\'", "\'", "$2").";")', $s);
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


function xthreads_get_xta_cache(&$tf, &$tids, $posthash='') {
	if(!$tids) return;
	// our special query needed to get download counts across
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




if(!function_exists('control_object')) {
	function control_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		eval('class '.$newname.' extends '.get_class($obj).' {
			function '.$newname.'(&$o) {
				foreach(get_object_vars($o) as $k => $v)
					$this->$k = $v;
			}'.$code.'
		}');
		$obj = new $newname($obj);
	}
}
// improved version of control_object which morphs an object into the supplied class name, copying all variables (including private!) across
// only problem with this method is that private/protected _resources_ won't get copied
if(!function_exists('morph_object')) {
	function morph_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objmorph_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
		if(substr($objserial, 0, $checkstr_len) == $checkstr) {
			$vars = get_object_vars($obj);
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			foreach($vars as $k => &$v) // need to copy to ensure resources get across (but won't get private/protected vars unfortunately)
				$obj->$k = $v;
		}
		// else not a valid object or PHP serialize has changed
	}
}
/* if(!function_exists('morph_object')) {
	function morph_object(&$obj, $name) {
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		eval('class __'.$name.' extends '.$classname.' { }');
		if(substr($objserial, 0, $checkstr_len) == $checkstr)
			$obj = unserialize('O:'.strlen($name).':"'.$name.'":'.substr($objserial, $checkstr_len));
		// else not a valid object or PHP serialize has changed
	}
} */

/*
function control_object(&$obj, $funcs) {
	static $cnt = 0;
	$newname = '_objcont_'.(++$cnt);
	
	$code = '';
	foreach($funcs as $k => &$f) {
		if(is_array($f['args'])) {
			$callargs = $funcargs = $comma = '';
			foreach($f['args'] as $arg => &$def) {
				$callargs .= $comma.'$'.$arg;
				if(isset($def)) $callargs .= '='.$def;
				$comma = ', ';
			}
			if(!empty($f['args']))
				$funcargs = ', $'.implode(', $', array_keys($f['args']));
			$code .= '
				function '.$k.'('.$callargs.') {
					'.($f['pre'] ? 'if(($ret = '.$f['pre'].'($this'.$funcargs.')) !== null) return $ret;':'').'
					'.($f['post'] ? $f['post'].'(':'return ').'parent::'.$k.'('.$funcargs.')'.($f['post'] ? ', $this'.$funcargs.')':'').';
				}
			';
		}
		else {
			$code .= '
				function '.$k.'() {
					$args = $args2 = func_get_args();
					array_unshift($args2, $this);
					'.($f['pre'] ? 'if(($ret = call_user_func_array(\''.$f['pre'].'\', $args2)) !== null) return $ret;':'').'
					'.($f['post'] ? '$ret =':'return').' call_user_func_array(array(parent, \''.$k.'\'), $args);
					'.($f['post'] ? '
						array_unshift($args2, $ret);
						return call_user_func_array(\''.$f['post'].'\', $args2);
					':'').'
				}
			';
		}
	}
	
	eval('class '.$newname.' extends '.get_class($obj).' {
		function '.$newname.'(&$o) {
			foreach(get_object_vars($o) as $k => $v)
				$this->$k = $v;
		}'.$code.'
	}');
	$obj = new $newname($obj);
}
*/
