<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


function xthreads_showthread() {
	global $thread, $forum;
	global $threadfield_cache, $db, $threadfields;
	
	$threadfields = array();
	if(!isset($threadfield_cache))
		$threadfield_cache = xthreads_gettfcache($GLOBALS['fid']);
	if(!empty($threadfield_cache)) {
		// just do an extra query to grab the threadfields
		$threadfields = $db->fetch_array($db->simple_select('threadfields_data', '`'.implode('`,`', array_keys($threadfield_cache)).'`', 'tid='.$thread['tid']));
		if(empty($threadfields)) return;
		foreach($threadfields as $k => &$v) {
			xthreads_get_xta_cache($threadfield_cache[$k], $thread['tid']);
			xthreads_sanitize_disp($v, $threadfield_cache[$k], $thread['username']);
		}
	}
	
	if($forum['xthreads_postsperpage']) {
		$GLOBALS['mybb']->settings['postsperpage'] = $forum['xthreads_postsperpage'];
	}
}

function xthreads_showthread_firstpost() {
	global $mybb, $templatelist;
	// don't do this if using threaded mode
	if(isset($mybb->input['mode']))
		$threaded = ($mybb->input['mode'] == 'threaded');
	elseif(!empty($mybb->user['threadmode']))
		$threaded = ($mybb->user['threadmode'] == 'threaded');
	else
		$threaded = ($mybb->settings['threadusenetstyle'] == 1);
	
	if(!$threaded) {
		global $db, $templates;
		xthreads_firstpost_tpl_preload();
		$templatelist .= ',showthread_noreplies';
		
		function xthreads_tpl_firstpost_moveout() {
			global $posts;
			static $done = false;
			if($done) return;
			$done = true;
			$GLOBALS['first_post'] = $posts;
			$posts = '';
			$GLOBALS['plugins']->remove_hook('showthread_start', 'xthreads_tpl_postbithack');
		}
		function xthreads_tpl_firstpost_noreplies() {
			global $posts;
			// execute this in case there's only one post in the thread
			xthreads_tpl_firstpost_moveout();
			if(!$posts) {
				eval('$posts = "'.$GLOBALS['templates']->get('showthread_noreplies').'";');
			}
		}
		function xthreads_tpl_postbitrestore() {
			global $templates, $xthreads_postbit_templates, $page;
			foreach($xthreads_postbit_templates as &$t) {
				$pbname = substr($t, 7);
				if($templates->cache['postbit_first'.$pbname]) {
					$templates->cache[$t] = $templates->cache['backup_postbit'.$pbname.'_backup__'];
				}
			}
			// whilst we're here, add the necessary plugin hook
			$GLOBALS['plugins']->add_hook('postbit', 'xthreads_tpl_firstpost_moveout');
			$GLOBALS['plugins']->add_hook('showthread_linear', 'xthreads_tpl_firstpost_noreplies');
			
			// don't forget to fix the postcounter too!
			if($page > 1) {
				global $mybb;
				if(!$mybb->settings['postsperpage'])
					$mybb->settings['postsperpage'] = 20;
				$GLOBALS['postcounter'] = $mybb->settings['postsperpage']*($page-1);
			}
		}
		$GLOBALS['plugins']->add_hook('showthread_start', 'xthreads_tpl_postbithack');
		
		// *sigh* no other way to do this other than to hack the templates object again... >_>
		control_object($templates, '
			function get($title, $eslashes=1, $htmlcomments=1) {
				static $done=false;
				if(!$done && ($title == \'postbit\' || $title == \'postbit_classic\')) {
					$done = true;
					$r = parent::get($title, $eslashes, $htmlcomments);
					xthreads_tpl_postbitrestore();
					return str_replace(\'{$post_extra_style}\', \'border-top-width: 0;\', $r);
				} else
					return parent::get($title, $eslashes, $htmlcomments);
			}
		');
		/*eval('
			class xthreads_tpl_firstpost extends '.get_class($templates).' {
				function xthreads_tpl_firstpost(&$o) {
					foreach(get_object_vars($o) as $k => $v)
						$this->$k = $v;
				}
				
				function get($title, $eslashes=1, $htmlcomments=1) {
					static $done=false;
					if(!$done && ($title == \'postbit\' || $title == \'postbit_classic\')) {
						$done = true;
						$r = parent::get($title, $eslashes, $htmlcomments);
						xthreads_tpl_postbitrestore();
						return str_replace(\'{$post_extra_style}\', \'border-top-width: 0;\', $r);
					} else
						return parent::get($title, $eslashes, $htmlcomments);
				}
			}
		');
		$templates = new xthreads_tpl_firstpost($templates);*/
		
		// and now actually do the hack to display the first post on each page
		control_object($db, '
			function simple_select($table, $fields=\'*\', $conditions=\'\', $options=array()) {
				static $done=false;
				if(!$done && $table == \'posts p\' && $fields == \'p.pid\' && $options[\'order_by\'] == \'p.dateline\') {
					$done = true;
					if($options[\'limit_start\']) $this->xthreads_firstpost_hack = true;
				}
				return parent::simple_select($table, $fields, $conditions, $options);
			}
			function fetch_array($query) {
				if($this->xthreads_firstpost_hack) {
					$this->xthreads_firstpost_hack = false;
					return array(\'pid\' => $GLOBALS[\'thread\'][\'firstpost\']);
				}
				return parent::fetch_array($query);
			}
		');
		$db->xthreads_firstpost_hack = false;
		/*eval('
			class xthreads_db_firstpost extends '.get_class($db).' {
				function xthreads_db_firstpost(&$o) {
					foreach(get_object_vars($o) as $k => $v)
						$this->$k = $v;
					$this->xthreads_firstpost_hack = false;
				}
				
				function simple_select($table, $fields=\'*\', $conditions=\'\', $options=array()) {
					static $done=false;
					if(!$done && $table == \'posts p\' && $fields == \'p.pid\' && $options[\'order_by\'] == \'p.dateline\') {
						$done = true;
						if($options[\'limit_start\']) $this->xthreads_firstpost_hack = true;
					}
					return parent::simple_select($table, $fields, $conditions, $options);
				}
				function fetch_array($query) {
					if($this->xthreads_firstpost_hack) {
						$this->xthreads_firstpost_hack = false;
						return array(\'pid\' => $GLOBALS[\'thread\'][\'firstpost\']);
					}
					return parent::fetch_array($query);
				}
			}
		');
		$db = new xthreads_db_firstpost($db);*/
		
		// this is a dirty hack we probably shouldn't be relying on (but eh, it works)
		// basically '-0' evaluates to true, effectively skipping the check in build_postbit()
		// but when incremented, becomes 1
		$GLOBALS['postcounter'] = '-0';
	}
}


function xthreads_firstpost_tpl_preload() {
	global $xthreads_postbit_templates, $templatelist;
	$xthreads_postbit_templates = array('postbit','postbit_attachments','postbit_attachments_attachment','postbit_attachments_attachment_unapproved','postbit_attachments_images','postbit_attachments_images_image','postbit_attachments_thumbnails','postbit_attachments_thumbnails_thumbnail','postbit_author_guest','postbit_author_user','postbit_avatar','postbit_away','postbit_classic','postbit_delete_pm','postbit_edit','postbit_editedby','postbit_email','postbit_find','postbit_forward_pm','postbit_gotopost','postbit_groupimage','postbit_ignored','postbit_inlinecheck','postbit_iplogged_hiden','postbit_iplogged_show','postbit_multiquote','postbit_offline','postbit_online','postbit_pm','postbit_posturl','postbit_quickdelete','postbit_quote','postbit_reply_pm','postbit_replyall_pm','postbit_report','postbit_reputation','postbit_seperator','postbit_signature','postbit_warn','postbit_warninglevel','postbit_www');
	foreach($xthreads_postbit_templates as &$t) {
		$templatelist .= ',postbit_first'.substr($t, 7);
	}
}
function xthreads_tpl_postbithack() {
	global $templates, $xthreads_postbit_templates;
	if(!$templates->cache['postbit'])
		$templates->cache(implode(',', $xthreads_postbit_templates));
	foreach($xthreads_postbit_templates as &$t) {
		$pbname = substr($t, 7);
		if($templates->cache['postbit_first'.$pbname]) {
			$templates->cache['backup_postbit'.$pbname.'_backup__'] = $templates->cache[$t];
			$templates->cache[$t] =& $templates->cache['postbit_first'.$pbname];
		}
	}
}
