<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_forumdisplay() {
	global $db, $threadfield_cache, $fid, $mybb, $tf_filters, $xthreads_forum_filter_form, $xthreads_forum_filter_args;
	// the position of the "forumdisplay_start" hook is kinda REALLY annoying...
	$fid = intval($mybb->input['fid']);
	if($fid < 1 || !($forum = get_forum($fid))) return;
	
	$threadfield_cache = xthreads_gettfcache($fid);
	$tf_filters = array();
	$xthreads_forum_filter_form = $xthreads_forum_filter_args = '';
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
		// and generate form HTML
		foreach($threadfield_cache as $n => &$tf) {
			if($tf['allowfilter'] && isset($mybb->input['filtertf_'.$n])) {
				$tf_filters[$n] = $mybb->input['filtertf_'.$n];
				$xthreads_forum_filter_form .= '<input type="hidden" name="filtertf_'.htmlspecialchars($n).'" value="'.htmlspecialchars_uni($tf_filters[$n]).'" />';
				$xthreads_forum_filter_args .= '&filtertf_'.rawurlencode($n).'='.rawurlencode($tf_filters[$n]);
			}
			//if($mybb->input['sortby'] == 'tf_'.$n)
			//	$tf_sort = $n;
		}
		
		// Quick Thread integration
		if(function_exists('quickthread_run'))
			xthreads_forumdisplay_quickthread();
	}
	if($forum['xthreads_inlinesearch'] && $mybb->input['search']) {
		$xthreads_forum_filter_args .= '&search='.rawurlencode($mybb->input['search']);
		$GLOBALS['xthreads_forum_search_form'] = '<input type="hidden" name="search" value="'.htmlspecialchars_uni($mybb->input['search']).'" />';
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
		
		// generate stuff for pagination/sort-links and fields for forms (sort listboxes, inline search)
		
	}
	
}

// Quick Thread integration function
function xthreads_forumdisplay_quickthread() {
	$tpl =& $GLOBALS['templates']->cache['forumdisplay_quick_thread'];
	if(!$tpl) return;
	
	// grab fields
	$edit_fields = $GLOBALS['threadfield_cache']; // will be set
	// filter out non required fields (don't need to filter out un-editable fields as editable by all implies this)
	foreach($edit_fields as $k => &$v) {
		if($v['editable_gids'] || $v['editable'] != XTHREADS_EDITABLE_REQ)
			unset($edit_fields[$k]);
	}
	if(empty($edit_fields)) return;
	
	require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
	$blank = array();
	xthreads_input_generate($blank, $edit_fields, $GLOBALS['fid']);
	if(!strpos($tpl, 'enctype="multipart/form-data"'))
		$tpl = str_replace('<form method="post" ', '<form method="post" enctype="multipart/form-data" ', $tpl);
	$tpl = preg_replace('~(\<tbody.*?\<tr\>.*?)(\<tr\>)~is', '$1'.strtr($GLOBALS['extra_threadfields'], array('$' => '\\$')).'$2', $tpl, 1);
}

function xthreads_forumdisplay_filter() {
	global $mybb, $foruminfo, $tf_filters, $threadfield_cache;
	global $visibleonly, $tvisibleonly, $db;
	
	$q = '';
	$tvisibleonly_tmp = $tvisibleonly;
	
	if($foruminfo['xthreads_inlinesearch']) {
		global $templates, $lang, $gobutton, $fid, $sortby, $sortordernow, $datecut, $xthreads_forum_filter_form;
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
	
	// if we have custom filters/inline search, patch the forumdisplay paged URLs + sorter links
	global $xthreads_forum_filter_args;
	if($xthreads_forum_filter_args) {
		$filterargs_html = htmlspecialchars_uni($xthreads_forum_filter_args);
		$GLOBALS['sorturl'] .= $filterargs_html;
		
		// old method for URL injection into multipage
		/* global $datecut;
		if($datecut <= 0 || $datecut == 9999) $datecut = 9999.9; // .9 gets around == 9999 check, but gets intval'd anyway - does introduce a side effect in URLs though
		$datecut .= $xthreads_forum_filter_args;
		// hack to get URL in page links
		if(!$mybb->input['datecut']) $mybb->input['datecut'] = 9999; */
		
		// new method - template cache hacks
		global $templates;
		$tpls = array('multipage_end', 'multipage_nextpage', 'multipage_page', 'multipage_prevpage', 'multipage_start');
		foreach($tpls as &$t)
			if(!isset($templates->cache[$t])) {
				$templates->cache(implode(',', $tpls));
				break;
			}
		
		// may need to replace first &amp; with a ?
		if(($mybb->settings['seourls'] == 'yes' || ($mybb->settings['seourls'] == 'auto' && $_SERVER['SEO_SUPPORT'] == 1)) && $GLOBALS['sortby'] == 'lastpost' && $GLOBALS['sortordernow'] == 'desc' && ($GLOBALS['datecut'] <= 0 || $GLOBALS['datecut'] == 9999))
			$filterargs_html = '?'.substr($filterargs_html, 5);
		
		foreach($tpls as &$t) {
			$templates->cache[$t] = str_replace('{$page_url}', '{$page_url}'.$filterargs_html, $templates->cache[$t]);
		}
		
		
		$templates->cache['forumdisplay_threadlist'] = str_replace('<select name="sortby">', '{$xthreads_forum_filter_form}{$xthreads_forum_search_form}<select name="sortby">', $templates->cache['forumdisplay_threadlist']);
	}
}

function xthreads_forumdisplay_thread() {
	global $thread, $threadfields, $threadfield_cache, $foruminfo;
	
	// make threadfields array
	$threadfields = array();
	foreach($threadfield_cache as $k => &$v) {
		xthreads_get_xta_cache($v, $GLOBALS['tids']);
		
		$threadfields[$k] =& $thread['xthreads_'.$k];
		xthreads_sanitize_disp($threadfields[$k], $v, ($thread['username'] ? $thread['username'] : $thread['threadusername']));
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
		$templates->cache['__null__forumbit_depth1_cat'] = $templates->cache['__null__forumbit_depth2_cat'] = $templates->cache['__null__forumbit_depth2_forum'] = $templates->cache['__null__forumbit_depth3'] = ' ';
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
	if($forum['xthreads_hideforum']) $forum['xthreads_tplprefix'] = '__null__';
	
	xthreads_set_threadforum_urlvars('forum', $forum['fid']);
}

function xthreads_global_forumbits_tpl() {
	global $templatelist;
	// see what custom prefixes we have and cache
	// I'm lazy, so just grab all the forum prefixes even if unneeded (in practice, difficult to filter out things properly anyway)
	// TODO: perhaps make this smarter??
	$prefixes = array();
	foreach($GLOBALS['cache']->read('forums') as $f) {
		if($f['xthreads_tplprefix'] && !$f['xthreads_hideforum'])
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
