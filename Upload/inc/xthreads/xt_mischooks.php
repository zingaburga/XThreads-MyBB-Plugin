<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_search() {
	global $db, $threadfield_cache;
	$threadfield_cache = xthreads_gettfcache();
	if(!empty($threadfield_cache)) {
		function xthreads_search_dbhook(&$s, &$db) {
			global $threadfield_cache, $fid;
			
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
	
	global $cache, $plugins;
	// cache templates - we've got not much choice but to cache all forums with custom template prefixes
	$cachelist = '';
	$forumcache = $cache->read('forums');
	foreach($forumcache as &$forum) {
		if($forum['xthreads_tplprefix'])
			$cachelist .= ($cachelist?',':'').$forum['xthreads_tplprefix'].'search_results_posts_post,'.$forum['xthreads_tplprefix'].'search_results_threads_thread';
	}
	if($cachelist) $GLOBALS['templates']->cache($cachelist);
	
	$plugins->add_hook('search_results_post', 'xthreads_search_result_post');
	$plugins->add_hook('search_results_thread', 'xthreads_search_result_thread');
}

function xthreads_search_result(&$data, $tplname) {
	global $threadfields, $threadfield_cache, $forumcache, $templates;
	static $done_attach_dl_count = false;
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
			xthreads_sanitize_disp($threadfields[$k], $v, ($data['usrename'] ? $data['username'] : $data['userusername']));
		}
	}
	// template hack
	$tplpref = $forumcache[$data['fid']]['xthreads_tplprefix'];
	if($tplpref && $templates->cache[$tplpref.$tplname]) {
		if(!isset($templates->cache['backup_'.$tplname.'_backup__']))
			$templates->cache['backup_'.$tplname.'_backup__'] = $templates->cache[$tplname];
		$templates->cache[$tplname] =& $templates->cache[$tplpref.$tplname];
	}
	elseif(isset($templates->cache['backup_'.$tplname.'_backup__']))
		$templates->cache[$tplname] =& $templates->cache['backup_'.$tplname.'_backup__'];
}
function xthreads_search_result_post() {
	xthreads_search_result($GLOBALS['post'], 'search_results_posts_post');
}
function xthreads_search_result_thread() {
	xthreads_search_result($GLOBALS['thread'], 'search_results_threads_thread');
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
		
		case 'xtattachment':
			$a['location_name'] = $lang->sprintf($lang->xthreads_downloading_attachment, htmlspecialchars_uni($user_activity['location']), htmlspecialchars_uni($user_activity['filenamename']));
			// TODO: allow custom for this too
			return;
	}
	
	if(!$fid) return;
	global $forumcache;
	if(!is_array($forumcache)) $forumcache = $GLOBALS['cache']->read('forums');
	$wolstr =& $forumcache[$fid]['xthreads_wol_'.$user_activity['activity']];
	if($wolstr) {
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
				if($GLOBALS['mybb']->version_code >= 1600)
					$hook = '
						function query($string, $hide_errors=0, $write_query=0) {
							static $done=false;
							if(!$done && !$write_query && substr(trim($string), 0, 73) == "SELECT t.fid, t.tid, t.subject, t.visible, p.displaystyle AS threadprefix") {
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
					function fetch_array($query) {
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
		case 'unknown':
			// TODO: the following URL isn't guaranteed as query strings may be used
			if(($p = strpos($ua['location'], '/xthreads_attach.php/')) !== false) {
				// check if really is xtattach page
				if(strpos($ua['location'], '.php') > $p) {
					// yes, user isn't sticking this as an argument
					// TODO: parse URL for stuff
				}
			}
			break;
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
