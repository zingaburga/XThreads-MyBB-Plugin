<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_forumdisplay() {
	global $db, $threadfield_cache, $fid, $mybb, $tf_filters;
	// the position of the "forumdisplay_start" hook is kinda REALLY annoying...
	$fid = intval($mybb->input['fid']);
	if($fid < 1 || !($forum = get_forum($fid))) return;
	
	$threadfield_cache = xthreads_gettfcache($fid);
	$tf_filters = array();
	if(!empty($threadfield_cache)) {
		function xthreads_forumdisplay_dbhook(&$s, &$db) {
			global $threadfield_cache, $fid, $plugins, $threadfields;
			//if(empty($threadfield_cache)) return;
			
			$fields = '';
			foreach($threadfield_cache as &$v)
				$fields .= ', tfd.`'.$v['field'].'` AS `xthreads_'.$v['field'].'`';
			
			$s = strtr($s, array(
				'SELECT t.*, ' => 'SELECT t.*'.$fields.', ',
				'WHERE t.fid=' => 'LEFT JOIN `'.$db->table_prefix.'threadfields_data` tfd ON t.tid=tfd.tid WHERE t.fid=',
			));
			$plugins->add_hook('forumdisplay_thread', 'xthreads_forumdisplay_thread');
			$threadfields = array();
		}
		
		control_object($db, '
			function query($string, $hide_errors=0, $write_query=0) {
				static $done=false;
				if(!$done && !$write_query && strpos($string, \'SELECT t.*, \') && strpos($string, \'t.username AS threadusername, u.username\') && strpos($string, \'FROM '.TABLE_PREFIX.'threads t\')) {
					$done = true;
					xthreads_forumdisplay_dbhook($string, $this);
				}
				return parent::query($string, $hide_errors, $write_query);
			}
		');
		
		// also check for forumdisplay filters/sort
		foreach($threadfield_cache as $n => &$tf) {
			if($tf['allowfilter'] && isset($mybb->input['filtertf_'.$n])) {
				$tf_filters[$n] = $mybb->input['filtertf_'.$n];
			}
			//if($mybb->input['sortby'] == 'tf_'.$n)
			//	$tf_sort = $n;
		}
	}
	if($forum['xthreads_inlinesearch'] || !empty($tf_filters)) {
		// only nice way to do all of this is to gain control of $templates, so let's do it
		control_object($GLOBALS['templates'], '
			function get($title, $eslashes=1, $htmlcomments=1) {
				static $done=false;
				if(!$done && $title == \'forumdisplay_orderarrow\') {
					$done = true;
					xthreads_forumdisplay_filter();
				}
				return parent::get($title, $eslashes, $htmlcomments);
			}
		');
		
		/*
		if($forum['xthreads_inlinesearch']) {
			// give us a bit of a free speed up since this isn't really being used anyway...
			$templates->cache['forumdisplay_searchforum'] = '';
		}
		*/
	}
	if($forum['xthreads_threadsperpage']) {
		$mybb->settings['threadsperpage'] = $forum['xthreads_threadsperpage'];
	}
}

function xthreads_forumdisplay_filter() {
	global $mybb, $foruminfo, $tf_filters, $threadfield_cache;
	global $visibleonly, $tvisibleonly, $db;
	
	$q = '';
	$tvisibleonly_tmp = $tvisibleonly;
	
	if($foruminfo['xthreads_inlinesearch']) {
		global $templates, $lang, $gobutton, $fid, $sortby, $sortordernow, $datecut;
		$searchval = '';
		if($mybb->input['search']) {
			$qstr = 'subject LIKE "%'.$db->escape_string_like($mybb->input['search']).'%"';
			$visibleonly .= ' AND '.$qstr;
			$q .= ' AND t.'.$qstr;
			$tvisibleonly .= ' AND t.'.$qstr;
			$searchval = htmlspecialchars_uni($mybb->input['search']);
		}
		
		eval('$GLOBALS[\'searchforum\'] = "'.$templates->get('forumdisplay_searchforum_inline').'";');
	}
	if(!empty($tf_filters)) {
		foreach($tf_filters as $field => &$val) {
			// $threadfield_cache is guaranteed to be set here
			if(is_array($val)) {
				if(!empty($val)) {
					if($threadfield_cache[$field]['multival']) {
						// ugly, but no other way to really do this...
						$qstr = '(';
						$qor = '';
						$cfield = xthreads_db_concat_sql(array("\"\n\"", $db->escape_string($field), "\"\n\""));
						foreach($val as &$v) {
							$qstr .= $qor.$cfield.' LIKE "%'."\n".$db->escape_string_like($v)."\n".'%"';
							if(!$qor) $qor = ' OR ';
						}
						$qstr .= ')';
						
					}
					else
						$qstr = 'tfd.`'.$db->escape_string($field).'` IN ("'.implode('","', array_map(array($db, 'escape_string'), $val)).'")';
				}
			}
			else {
				if($threadfield_cache[$field]['multival'])
					$qstr = xthreads_db_concat_sql(array("\"\n\"", 'tfd.`'.$db->escape_string($field).'`', "\"\n\"")).' LIKE "%'."\n".$db->escape_string_like($val)."\n".'%"';
				else
					$qstr = 'tfd.`'.$db->escape_string($field).'` = "'.$db->escape_string($val).'"';
			}
			$q .= ' AND '.$qstr;
			$tvisibleonly .= ' AND '.$qstr;
		}
	}
	if($q) {
		// and now we have to patch the DB to get proper thread counts...
		$dbf = $dbt = '';
		if($GLOBALS['datecut'] <= 0) {
			if(!empty($tf_filters))
				$dbf_code = '
					$table = "threads t LEFT JOIN {$this->table_prefix}threadfields_data tfd ON t.tid=tfd.tid";
					$fields = "COUNT(t.tid) AS threads, 0 AS unapprovedthreads";
					$conditions .= \''.strtr($tvisibleonly_tmp.$q, array('\'' => '\\\'', '\\' => '\\\\')).'\';
				';
			else
				$dbf_code = '
					$table = "threads";
					$fields = "COUNT(tid) AS threads, 0 AS unapprovedthreads";
					$conditions .= \''.strtr($visibleonly, array('\'' => '\\\'', '\\' => '\\\\')).'\';
				';
			$dbf = '
				static $dont_f = false;
				if(!$done_f && $table == "forums" && $fields == "threads, unapprovedthreads") {
					$done_f = true;
					'.$dbf_code.'
					
				}
			';
		}
		if(!empty($tf_filters))
			$dbt = '
				static $done_t = false;
				if(!$done_t && $table == "threads" && $fields == "COUNT(tid) AS threads") {
					$done_t = true;
					$table = "threads t LEFT JOIN {$this->table_prefix}threadfields_data tfd ON t.tid=tfd.tid";
					$fields = "COUNT(t.tid) AS threads";
					$conditions .= \''.strtr($q, array('\'' => '\\\'', '\\' => '\\\\')).'\';
					$options = array("limit" => 1);
				}
			';
		
		if($dbf || $dbt) {
			control_object($db, '
				function simple_select($table, $fields="*", $conditions="", $options=array()) {
					'.$dbt.$dbf.'
					return parent::simple_select($table, $fields, $conditions, $options);
				}
			');
		}
	}
}

function xthreads_forumdisplay_thread() {
	global $thread, $threadfields, $threadfield_cache, $foruminfo;
	
	// make threadfields array
	$threadfields = array();
	foreach($threadfield_cache as $k => &$v) {
		xthreads_get_xta_cache($v, $GLOBALS['tids']);
		
		$threadfields[$k] =& $thread['xthreads_'.$k];
		xthreads_sanitize_disp($threadfields[$k], $v, ($thread['usrename'] ? $thread['username'] : $thread['threadusername']));
	}
	// evaluate group separator
	if($foruminfo['xthreads_grouping']) {
		static $threadcount = 0;
		static $nulldone = false;
		global $templates;
		
		if($thread['sticky'] == 0 && !$nulldone) {
			$nulldone = true;
			$nulls = (count($GLOBALS['threadcache']) - $threadcount) % $foruminfo['xthreads_grouping'];
			if($nulls) {
				$excess = $nulls;
				$nulls = $foruminfo['xthreads_grouping'] - $nulls;
				$GLOBALS['nullthreads'] = '';
				while($nulls--) {
					$bgcolor = alt_trow(); // TODO: this may be problematic
					eval('$GLOBALS[\'nullthreads\'] .= "'.$templates->get('forumdisplay_thread_null').'";');
				}
			}
		}
		
		// reset counter on sticky/normal sep
		if($thread['sticky'] == 0 && $GLOBALS['shownormalsep']) {
			$nulls = $threadcount % $foruminfo['xthreads_grouping'];
			if($nulls) {
				$excess = $nulls;
				$nulls = $foruminfo['xthreads_grouping'] - $nulls;
				while($nulls--) {
					$bgcolor = alt_trow();
					eval('$GLOBALS[\'threads\'] .= "'.$templates->get('forumdisplay_thread_null').'";');
				}
			}
			
			$threadcount = 0;
		}
		if($threadcount && $threadcount % $foruminfo['xthreads_grouping'] == 0)
			eval('$GLOBALS[\'threads\'] .= "'.$templates->get('forumdisplay_group_sep').'";');
		++$threadcount;
		
	}
}

function xthreads_tpl_forumbits(&$forum) {
	static $done=false;
	global $templates;
	
	if(!$done) {
		$done = true;
		control_object($templates, '
			function get($title, $eslashes=1, $htmlcomments=1) {
				$p =& $this->xthreads_forumbits_curforum[\'xthreads_tplprefix\'];
				if($p && $this->cache[$p.$title] && substr($title, 0, 9) == \'forumbit_\') {
					return parent::get($p.$title, $eslashes, $htmlcomments);
				}
				return parent::get($title, $eslashes, $htmlcomments);
			}
		');
	}
	$templates->xthreads_forumbits_curforum =& $forum;
}

function xthreads_global_forumbits_tpl() {
	global $templatelist;
	// see what custom prefixes we have and cache
	// I'm lazy, so just grab all the forum prefixes even if unneeded (in practice, difficult to filter out things properly anyway)
	// TODO: perhaps make this smarter??
	$prefixes = array();
	foreach($GLOBALS['cache']->read('forums') as $f) {
		if($f['xthreads_tplprefix'])
			$prefixes[$f['xthreads_tplprefix']] = 1;
	}
	if(!empty($prefixes)) {
		foreach(array_keys($prefixes) as $pre) {
			$templatelist .= ','.
				$pre.'forumbit_depth1_cat,'.
				$pre.'forumbit_depth1_cat_subforum,'.
				$pre.'forumbit_depth1_forum_lastpost,'.
				$pre.'forumbit_depth2_cat,'.
				$pre.'forumbit_depth2_forum,'.
				$pre.'forumbit_depth2_forum_lastpost,'.
				$pre.'forumbit_depth3,'.
				$pre.'forumbit_depth3_statusicon,'.
				$pre.'forumbit_moderators,'.
				$pre.'forumbit_subforums';
		}
	}
}

// MyBB should really have such a function like this...
function xthreads_db_concat_sql($a) {
	global $db;
	switch($db->type) {
		case 'sqlite3':
		case 'sqlite2':
		case 'pgsql':
			return implode('||', $a);
		default:
			return 'CONCAT('.implode(',', $a).')';
	}

}
