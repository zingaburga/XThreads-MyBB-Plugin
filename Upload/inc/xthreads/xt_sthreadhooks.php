<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


function xthreads_showthread() {
	global $thread, $threadfields, $threadfields_display, $threadfields_display_rows, $templates, $theme, $threadfield_cache;
	// just do an extra query to grab the threadfields
	xthreads_get_threadfields($thread['tid'], $threadfields, false, $thread);
	
	// generate stuff to show on showthread
	// $threadfield_cache should always be set here
	$threadfields_display = $threadfields_display_rows = '';
	if(!empty($threadfields)) foreach($threadfields as $k => &$val) {
		$tf =& $threadfield_cache[$k];
		if($tf['hidefield'] & XTHREADS_HIDE_THREAD) continue;
		if($tf['inputtype'] == XTHREADS_INPUT_FILE)
			$value =& $val['value'];
		else
			$value =& $val;
		$title = htmlspecialchars_uni($tf['title']);
		$bgcolor = alt_trow();
		eval('$threadfields_display_rows .= "'.$templates->get('showthread_threadfield_row').'";');
	} unset($value);
	if($threadfields_display_rows)
		eval('$threadfields_display = "'.$templates->get('showthread_threadfields').'";');
	
	/*
	global $mybb;
	if($mybb->input['action'] == 'xtnext' || $mybb->input['action'] == 'xtprev') {
		global $db;
		$add_join = false;
		
		$nf = 'lastpost';
		switch($mybb->input['order']) {
			case 'subject':
			case 'replies':
			case 'views':
				$nf = $mybb->input['order'];
				break;
			
			case 'starter':  $nf = 'username'; break;
			case 'started':  $nf = 'dateline'; break;
			case 'rating': // this is f***ing slow, but then, that's the best MyBB can do
				unset($nf);
				$nextfield = 'IF(t.numratings=0, 0, t.totalratings / t.numratings)';
				$curval = ($thread['numratings'] ? $thread['totalratings'] / $thread['numratings'] : 0);
				break;
			
			// more XThreads sort options
			// TODO: prefix, icon
			
			case 'lastposter':
			case 'numratings':
			case 'attachmentcount':
				$nf = $mybb->input['order'];
				break;
			
			
			default:
				// TODO: threadfields sorting
				if(substr($mybb->input['order'], 0, 3) == 'tf_') {
					$add_join = true;
					
				} elseif(substr($mybb->input['order'], 0, 4) == 'tfa_') {
					$add_join = true;
					
				}
				break;
			
		}
		if(isset($nf)) {
			if($add_join) {
				$nextfield = 'tfd.`'.$nf.'`';
				$curval = $threadfields[$nf];
			} else {
				$nextfield = 't.'.$nf;
				$curval = $thread[$nf];
			}
		}
		if(is_string($curval))
			$curval = '"'.$db->escape_string($curval).'"';
		$cond = $nextfield.($mybb->input['action']=='xtprev' ? '<':'>').$curval;
		
		// TODO: additional filtering
		
		$cond .= ' AND t.fid='.$thread['fid'].' AND t.visible=1 AND t.closed NOT LIKE "moved|%"';
		$order_dir = ($mybb->input['action'] == 'xtprev' ? 'desc':'asc');
		
		$join = '';
		if($add_join)
			$join = 'LEFT JOIN '.$db->table_prefix.'threadfields_data tfd ON t.tid=tfd.tid';
		$query = $db->query('
			SELECT t.tid FROM '.$db->table_prefix.'threads t
			'.$join.'
			WHERE '.$cond.'
			ORDER BY '.$nextfield.' '.$order_dir.', t.tid '.$order_dir.'
			LIMIT 1
		');
		$nexttid = $db->fetch_field($query, 'tid');
		if(!$nexttid)
			error($GLOBALS['lang']->error_nonextoldest);
		
		header('Location: '.htmlspecialchars_decode(get_thread_link($nexttid)));
		exit;
	}
	*/
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
	if($threaded) return;
	
	global $db;
	xthreads_firstpost_tpl_preload();
	$templatelist .= ',showthread_noreplies';
	
	function xthreads_tpl_firstpost_moveout() {
		global $posts;
		static $done = false;
		if($done) return;
		$done = true;
		$GLOBALS['first_post'] = $posts;
		$posts = '';
		// uh... what's this next line here for again?
		//$GLOBALS['plugins']->remove_hook('showthread_start', 'xthreads_showthread_firstpost_hack');
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
			if(!$pbname) $pbname = '';
			if(isset($templates->cache['postbit_first'.$pbname]) && !isset($templates->non_existant_templates['postbit_first'.$pbname])) {
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
	
	function xthreads_showthread_firstpost_hack() {
		if(xthreads_tpl_postbithack()) {
			// *sigh* no other way to do this other than to hack the templates object again... >_>
			control_object($GLOBALS['templates'], '
				function get($title, $eslashes=1, $htmlcomments=1) {
					static $done=false;
					if(!$done && ($title == \'postbit\' || $title == \'postbit_classic\')) {
						$done = true;
						$r = parent::get($title, $eslashes, $htmlcomments);
						xthreads_tpl_postbitrestore();
						//return str_replace(\'{$post_extra_style}\', \'border-top-width: 0;\', $r);
						return \'".($post_extra_style="border-top-width: 0;"?"":"")."\'.$r;
					} else
						return parent::get($title, $eslashes, $htmlcomments);
				}
			');
		}
	}
	//$GLOBALS['plugins']->add_hook('showthread_start', 'xthreads_showthread_firstpost_hack');
	
	
	// and now actually do the hack to display the first post on each page
	if($GLOBALS['forum']['xthreads_firstpostattop']) { // would be great if we had a reliable way to determine if we're on the first page here
		$db->xthreads_firstpost_hack = false;
		
		// this is a dirty hack we probably shouldn't be relying on (but eh, it works)
		// basically '-0' evaluates to true, effectively skipping the check in build_postbit()
		// but when incremented, becomes 1
		$GLOBALS['postcounter'] = '-0';
		
		$extra_code = '
			function fetch_array($query, $resulttype=MYSQL_ASSOC) {
				if($this->xthreads_firstpost_hack) {
					$this->xthreads_firstpost_hack = false;
					return array(\'pid\' => $GLOBALS[\'thread\'][\'firstpost\']);
				}
				return parent::fetch_array($query);
			}
		';
		$firstpost_hack_code = 'if($options[\'limit_start\']) $this->xthreads_firstpost_hack = true;';
	} else {
		$extra_code = '';
		$firstpost_hack_code = 'if(!$options[\'limit_start\'])';
	}
	control_object($db, '
		function simple_select($table, $fields=\'*\', $conditions=\'\', $options=array()) {
			static $done=false;
			if(!$done && $table == \'posts p\' && $fields == \'p.pid\' && $options[\'order_by\'] == \'p.dateline\') {
				$done = true;
				'.$firstpost_hack_code.'
					xthreads_showthread_firstpost_hack();
			}
			return parent::simple_select($table, $fields, $conditions, $options);
		}
		'.$extra_code.'
	');
}


function xthreads_firstpost_tpl_preload() {
	global $xthreads_postbit_templates, $templatelist;
	$xthreads_postbit_templates = array('postbit','postbit_attachments','postbit_attachments_attachment','postbit_attachments_attachment_unapproved','postbit_attachments_images','postbit_attachments_images_image','postbit_attachments_thumbnails','postbit_attachments_thumbnails_thumbnail','postbit_author_guest','postbit_author_user','postbit_avatar','postbit_away','postbit_classic','postbit_delete_pm','postbit_edit','postbit_editedby','postbit_email','postbit_find','postbit_forward_pm','postbit_gotopost','postbit_groupimage','postbit_ignored','postbit_inlinecheck','postbit_iplogged_hiden','postbit_iplogged_show','postbit_multiquote','postbit_offline','postbit_online','postbit_pm','postbit_posturl','postbit_quickdelete','postbit_quote','postbit_reply_pm','postbit_replyall_pm','postbit_report','postbit_reputation','postbit_seperator','postbit_signature','postbit_warn','postbit_warninglevel','postbit_www');
	foreach($xthreads_postbit_templates as &$t) {
		$templatelist .= ',postbit_first'.substr($t, 7);
	}
}
// returns true if postbit_first used at all
function xthreads_tpl_postbithack() {
	global $templates, $xthreads_postbit_templates;
	$modified = false;
	if(!isset($templates->cache['postbit']))
		$templates->cache(implode(',', $xthreads_postbit_templates));
	foreach($xthreads_postbit_templates as &$t) {
		$pbname = substr($t, 7);
		if(!$pbname) $pbname = '';
		if(isset($templates->cache['postbit_first'.$pbname]) && !isset($templates->non_existant_templates['postbit_first'.$pbname])) {
			$templates->cache['backup_postbit'.$pbname.'_backup__'] = $templates->cache[$t];
			$templates->cache[$t] =& $templates->cache['postbit_first'.$pbname];
			$modified = true;
		}
	}
	return $modified;
}
