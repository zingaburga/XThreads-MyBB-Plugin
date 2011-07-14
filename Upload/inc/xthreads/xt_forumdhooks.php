<?php
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_forumdisplay() {
	global $db, $threadfield_cache, $fid, $mybb, $tf_filters, $xt_filters, $filters_set, $xthreads_forum_filter_form, $xthreads_forum_filter_args;
	// the position of the "forumdisplay_start" hook is kinda REALLY annoying...
	$fid = (int)$mybb->input['fid'];
	if($fid < 1 || !($forum = get_forum($fid))) return;
	
	$threadfield_cache = xthreads_gettfcache($fid);
	$tf_filters = array();
	$filters_set = array(
		'__search' => array('hiddencss' => '', 'visiblecss' => 'display: none;', 'selected' => array('' => ' selected="selected"'), 'checked' => array('' => ' checked="checked"'), 'active' => array('' => 'filtertf_active'), 'nullselected' => ' selected="selected"', 'nullchecked' => ' checked="checked"', 'nullactive' => 'filtertf_active'),
		'__all' => array('hiddencss' => '', 'visiblecss' => 'display: none;', 'nullselected' => ' selected="selected"', 'nullchecked' => ' checked="checked"', 'nullactive' => 'filtertf_active'),
	);
	$xthreads_forum_filter_form = $xthreads_forum_filter_args = '';
	$use_default_filter = true;
	if(!empty($threadfield_cache)) {
		function xthreads_forumdisplay_dbhook(&$s, &$db) {
			global $threadfield_cache, $fid, $plugins, $threadfields, $xthreads_forum_sort;
			//if(empty($threadfield_cache)) return;
			
			$fields = '';
			foreach($threadfield_cache as &$v)
				$fields .= ', tfd.`'.$v['field'].'` AS `xthreads_'.$v['field'].'`';
			
			$sortjoin = '';
			if(!empty($xthreads_forum_sort) && isset($xthreads_forum_sort['sortjoin']))
				$sortjoin = ' LEFT JOIN '.$db->table_prefix.$xthreads_forum_sort['sortjoin'];
			
			$s = strtr($s, array(
				'SELECT t.*, ' => 'SELECT t.*'.$fields.', ',
				'WHERE t.fid=' => 'LEFT JOIN `'.$db->table_prefix.'threadfields_data` tfd ON t.tid=tfd.tid'.$sortjoin.' WHERE t.fid=',
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
			$filters_set[$n] = array('hiddencss' => '', 'visiblecss' => 'display: none;', 'nullselected' => ' selected="selected"', 'nullchecked' => ' checked="checked"', 'nullactive' => 'filtertf_active');
			if($tf['ignoreblankfilter']) {
				// will be overwritten if not blank
				$filters_set[$n]['selected'] = array('' => ' selected="selected"');
				$filters_set[$n]['checked'] = array('' => ' checked="checked"');
				$filters_set[$n]['active'] = array('' => 'filtertf_active');
			}
			
			if($tf['allowfilter'] && isset($mybb->input['filtertf_'.$n]) && xthreads_user_in_groups($tf['viewable_gids'])) {
				$tf_filters[$n] = $mybb->input['filtertf_'.$n];
				$use_default_filter = false;
				// ignore blank inputs
				if($tf['ignoreblankfilter'] && (
					(is_array($mybb->input['filtertf_'.$n]) && (empty($tf_filters[$n]) || $tf_filters[$n] == array(''))) ||
					($tf_filters[$n] === '')
				)) {
					unset($tf_filters[$n]);
				}
			}
		}
		
		// sorting by thread fields
		// replicate some MyBB behaviour
		if(!isset($mybb->input['sortby']) && !empty($forum['defaultsortby']))
			$mybb->input['sortby'] = $forum['defaultsortby'];
		if($mybb->input['sortby'] && substr($mybb->input['sortby'], 0, 2) == 'tf') {
			global $xthreads_forum_sort;
			if(substr($mybb->input['sortby'], 0, 3) == 'tf_') {
				$n = substr($mybb->input['sortby'], 3);
				if(isset($threadfield_cache[$n]) && xthreads_empty($threadfield_cache[$n]['multival']) && $threadfield_cache[$n]['inputtype'] != XTHREADS_INPUT_FILE && xthreads_user_in_groups($threadfield_cache[$n]['viewable_gids'])) {
					if($threadfield_cache[$n]['inputtype'] != XTHREADS_INPUT_TEXTAREA) { // also disallow sorting by textarea inputs
						$xthreads_forum_sort = array(
							't' => 'tfd.',
							'sortby' => $mybb->input['sortby'],
							'sortfield' => '`'.$n.'`'
						);
					}
				}
			}
			// xtattachment sorting
			elseif(substr($mybb->input['sortby'], 0, 4) == 'tfa_') {
				$p = strpos($mybb->input['sortby'], '_', 5);
				if($p) {
					$field = strtolower(substr($mybb->input['sortby'], 4, $p-4));
					$n = substr($mybb->input['sortby'], $p+1);
					if(isset($threadfield_cache[$n]) && $threadfield_cache[$n]['inputtype'] == XTHREADS_INPUT_FILE && xthreads_user_in_groups($threadfield_cache[$n]['viewable_gids']) && in_array($field, array('filename', 'filesize', 'uploadtime', 'updatetime', 'downloads'))) {
						$xthreads_forum_sort = array(
							't' => 'xta.',
							'sortby' => $mybb->input['sortby'],
							'sortfield' => '`'.$field.'`',
							'sortjoin' => 'xtattachments xta ON tfd.`'.$n.'`=xta.aid'
						);
					}
				}
			}
		}
		
		// Quick Thread integration
		if(function_exists('quickthread_run'))
			xthreads_forumdisplay_quickthread();
	}
	if(!isset($xthreads_forum_sort) && $mybb->input['sortby'] && in_array($mybb->input['sortby'], array('prefix', 'icon', 'lastposter', 'numratings', 'attachmentcount'))) {
		global $xthreads_forum_sort;
		switch($mybb->input['sortby']) {
			case 'prefix': if($mybb->version_code >= 1500) {
				$xthreads_forum_sort = array(
					't' => 'p.',
					'sortby' => $mybb->input['sortby'],
					'sortfield' => $mybb->input['sortby']
				);
			} break;
			case 'icon':
				$xthreads_forum_sort = array(
					't' => 't.',
					'sortby' => $mybb->input['sortby'],
					'sortfield' => $mybb->input['sortby'],
					// we can't use the sort join because that assumes that thread fields exist... :/
					//'sortfield' => 'name',
					//'sortjoin' => 'icons i ON t.icon=i.iid'
				);
				break;
			case 'lastposter':
			case 'numratings':
			case 'attachmentcount':
				$xthreads_forum_sort = array(
					't' => 't.',
					'sortby' => $mybb->input['sortby'],
					'sortfield' => $mybb->input['sortby']
				);
		}
	}
	$xt_filters = array();
	//$enabled_xtf = explode(',', $forum['xthreads_addfiltenable']);
	//if(!empty($enabled_xtf)) {
		//global $lang;
		//foreach($enabled_xtf as &$xtf) {
		$enabled_xtf = array('uid','icon','lastposteruid');
		if($mybb->version_code >= 1500) $enabled_xtf[] = 'prefix';
		foreach($enabled_xtf as &$xtf) {
			$filters_set['__xt_'.$xtf] = array('hiddencss' => '', 'visiblecss' => 'display: none;', 'nullselected' => ' selected="selected"', 'nullchecked' => ' checked="checked"', 'nullactive' => 'filtertf_active');
			if(isset($mybb->input['filterxt_'.$xtf]) && $mybb->input['filterxt_'.$xtf] !== '') {
				$xt_filters[$xtf] = $mybb->input['filterxt_'.$xtf];
				$use_default_filter = false;
			}
		}
		unset($enabled_xtf);
	//}
	
	if(function_exists('xthreads_evalcacheForumFilters')) {
		$xtforum = xthreads_evalcacheForumFilters($fid);
		if($use_default_filter && (!empty($xtforum['defaultfilter_tf']) || !empty($xtforum['defaultfilter_xt'])) && !$mybb->input['filterdisable']) {
			$tf_filters =& $xtforum['defaultfilter_tf'];
			foreach($xtforum['defaultfilter_tf'] as $n => &$filter) {
				if(!xthreads_user_in_groups($threadfield_cache[$n]['viewable_gids'])) {
					unset($xtforum['defaultfilter_tf'][$n]);
					continue;
				}
				/*if(is_array($filter))
					$filter = array_map('eval_str', $filter);
				else
					$filter = eval_str($filter);*/
			}
			$xt_filters =& $xtforum['defaultfilter_xt'];
			/*foreach($xt_filters as $n => &$filter) {
				if(is_array($filter))
					$filter = array_map('eval_str', $filter);
				else
					$filter = eval_str($filter);
			}*/
		}
		//unset($enabled_xtf);
	}
	
	foreach($tf_filters as $n => &$filter) {
		xthreads_forumdisplay_filter_input('filtertf_'.$n, $filter, $filters_set[$n]);
	}
	foreach($xt_filters as $n => &$filter) {
		// sanitise input here as we may need to grab extra info
		if(is_array($filter))
			$filter = array_map('intval', $filter);
		else
			$filter = (int)$filter;
		
		xthreads_forumdisplay_filter_input('filterxt_'.$n, $filter, $filters_set['__xt_'.$n]);
		
		/*
		if(is_array($filter))
			$ids = implode(',', $filter);
		else
			$ids = $filter;
		
		// grab extra info for $filter_set array
		switch($n) {
			case 'uid': case 'lastposteruid':
				// perhaps might be nice if we could merge these two together...
				$info = xthreads_forumdisplay_xtfilter_extrainfo('users', array('username'), 'uid', $ids, 'guest');
				$filters_set['__xt_'.$n]['name'] = $info['username'];
				break;
			case 'prefix':
				// displaystyles?
				if(!$lang->xthreads_no_prefix) $lang->load('xthreads');
				$info = xthreads_forumdisplay_xtfilter_extrainfo('threadprefixes', array('prefix', 'displaystyle'), 'pid', $ids, 'xthreads_no_prefix');
				$filters_set['__xt_'.$n]['name'] = $info['prefix'];
				$filters_set['__xt_'.$n]['displayname'] = $info['displaystyle'];
				break;
			case 'icon':
				// we'll retrieve icons from the cache rather than query the DB
				$icons = $GLOBALS['cache']->read('posticons');
				if(is_array($filter))
					$ids =& $filter;
				else
					$ids = array($ids);
				
				$filters_set['__xt_'.$n]['name'] = '';
				$iconstr =& $filters_set['__xt_'.$n]['name'];
				foreach($ids as $id) {
					if($id && $icons[$id])
						$iconstr .= ($iconstr?', ':'') . htmlspecialchars_uni($icons[$id]['name']);
					elseif(!$id) {
						if(!$lang->xthreads_no_icon) $lang->load('xthreads');
						$iconstr .= ($iconstr?', ':'') . '<em>'.$lang->xthreads_no_icon.'</em>';
					}
				}
				unset($icons);
				break;
		}
		*/
	}
	unset($filter);
	
	if($xthreads_forum_filter_args) {
		$filters_set['__all']['urlarg'] = htmlspecialchars_uni(substr($xthreads_forum_filter_args, 1));
		$filters_set['__all']['urlarga'] = '&amp;'.$filters_set['__all']['urlarg'];
		$filters_set['__all']['urlargq'] = '?'.$filters_set['__all']['urlarg'];
		$filters_set['__all']['forminput'] = $xthreads_forum_filter_form;
		$filters_set['__all']['hiddencss'] = 'display: none;';
		$filters_set['__all']['visiblecss'] = '';
		unset($filters_set['__all']['nullselected'], $filters_set['__all']['nullchecked'], $filters_set['__all']['nullactive']);
	}
	
	if($forum['xthreads_inlinesearch'] && isset($mybb->input['search']) && $mybb->input['search'] !== '') {
		$urlarg = 'search='.rawurlencode($mybb->input['search']);
		$xthreads_forum_filter_args .= '&'.$urlarg;
		$GLOBALS['xthreads_forum_search_form'] = '<input type="hidden" name="search" value="'.htmlspecialchars_uni($mybb->input['search']).'" />';
		$filters_set['__search']['forminput'] =& $GLOBALS['xthreads_forum_search_form'];
		$filters_set['__search']['value'] = htmlspecialchars_uni($mybb->input['search']);
		$filters_set['__search']['urlarg'] = htmlspecialchars_uni($urlarg);
		$filters_set['__search']['urlarga'] = '&amp;'.$filters_set['__search']['urlarg'];
		$filters_set['__search']['urlargq'] = '?'.$filters_set['__search']['urlarg'];
		$filters_set['__search']['selected'] = array($mybb->input['search'] => ' selected="selected"');
		$filters_set['__search']['checked'] = array($mybb->input['search'] => ' checked="checked"');
		$filters_set['__search']['active'] = array($mybb->input['search'] => 'filtertf_active');
		$filters_set['__search']['hiddencss'] = 'display: none;';
		$filters_set['__search']['visiblecss'] = '';
		unset($filters_set['__search']['nullselected'], $filters_set['__search']['nullchecked'], $filters_set['__search']['nullactive']);
	}
	
	$using_filter = ($forum['xthreads_inlinesearch'] || !empty($tf_filters) || !empty($xt_filters));
	if($using_filter || isset($xthreads_forum_sort)) {
		// only nice way to do all of this is to gain control of $templates, so let's do it
		control_object($GLOBALS['templates'], '
			function get($title, $eslashes=1, $htmlcomments=1) {
				static $done=false;
				if(!$done && $title == \'forumdisplay_orderarrow\') {
					$done = true;
					'.($using_filter?'xthreads_forumdisplay_filter();':'').'
					'.(isset($xthreads_forum_sort)?'
						$orderbyhack = xthreads_forumdisplay_sorter();
						return $orderbyhack.parent::get($title, $eslashes, $htmlcomments);
					':'').'
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
	
	if($forum['xthreads_fdcolspan_offset']) {
		control_object($GLOBALS['cache'], '
			function read($name, $hard=false) {
				static $done=false;
				if(!$done && $name == "posticons" && isset($GLOBALS["colspan"])) {
					$done = true;
					$GLOBALS["colspan"] += $GLOBALS["foruminfo"]["xthreads_fdcolspan_offset"];
				}
				return parent::read($name, $hard);
			}
		');
	}
}

// Quick Thread integration function
function xthreads_forumdisplay_quickthread() {
	$tpl =& $GLOBALS['templates']->cache['forumdisplay_quick_thread'];
	if(xthreads_empty($tpl)) return;
	
	// grab fields
	$edit_fields = $GLOBALS['threadfield_cache']; // will be set
	// filter out non required fields (don't need to filter out un-editable fields as editable by all implies this)
	foreach($edit_fields as $k => &$v) {
		if(!empty($v['editable_gids']) || $v['editable'] != XTHREADS_EDITABLE_REQ)
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

function xthreads_forumdisplay_sorter() {
	global $xthreads_forum_sort, $mybb;
	if(empty($xthreads_forum_sort)) return '';
	$GLOBALS['t'] = $xthreads_forum_sort['t'];
	$GLOBALS['sortby'] = $xthreads_forum_sort['sortby'];
	$GLOBALS['sortfield'] = $xthreads_forum_sort['sortfield'];
	$mybb->input['sortby'] = htmlspecialchars($xthreads_forum_sort['sortby']);
	$GLOBALS['sortsel'] = array($xthreads_forum_sort['sortby'] => 'selected="selected"');
	// apply paranoia filtering...
	return '"; $orderarrow[\''.strtr($xthreads_forum_sort['sortby'], array('\\' => '', '\'' => '', '"' => '')).'\'] = "';
}

function xthreads_forumdisplay_filter_input($arg, &$tffilter, &$filter_set) {
	global $xthreads_forum_filter_form, $xthreads_forum_filter_args;
	if(is_array($tffilter) && count($tffilter) == 1) // single element array -> remove array-ness
		$tffilter = reset($tffilter);
	if(is_array($tffilter)) {
		$filter_set = array(
			'value' => '',
			'urlarg' => '',
			'urlarga' => '&',
			'urlargq' => '?',
			'forminput' => '',
			'selected' => array(),
			'checked' => array(),
			'active' => array(),
			'hiddencss' => 'display: none;',
			'visiblecss' => '',
		);
		$filterurl = '';
		foreach($tffilter as &$val) {
			$filter_set['forminput'] .= '<input type="hidden" name="'.htmlspecialchars($arg).'[]" value="'.htmlspecialchars_uni($val).'" />';
			$filterurl .= ($filterurl ? '&':'').rawurlencode($arg).'[]='.rawurlencode($val);
			
			$filter_set['value'] .= ($filter_set['value'] ? ', ':'').htmlspecialchars_uni($val);
			$filter_set['selected'][$val] = ' selected="selected"';
			$filter_set['checked'][$val] = ' checked="checked"';
			$filter_set['active'][$val] = 'filtertf_active';
		}
		$filter_set['urlarg'] = htmlspecialchars_uni($filterurl);
		$filter_set['urlarga'] = '&amp;'.$filter_set['urlarg'];
		$filter_set['urlargq'] = '?'.$filter_set['urlarg'];
		$xthreads_forum_filter_form .= $filter_set['forminput'];
		$xthreads_forum_filter_args .= '&'.$filterurl;
	} else {
		$formarg = '<input type="hidden" name="'.htmlspecialchars($arg).'" value="'.htmlspecialchars_uni($tffilter).'" />';
		$xthreads_forum_filter_form .= $formarg;
		$urlarg = rawurlencode($arg).'='.rawurlencode($tffilter);
		$xthreads_forum_filter_args .= '&'.$urlarg;
		$filter_set = array(
			'value' => htmlspecialchars_uni($tffilter),
			'urlarg' => htmlspecialchars_uni($urlarg),
			'urlarga' => '&amp;'.htmlspecialchars_uni($urlarg),
			'urlargq' => '?'.htmlspecialchars_uni($urlarg),
			'forminput' => $formarg,
			'selected' => array($tffilter => ' selected="selected"'),
			'checked' => array($tffilter => ' checked="checked"'),
			'active' => array($tffilter => 'filtertf_active'),
			'hiddencss' => 'display: none;',
			'visiblecss' => '',
		);
	}
}

function &xthreads_forumdisplay_xtfilter_extrainfo($table, $fields, $idfield, &$ids, $blanklang) {
	global $db, $lang;
	$ret = array();
	$query = $db->simple_select($table, implode(',',$fields), $idfield.' IN ('.$ids.')');
	while($thing = $db->fetch_array($query)) {
		foreach($fields as $f) {
			$ret[$f] .= ($ret[$f]?', ':'') . htmlspecialchars_uni($thing[$f]);
		}
	}
	$db->free_result($query);
	if(strpos(','.$ids.',', ',0,') !== false)
		foreach($fields as &$f)
			$ret[$f] .= ($ret[$f]?', ':'') . '<em>'.$lang->$blanklang.'</em>';
	return $ret;
}

function xthreads_forumdisplay_filter() {
	global $mybb, $foruminfo, $tf_filters, $xt_filters, $threadfield_cache;
	global $visibleonly, $tvisibleonly, $db;
	
	$q = '';
	$tvisibleonly_tmp = $tvisibleonly;
	
	if($foruminfo['xthreads_inlinesearch']) {
		global $templates, $lang, $gobutton, $fid, $sortby, $sortordernow, $datecut, $xthreads_forum_filter_form;
		$searchval = '';
		if(!xthreads_empty($mybb->input['search'])) {
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
			if(is_array($val) && count($val) > 1) {
				if(!xthreads_empty($threadfield_cache[$field]['multival'])) {
					// ugly, but no other way to really do this...
					$qstr = '(';
					$qor = '';
					$cfield = xthreads_db_concat_sql(array("\"\n\"", 'tfd.`'.$db->escape_string($field).'`', "\"\n\""));
					foreach($val as &$v) {
						$qstr .= $qor.$cfield.' LIKE "%'."\n".$db->escape_string_like($v)."\n".'%"';
						if(!$qor) $qor = ' OR ';
					}
					$qstr .= ')';
					
				}
				else
					$qstr = 'tfd.`'.$db->escape_string($field).'` IN ("'.implode('","', array_map(array($db, 'escape_string'), $val)).'")';
			}
			else {
				if(is_array($val)) // single element
					$val2 = reset($val);
				else
					$val2 =& $val;
				if(!xthreads_empty($threadfield_cache[$field]['multival']))
					$qstr = xthreads_db_concat_sql(array("\"\n\"", 'tfd.`'.$db->escape_string($field).'`', "\"\n\"")).' LIKE "%'."\n".$db->escape_string_like($val2)."\n".'%"';
				else
					$qstr = 'tfd.`'.$db->escape_string($field).'` = "'.$db->escape_string($val2).'"';
			}
			$q .= ' AND '.$qstr;
			$tvisibleonly .= ' AND '.$qstr;
		}
	}
	if(!empty($xt_filters)) {
		foreach($xt_filters as $field => &$val) {
			if(is_array($val) && count($val) > 1) {
				$qstr = '`'.$db->escape_string($field).'` IN ('.implode(',', array_map('intval', $val)).')';
			}
			else {
				$qstr = '`'.$db->escape_string($field).'` = '.(int)$val;
			}
			$q .= ' AND t.'.$qstr;
			$tvisibleonly .= ' AND t.'.$qstr;
			$visibleonly .= ' AND '.$qstr;
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
		
		// inject URL into multipage - template cache hacks
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
		xthreads_sanitize_disp($threadfields[$k], $v, (!xthreads_empty($thread['username']) ? $thread['username'] : $thread['threadusername']));
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
		function xthreads_tpl_forumbits_tplget(&$obj, &$forum, $title, $eslashes, $htmlcomments) {
			if($forum['xthreads_hideforum']) {
				// alternate the bgcolor if applicable (so we get no net change)
				if($title == 'forumbit_depth1_cat' || $title == 'forumbit_depth2_cat' || $title == 'forumbit_depth2_forum')
					$GLOBALS['bgcolor'] = alt_trow();
				return 'return "";';
			}
			global $forum_tpl_prefixes;
			if(!empty($forum_tpl_prefixes[$forum['fid']]))
				foreach($forum_tpl_prefixes[$forum['fid']] as &$p)
					if(isset($obj->cache[$p.$title]) && !isset($obj->non_existant_templates[$p.$title])) {
						$title = $p.$title;
						break;
					}
			return 'return "'.$obj->xthreads_tpl_forumbits_get($title, $eslashes, $htmlcomments).'";';
		}
		control_object($templates, '
			function get($title, $eslashes=1, $htmlcomments=1) {
				if(substr($title, 0, 9) != \'forumbit_\')
					return parent::get($title, $eslashes, $htmlcomments);
				return \'".eval(xthreads_tpl_forumbits_tplget($templates, $forum, \\\'\'.strtr($title, array(\'\\\\\' => \'\\\\\\\\\', \'\\\'\' => \'\\\\\\\'\')).\'\\\', \'.$eslashes.\', \'.$htmlcomments.\'))."\';
			}
			function xthreads_tpl_forumbits_get($title, $eslashes, $htmlcomments){
				return parent::get($title, $eslashes, $htmlcomments);
			}
		');
	}
	
	xthreads_set_threadforum_urlvars('forum', $forum['fid']);
}

function xthreads_global_forumbits_tpl() {
	global $templatelist, $forum_tpl_prefixes;
	// see what custom prefixes we have and cache
	// I'm lazy, so just grab all the forum prefixes even if unneeded (in practice, difficult to filter out things properly anyway)
	// TODO: perhaps make this smarter??
	$forum_tpl_prefixes = xthreads_get_tplprefixes(false);
	if(!empty($forum_tpl_prefixes)) {
		$forumcache = $GLOBALS['cache']->read('forums');
		foreach($forum_tpl_prefixes as $fid => &$prefs) {
			if($forumcache[$fid]['xthreads_hideforum']) continue;
			foreach($prefs as $pre) {
				// essentially, we need to escape this to prevent SQL injection
				// however, if we've taken over control over the templates engine, it'll already do the escaping for us, so don't double-escape
				if(!isset($GLOBALS['templates']->xt_tpl_prefix))
					$pre = $GLOBALS['db']->escape_string($pre);
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
}

// MyBB should really have such a function like this...
function xthreads_db_concat_sql($a) {
	switch(xthreads_db_type()) {
		case 'sqlite':
		case 'pgsql':
			return implode('||', $a);
		default:
			return 'CONCAT('.implode(',', $a).')';
	}

}
