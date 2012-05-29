<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

if(!defined('XTHREADS_INSTALLED_VERSION')) return false;

// if you don't wish to have XThreads modify any templates, set this value to true
// note that once you have XThreads installed (v1.41 or later), this will be stored in cache/xthreads.php instead
@define('XTHREADS_MODIFY_TEMPLATES', true);

// even if there are no upgrade actions to be run for a particular upgrade, we'll get the user into the habbit of running the upgrader

global $db, $cache;

if(XTHREADS_INSTALLED_VERSION < 1.1) {
	// add viewable groups thing to thread fields
	
	// don't need to worry about separating these writes as this version only supports MySQL
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`viewable_gids` varchar(255) not null default "",
		`unviewableval` text not null
	)');
}

require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
if(XTHREADS_INSTALLED_VERSION < 1.2) {
	if(XTHREADS_MODIFY_TEMPLATES)
		find_replace_templatesets('forumdisplay_searchforum_inline', '#\\</form\\>#', '{$xthreads_forum_filter_form}</form>');
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'xtattachments` MODIFY COLUMN `md5hash` binary(16) default null');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`hideedit` tinyint(1) not null default 0
	)');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN (
		`xthreads_hideforum` tinyint(3) not null default 0
	)');
	
	/*
	// try to find orphaned xtattachments
	$orphaned = '';
	$query = $db->simple_select('xtattachments a INNER JOIN '.$db->table_prefix.'threadfields_data tfd ON a.tid=t.tid', 'a.aid AS `a-aid`, a.field AS `a-field`, tfd.*', 'a.tid!=0'); // use a "-" in the name to guarantee no conflict with threadfields
	while($f = $db->fetch_array($query)) {
		if(!$f[$f['a-field']])
			$orphaned = ($orphaned?',':'') . $f['a-aid'];
	}
	$db->free_result($query);
	if($orphaned) // mark as orphaned
		$db->update_query('xtattachments', array('tid' => 0), 'aid IN ('.$orphaned.')');
	
	// also find xtattachment references which are invalid
	*/
}

if(XTHREADS_INSTALLED_VERSION < 1.3) {
	// we won't bother to fix potential issues with multiple values with textboxes
}

/*
if(XTHREADS_INSTALLED_VERSION < 1.31) {
	// make table alterations for longer varchars + removal of default value
	$query = $db->simple_select('threadfields', 'field', 'inputtype IN ('.implode(',', array(XTHREADS_INPUT_TEXT, XTHREADS_INPUT_SELECT, XTHREADS_INPUT_RADIO, XTHREADS_INPUT_CHECKBOX)).')');
	$qry_base = 'ALTER TABLE `'.$db->table_prefix.'threadfields_data` MODIFY ';
	$qry_suf = ' not null default ""';
	while($field = $db->fetch_array($query)) {
		$alterfield_base = '`'.$field['field'].'` ';
		if(!$db->write_query($qry_base.$alterfield_base.'varchar(1024)'.$qry_suf, true)) {
			$db->write_query($qry_base.$alterfield_base.'varchar(255)'.$qry_suf);
		}
	}
}
*/

if(XTHREADS_INSTALLED_VERSION < 1.32) {
	// fix DB collations
	$collation = $db->build_create_table_collation();
	if($collation && ($db->type == 'mysql' || $db->type == 'mysqli')) {
		foreach(array('threadfields_data','xtattachments','threadfields') as $table) {
			$db->write_query('ALTER TABLE `'.$db->table_prefix.$table.'` CONVERT TO '.$collation);
		}
	}
	
	// make table alterations for longer varchars + removal of default value
	$query = $db->simple_select('threadfields', 'field,allowfilter,inputtype,multival', 'inputtype IN ('.implode(',', array(XTHREADS_INPUT_TEXT, XTHREADS_INPUT_SELECT, XTHREADS_INPUT_RADIO, XTHREADS_INPUT_CHECKBOX)).')');
	$qry_base = 'ALTER TABLE `'.$db->table_prefix.'threadfields_data` MODIFY ';
	$qry_suf_vc = ' not null default ""';
	while($field = $db->fetch_array($query)) {
		$alterfield_base = '`'.$field['field'].'` ';
		if($field['allowfilter']) {
			if($field['inputtype'] == XTHREADS_INPUT_TEXT || $field['inputtype'] == XTHREADS_INPUT_CHECKBOX || ($field['inputtype'] == XTHREADS_INPUT_SELECT && $field['multival'] !== '')) {
				if(!$db->write_query($qry_base.$alterfield_base.'varchar(1024)'.$qry_suf_vc, true)) {
					$db->write_query($qry_base.$alterfield_base.'varchar(255)'.$qry_suf_vc);
				}
			} else {
				$db->write_query($qry_base.$alterfield_base.'varchar(255)'.$qry_suf_vc);
			}
		} else {
			$db->write_query($qry_base.$alterfield_base.'text not null');
		}
	}
}

if(XTHREADS_INSTALLED_VERSION < 1.33) {
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`tabstop` tinyint(1) not null default 1
	)');
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` MODIFY `xthreads_tplprefix` varchar(255) not null default ""');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN `xthreads_hidebreadcrumb` tinyint(3) not null default 0');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN `xthreads_addfiltenable` varchar(200) not null default ""');
	
	// replace default comment in showthread_noreplies template
	$query = $db->simple_select('templates', 'tid,template', 'title="showthread_noreplies"');
	while($template = $db->fetch_array($query)) {
		$newtemplate = str_replace('<!-- template to be used if there are no replies to a thread - only evaulated if first post at top option is enabled. For this to work with quick reply properly, you should uncomment and use the following -->', '<!-- template to be used if there are no replies to a thread. For this to work with quick reply properly, you should uncomment and use the following -->', $template['template']);
		if($newtemplate != $template['template']) {
			$db->update_query('templates', array('template' => $db->escape_string($newtemplate)), 'tid='.$template['tid']);
		}
	}
	$db->free_result($query);
}

if(XTHREADS_INSTALLED_VERSION < 1.40) {
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN `xthreads_langprefix` text not null');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN `xthreads_defaultfilter` text not null');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` DROP COLUMN `xthreads_addfiltenable`');
	// add indexes
	foreach(array('lastposteruid','prefix','icon') as $afe) {
		$db->write_query('ALTER TABLE `'.$db->table_prefix.'threads` ADD KEY `xthreads_'.$afe.'` (`'.$afe.'`)', true);
	}
	$cache->update_forums();
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`datatype` tinyint(3) not null default '.XTHREADS_DATATYPE_TEXT.'
	)');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` MODIFY `xthreads_tplprefix` text not null');
	
	// not _really_ necessary for XThreads, but we'll do it anyway for any
	// plugin which decides to rely on the 'uid' column of xtattachments table
	// and so that we don't end up stabbing ourselves in the foot later on
	$db->write_query('UPDATE `'.$db->table_prefix.'xtattachments` a INNER JOIN `'.$db->table_prefix.'threads` t ON a.tid=t.tid SET a.uid=t.uid WHERE a.uid=0 AND a.tid!=0');
	// obviously not entirely accurate (thread starter may not be uploader of file) but better than leaving it as a '0'
	
	if(XTHREADS_MODIFY_TEMPLATES) {
		require_once MYBB_ROOT.'inc/xthreads/xt_install.php'; // grab XTHREADS_INSTALL_TPLADD_EXTRASORT define
		find_replace_templatesets('forumdisplay_threadlist', '#\\<option value="subject" \\{\\$sortsel\\[\'subject\'\\]\\}\\>\\{\\$lang-\\>sort_by_subject\\}\\</option\\>#', '{$sort_by_prefix}<option value="subject" {$sortsel[\'subject\']}>{$lang->sort_by_subject}</option>');
		find_replace_templatesets('forumdisplay_threadlist', '#\\<option value="views" \\{\\$sortsel\\[\'views\'\\]\\}\\>\\{\\$lang-\\>sort_by_views\\}\\</option\\>#', '<option value="views" {$sortsel[\'views\']}>{$lang->sort_by_views}</option>'."\n".XTHREADS_INSTALL_TPLADD_EXTRASORT);
		find_replace_templatesets('forumdisplay_threadlist_sortrating', '#$#', '<option value="numratings" {$sortsel[\'numratings\']}>{$lang->sort_by_numratings}</option>');
	}
}

if(XTHREADS_INSTALLED_VERSION < 1.41) {
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN `xthreads_fdcolspan_offset` smallint(6) not null default 0');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`editable_values` text not null
	)');
	
}
if(XTHREADS_INSTALLED_VERSION < 1.42) {
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` MODIFY `defaultsortby` varchar(255) NOT NULL default \'\'');
	
}
if(XTHREADS_INSTALLED_VERSION < 1.43) {
	// remove hanging threadfields_data entries
	$query = $db->query('SELECT tfd.tid
		FROM `'.$db->table_prefix.'threadfields_data` tfd
		LEFT JOIN `'.$db->table_prefix.'threads` t ON tfd.tid=t.tid
		WHERE t.tid IS NULL');
	/* other queries, which seem to be slower
	 * select tid from mybb_threadfields_data where tid not in (select tid from mybb_threads)
	 * ^ about same speed
	 * select tid from mybb_threadfields_data tfd where not exists (select tid from mybb_threads t where t.tid=tfd.tid)
	 * ^ a fair bit slower
	 */
	$tids = '';
	while($tid = $db->fetch_field($query, 'tid')) {
		$tids .= ($tids?',':'') . $tid;
	}
	$db->free_result($query);
	
	if($tids) {
		$db->delete_query('threadfields_data', 'tid IN ('.$tids.')');
		require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
		xthreads_rm_attach_query('tid IN ('.$tids.')');
	}
	
	require_once MYBB_ROOT.'inc/xthreads/xt_install.php';
	xthreads_plugins_quickthread_tplmod();
	
	$xt_forums_cache = $cache->read('xt_forums');
	if(!empty($xt_forums_cache)) { // remove old xt_forums cache if present
		xthreads_delete_datacache('xt_forums');
	}
}

if(XTHREADS_INSTALLED_VERSION < 1.45 && XTHREADS_INSTALLED_VERSION > 1.32) {
	// we'll fix up broken regexes in these versions if they exist
	// note that this does assume people haven't deliberately used bad regexes
	$db->update_query('threadfields', array(
		'textmask' => $db->escape_string('^(https?)\://([a-z0-9.\-_]+)(/[^\r\n"<>&]*)?$')
	), 'textmask="'.$db->escape_string('^(https?)\://([a-z.\-_]+)(/[^\r\n"<>&]*)?$').'"');
	$rows_changed = $db->affected_rows();
	
	$db->update_query('threadfields', array(
		'textmask' => $db->escape_string('^([a-z0-9]+)\://([a-z0-9.\-_]+)(/[^\r\n"<>&]*)?$')
	), 'textmask="'.$db->escape_string('^([a-z0-9]+)\://([a-z.\-_]+)(/[^\r\n"<>&]*)?$').'"');
	$rows_changed += $db->affected_rows();
}

if(XTHREADS_INSTALLED_VERSION < 1.50) {
	// template modification
	$tpl_threadfields_inputrow = $db->fetch_field($db->simple_select('templates', 'template', 'title="threadfields_inputrow" AND sid=-1'), 'template');
	if($tpl_threadfields_inputrow) {
		$newtpl = preg_replace('~^(\s*\<tr)\>~i', '$1 class="xthreads_inputrow">', $tpl_threadfields_inputrow);
		if($newtpl != $tpl_threadfields_inputrow)
			$db->update_query('templates', array('template' => $db->escape_string($newtpl)), 'title="threadfields_inputrow" AND sid=-1');
	}
	
	// settings overrides
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN `xthreads_settingoverrides` text not null');
	// migrate old settings across
	$query = $db->simple_select('forums', 'fid,xthreads_force_postlayout,xthreads_threadsperpage');
	while($forum = $db->fetch_array($query)) {
		$override = '';
		if($forum['xthreads_force_postlayout'])
			$override .= 'postlayout='.$forum['xthreads_force_postlayout']."\n";
		if($forum['xthreads_threadsperpage'])
			$override .= 'threadsperpage='.$forum['xthreads_threadsperpage']."\n";
		if($override)
			$db->update_query('forums', array('xthreads_settingoverrides' => $db->escape_string($override)), 'fid='.$forum['fid']);
	}
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` DROP COLUMN `xthreads_force_postlayout`, DROP COLUMN `xthreads_threadsperpage`');
	
	$cache->update_forums(); // the forum cache rebuild reads from here
	xthreads_buildtfcache(); // will also update XThreads forum cache
}

if(XTHREADS_INSTALLED_VERSION < 1.53) {
	// fix up non-text select/radio inputs
	$query = $db->simple_select('threadfields', 'field, datatype', 'datatype!='.XTHREADS_DATATYPE_TEXT.' AND inputtype IN('.XTHREADS_INPUT_RADIO.','.XTHREADS_INPUT_SELECT.')');
	$fixsql = '';
	while($tf = $db->fetch_array($query)) {
		switch($tf['datatype']) {
			case XTHREADS_DATATYPE_INT:
			case XTHREADS_DATATYPE_UINT:
				$fieldtype = xthreads_db_fielddef('int', null, $tf['datatype']==XTHREADS_DATATYPE_UINT).' default null';
				break;
			case XTHREADS_DATATYPE_BIGINT:
			case XTHREADS_DATATYPE_BIGUINT:
				$fieldtype = xthreads_db_fielddef('bigint', null, $tf['datatype']==XTHREADS_DATATYPE_BIGUINT).' default null';
				break;
			case XTHREADS_DATATYPE_FLOAT:
				$fieldtype = 'double default null';
				break;
			default: // paranoia
				$fieldtype = '';
		}
		if($fieldtype)
			$fixsql .= ($fixsql?', ':'').'MODIFY `'.$db->escape_string($tf['field']).'` '.$fieldtype;
	}
	$db->free_result($query);
	if($fixsql)
		$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields_data` '.$fixsql);
}

if(XTHREADS_INSTALLED_VERSION < 1.60) {
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`inputformat` text not null,
		`inputvalidate` text not null
	)');
	// we never used this, so may as well get rid of it
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` DROP COLUMN `xthreads_wol_xtattachment`');
}

return true;
