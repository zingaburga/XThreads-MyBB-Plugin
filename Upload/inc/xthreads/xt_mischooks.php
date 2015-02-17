<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_search() {
	global $db, $threadfield_cache;
	$threadfield_cache = xthreads_gettfcache();
	if(!empty($threadfield_cache)) {
		function xthreads_search_dbhook(&$s, &$db) {
			global $threadfield_cache;
			
			$fields = '';
			foreach($threadfield_cache as &$v)
				$fields .= ', tfd.`'.$v['field'].'` AS `xthreads_'.$v['field'].'`';
			
			$s = strtr($s, array(
				'FROM '.TABLE_PREFIX => $fields.' FROM '.TABLE_PREFIX,
				'LEFT JOIN '.TABLE_PREFIX.'users u' => 'LEFT JOIN `'.$db->table_prefix.'threadfields_data` tfd ON t.tid=tfd.tid LEFT JOIN '.TABLE_PREFIX.'users u',
			));
		}
		control_object($db, '
			function query($string, $hide_errors=0, $write_query=0) {
				static $done=false;
				if(!$done && !$write_query && strpos($string, \'SELECT \') && strpos($string, \'u.username AS userusername\') && strpos($string, \'LEFT JOIN '.TABLE_PREFIX.'users u ON \')) {
					$done = true;
					xthreads_search_dbhook($string, $this);
				}
				return parent::query($string, $hide_errors, $write_query);
			}
		');
	}
	
	global $cache, $plugins, $forum_tpl_prefixes;
	// cache templates - we've got not much choice but to cache all forums with custom template prefixes
	$cachelist = '';
	$forum_tpl_prefixes = xthreads_get_tplprefixes(true);
	foreach($forum_tpl_prefixes as $pref) {
		$pref = $db->escape_string($pref);
		$cachelist .= ($cachelist?',':'').$pref.'search_results_posts_post,'.$pref.'search_results_threads_thread';
	}
	if($cachelist !== '') $GLOBALS['templates']->cache($cachelist);
	
	$plugins->add_hook('search_results_post', 'xthreads_search_result_post');
	$plugins->add_hook('search_results_thread', 'xthreads_search_result_thread');
}

function xthreads_search_result(&$data, $tplname) {
	global $threadfields, $threadfield_cache, $forumcache, $mybb;
	
	// need to set these variables before doing threadfields stuff!
	$data['threaddate'] = my_date($mybb->settings['dateformat'], $data['dateline']);
	$data['threadtime'] = my_date($mybb->settings['timeformat'], $data['dateline']);
	xthreads_set_threadforum_urlvars('thread', $data['tid']);
	xthreads_set_threadforum_urlvars('forum', $data['fid']);
	
	if(!empty($threadfield_cache)) {
		// make threadfields array
		$threadfields = array(); // clear previous threadfields
		
		if($GLOBALS['thread_ids']) $tidlist =& $GLOBALS['thread_ids'];
		elseif($GLOBALS['tids']) $tidlist =& $GLOBALS['tids'];
		else $tidlist = '';
		
		foreach($threadfield_cache as $k => &$v) {
			if($v['forums'] && strpos(','.$v['forums'].',', ','.$data['fid'].',') === false)
				continue;
			
			xthreads_get_xta_cache($v, $tidlist);
			
			$threadfields[$k] =& $data['xthreads_'.$k];
			xthreads_sanitize_disp($threadfields[$k], $v, ($data['username'] !== '' ? $data['username'] : $data['userusername']));
		}
	}
	// template hack
	xthreads_portalsearch_cache_hack($GLOBALS['forum_tpl_prefixes'][$data['fid']], $tplname);
}
function xthreads_search_result_post() {
	xthreads_search_result($GLOBALS['post'], 'search_results_posts_post');
}
function xthreads_search_result_thread() {
	global $thread;
	xthreads_search_result($thread, 'search_results_threads_thread');
	
	// fix for posts per page override
	$forum =& $GLOBALS['forumcache'][$thread['fid']];
	if($forum['xthreads_postsperpage'] && $forum['xthreads_postsperpage'] != $GLOBALS['mybb']->settings['postsperpage']) {
		// urgh, we have to reproduce MyBB code, how yuck
		global $threadpages, $morelink, $highlight, $templates, $lang;
		if($thread['posts'] > $forum['xthreads_postsperpage']) {
			$pagesstop = ceil($thread['posts'] / $forum['xthreads_postsperpage']);
			if($pagesstop != $thread['pages']) { // small optimisation
				$thread['pages'] = $pagesstop;
				$threadpages = $morelink = '';
				if($pagesstop > 4) {
					$pagesstop = 4;
					$page_link = get_thread_link($thread['tid'], $thread['pages']).$highlight;
					eval('$morelink = "'.$templates->get('forumdisplay_thread_multipage_more').'";');
				}
				for($i = 1; $i <= $pagesstop; ++$i) {
					$page_link = get_thread_link($thread['tid'], $i).$highlight;
					eval('$threadpages .= "'.$templates->get('forumdisplay_thread_multipage_page').'";');
				}
				eval('$thread[\'multipage\'] = "'.$templates->get('forumdisplay_thread_multipage').'";');
			}
		}
		else
			$thread['multipage'] = $threadpages = $morelink = '';
	}
}


function xthreads_portal() {
	global $threadfield_cache, $mybb;
	$threadfield_cache = xthreads_gettfcache();
	
	$fids = array_flip(array_map('intval', explode(',', $mybb->settings['portal_announcementsfid'])));
	$fields = '';
	foreach($threadfield_cache as $k => &$v) {
		$available = (!$v['forums']);
		if(!$available)
			foreach(explode(',', $v['forums']) as $fid) {
				if(isset($fids[$fid])) {
					$available = true;
					break;
				}
			}
		if($available)
			$fields .= ', tfd.`'.$v['field'].'` AS `xthreads_'.$v['field'].'`';
		else
			unset($threadfield_cache[$k]);
	}
	
	if($fields) {
		// do DB hack
		control_object($GLOBALS['db'], '
			function query($string, $hide_errors=0, $write_query=0) {
				static $done=false;
				if(!$done && !$write_query && strpos($string, \'SELECT t.*, t.username AS threadusername, u.username, u.avatar\')) {
					$done = true;
					$string = strtr($string, array(
						\'SELECT t.*, t.username AS threadusername, u.username, u.avatar\' => \'SELECT t.*, t.username AS threadusername, u.username, u.avatar'.$fields.'\',
						\'FROM '.TABLE_PREFIX.'threads t\' => \'FROM '.TABLE_PREFIX.'threads t LEFT JOIN '.TABLE_PREFIX.'threadfields_data tfd ON t.tid=tfd.tid\'
					));
				}
				return parent::query($string, $hide_errors, $write_query);
			}
		');
	}
}


function xthreads_portal_announcement() {
	static $doneinit = false;
	
	global $threadfield_cache, $announcement, $threadfields, $forum_tpl_prefixes;
	
	if(!$doneinit) {
		$doneinit = true;
		
		// cache templates
		$cachelist = '';
		$forum_tpl_prefixes = xthreads_get_tplprefixes(true, $GLOBALS['forum']);
		foreach($forum_tpl_prefixes as $pref) {
			$pref = $GLOBALS['db']->escape_string($pref);
			$cachelist .= ($cachelist?',':'').$pref.'portal_announcement,'.$pref.'portal_announcement_numcomments,'.$pref.'portal_announcement_numcomments_no';
		}
		if($cachelist !== '') $GLOBALS['templates']->cache($cachelist);
	}
	
	
	// following two lines not needed as we have $anndate and $anntime
	//$announcement['threaddate'] = my_date($mybb->settings['dateformat'], $announcement['dateline']);
	//$announcement['threadtime'] = my_date($mybb->settings['timeformat'], $announcement['dateline']);
	xthreads_set_threadforum_urlvars('thread', $announcement['tid']);
	xthreads_set_threadforum_urlvars('forum', $announcement['fid']);
	
	if(!empty($threadfield_cache)) {
		// make threadfields array
		$threadfields = array(); // clear previous threadfields
		
		foreach($threadfield_cache as $k => &$v) {
			if($v['forums'] && strpos(','.$v['forums'].',', ','.$announcement['fid'].',') === false)
				continue;
			
			$tids = '0'.$GLOBALS['tids'];
			xthreads_get_xta_cache($v, $tids);
			
			$threadfields[$k] =& $announcement['xthreads_'.$k];
			xthreads_sanitize_disp($threadfields[$k], $v, ($announcement['username'] !== '' ? $announcement['username'] : $announcement['threadusername']));
		}
	}
	// template hack
	$tplprefix =& $forum_tpl_prefixes[$announcement['fid']];
	xthreads_portalsearch_cache_hack($tplprefix, 'portal_announcement');
	if(!xthreads_empty($tplprefix)) {
		$tplname = $tplprefix.'portal_announcement_numcomments'.($announcement['replies']?'':'_no');
		if(!xthreads_empty($GLOBALS['templates']->cache[$tplname])) {
			global $lang, $mybb;
			// re-evaluate comments template
			eval('$GLOBALS[\'numcomments\'] = "'.$GLOBALS['templates']->get($tplname).'";');
		}
	}
}

function xthreads_portalsearch_cache_hack($tplpref, $tplname) {
	$tplcache =& $GLOBALS['templates']->cache;
	if(!xthreads_empty($tplpref) && isset($tplcache[$tplpref.$tplname])) {
		if(!isset($tplcache['backup_'.$tplname.'_backup__']))
			$tplcache['backup_'.$tplname.'_backup__'] = $tplcache[$tplname];
		$tplcache[$tplname] =& $tplcache[$tplpref.$tplname];
	}
	elseif(isset($tplcache['backup_'.$tplname.'_backup__']))
		$tplcache[$tplname] =& $tplcache['backup_'.$tplname.'_backup__'];
}

function xthreads_wol_patch(&$a) {
	global $lang, $thread_fid_map;
	global $forums, $threads, $posts, $attachments;
	$langargs = array();
	$user_activity =& $a['user_activity'];
	switch($user_activity['activity']) {
		case 'announcements':
		case 'forumdisplay':
		case 'newthread':
			$fid = $user_activity['fid'];
			$langargs = array(get_forum_link($fid), $forums[$fid]);
			// TODO: special forumdisplay linkto string?
			break;
		case 'attachment':
			$tid = $posts[$attachments[$user_activity['aid']]];
			$fid = $thread_fid_map[$tid];
			$langargs = array($user_activity['aid'], $threads[$tid], get_thread_link($tid));
			break;
		case 'newreply':
			if($user_activity['pid'])
				$user_activity['tid'] = $posts[$user_activity['pid']];
			// fall through
		case 'showthread':
			$fid = $thread_fid_map[$user_activity['tid']];
			$langargs = array(get_thread_link($user_activity['tid']), $threads[$user_activity['tid']], '');
			break;
		
		//case 'editpost': -
		//case 'newpoll':
		//case 'editpoll':
		//case 'showresults':
		//case 'vote':
		//case 'ratethread': -
		//case 'report':
		//case 'sendthread':
		
		/*
		case 'xtattachment':
			$a['location_name'] = $lang->sprintf($lang->xthreads_downloading_attachment, htmlspecialchars_uni($user_activity['location']), htmlspecialchars_uni($user_activity['filenamename']));
			// TODO: allow custom for this too
			return;
		*/
	}
	
	if(!$fid) return;
	global $forumcache;
	if(!is_array($forumcache)) $forumcache = $GLOBALS['cache']->read('forums');
	$wolstr =& $forumcache[$fid]['xthreads_wol_'.$user_activity['activity']];
	if(!xthreads_empty($wolstr)) {
		if(empty($langargs))
			$a['location_name'] = $wolstr;
		else {
			array_unshift($langargs, $wolstr);
			$a['location_name'] = call_user_func_array(array($lang, 'sprintf'), $langargs);
		}
	}
	
}

function xthreads_wol_patch_init(&$ua) {
	switch($ua['activity']) {
		case 'attachment':
		case 'newreply':
		case 'showthread':
			static $done_hook = false;
			if(!$done_hook) {
				$done_hook = true;
				// hook in to get thread_fid_map
				global $db;
				$GLOBALS['thread_fid_map'] = array();
				if($GLOBALS['mybb']->version_code >= 1500)
					$hook = '
						function query($string, $hide_errors=0, $write_query=0) {
							static $done=false;
							if(!$done && !$write_query && (substr(trim($string), 0, 73) == "SELECT t.fid, t.tid, t.subject, t.visible, p.displaystyle AS threadprefix" || substr(trim($string), 0, 80) == "SELECT t.uid, t.fid, t.tid, t.subject, t.visible, p.displaystyle AS threadprefix")) {
								$done = true;
								$this->xthreads_db_wol_hook = true;
							}
							return parent::query($string, $hide_errors, $write_query);
						}
						function simple_select($table, $fields="*", $conditions="", $options=array()) {
							if($this->xthreads_db_wol_hook) {
								$this->xthreads_db_wol_hook = false;
							}
							return parent::simple_select($table, $fields, $conditions, $options);
						}
					';
				else
					$hook = '
						function simple_select($table, $fields="*", $conditions="", $options=array()) {
							static $done=false;
							if($done && $this->xthreads_db_wol_hook) {
								$this->xthreads_db_wol_hook = false;
							}
							if(!$done && $table == "threads" && $fields == "fid,tid,subject,visible" && substr($conditions, 0, 7) == "tid IN(" && empty($options)) {
								$done = true;
								$this->xthreads_db_wol_hook = true;
							}
							return parent::simple_select($table, $fields, $conditions, $options);
						}
					';
				
				control_object($db, $hook.'
					function fetch_array($query, $resulttype=MYSQL_ASSOC) {
						if($this->xthreads_db_wol_hook) {
							$r = parent::fetch_array($query);
							$GLOBALS[\'thread_fid_map\'][$r[\'tid\']] = $r[\'fid\'];
							return $r;
						}
						return parent::fetch_array($query);
					}
				');
				$db->xthreads_db_wol_hook = false;
			}
			break;
		/* case 'unknown':
			// TODO: the following URL isn't guaranteed as query strings may be used
			if(($p = strpos($ua['location'], '/xthreads_attach.php/')) !== false) {
				// check if really is xtattach page
				if(strpos($ua['location'], '.php') > $p) {
					// yes, user isn't sticking this as an argument
					// TODO: parse URL for stuff
				}
			}
			break;
		*/
	}
}


function xthreads_fix_stats() {
	global $cache;
	function &xthreads_fix_stats_read($stats, $hard) {
		static $fix = null;
		if(!isset($fix) || $hard) {
			$fix = array('posts' => 0, 'threads' => 0);
			$forums = $GLOBALS['cache']->read('forums', $hard);
			$q = $comma = '';
			foreach($forums as &$f)
				if($f['xthreads_nostatcount']) {
					$q .= $comma.$f['fid'];
					$comma = ',';
				}
			
			// since MyBB doesn't cache forum counters, we have to query for it
			if($q) {
				global $db;
				$fix = $db->fetch_array($db->simple_select('forums', 'SUM(threads)+SUM(unapprovedthreads) AS threads, SUM(posts)+SUM(unapprovedposts) AS posts', 'fid IN ('.$q.')'));
			}
		}
		$stats['numposts'] -= $fix['posts'];
		$stats['numthreads'] -= $fix['threads'];
		return $stats;
	}
	control_object($cache, '
		function read($name, $hard=false) {
			if($name != "stats")
				return parent::read($name, $hard);
			else
				return xthreads_fix_stats_read(parent::read($name, $hard), $hard);
		}
	');
}

function xthreads_fix_stats_index() {
	if($GLOBALS['mybb']->settings['showindexstats'])
		xthreads_fix_stats();
}
function xthreads_fix_stats_portal() {
	if($GLOBALS['mybb']->settings['portal_showstats'])
		xthreads_fix_stats();
}
function xthreads_fix_stats_stats() {
	xthreads_fix_stats();
	// re-read the stats
	$GLOBALS['stats'] = $GLOBALS['cache']->read('stats');
}
function xthreads_fix_stats_usercp() {
	if(!$GLOBALS['mybb']->input['action'])
		xthreads_fix_stats();
}



// modified version of xthreads_breadcrumb_hack()
// because printthread.php just has to do things differently...
function xthreads_breadcrumb_hack_printthread() {
	global $pforumcache;
	//if(!is_array($pforumcache)) {
	// need to override because we want the 'xthreads_hidebreadcrumb' field
		global $forum, $db, $fid;
		$parlist = build_parent_list($fid, 'fid', 'OR', $forum['parentlist']);
		$query = $db->simple_select('forums', 'name, fid, pid, xthreads_hidebreadcrumb', $parlist, array('order_by' => 'pid, disporder'));
		while($forumnav = $db->fetch_array($query)) {
			$pforumcache[$forumnav['pid']][$forumnav['fid']] = $forumnav;
		}
		unset($forumnav, $forum, $fid); // unsetting the global references
	//}
	if(!is_array($pforumcache[0])) return;
	
	// do the same as xthreads_breadcrumb_hack() but in reverse
	foreach($pforumcache[0] as &$pforum) { // will only ever loop once
		if($pforum['pid']) continue; // paranoia
		
		// firstly, skip any hidden top-level parents
		$prevforum =& $pforum;
		while($prevforum && $prevforum['xthreads_hidebreadcrumb'] && !empty($pforumcache[$prevforum['fid']]))
			$prevforum =& xthreads_get_array_first($pforumcache[$prevforum['fid']]);
		
		if($prevforum) {
			if($prevforum['pid']) {
				$prevforum['pid'] = 0;
				$pforum = $prevforum;
			}
			
			$forum = null;
			if($pforumcache[$prevforum['fid']])
				$forum =& xthreads_get_array_first($pforumcache[$prevforum['fid']]);
			while($forum) {
				if(!$forum['xthreads_hidebreadcrumb']) {
					// rewrite parent fid (won't actually change if there's no hidden breadcrumbs in-between)
					$forum['pid'] = $prevforum['fid'];
					$pforumcache[$forum['pid']] = array($forum['fid'] => $forum);
					$prevforum =& $forum;
				}
				if(!$pforumcache[$forum['fid']]) {
					// we always display the active breadcrumb, so set this if hidden
					if($forum['xthreads_hidebreadcrumb']) {
						$forum['pid'] = $prevforum['fid'];
						$pforumcache[$forum['pid']] = array($forum['fid'] => $forum);
					}
					break;
				}
				$forum =& xthreads_get_array_first($pforumcache[$forum['fid']]);
			}
		}
	}
}

