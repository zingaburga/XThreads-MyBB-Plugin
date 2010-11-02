<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

if(!is_array($info)) return false;

// even if there are no upgrade actions to be run for a particular upgrade, we'll get the user into the habbit of running the upgrader

global $db, $cache;

if($info['version'] < 1.1) {
	// add viewable groups thing to thread fields
	
	// don't need to worry about separating these writes as this version only supports MySQL
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`viewable_gids` varchar(255) not null default "",
		`unviewableval` text not null
	)');
}

if($info['version'] < 1.2) {
	
	require MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('forumdisplay_searchforum_inline', '#\\</form\\>#', '{$xthreads_forum_filter_form}</form>');
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'xtattachments` MODIFY COLUMN `md5hash` binary(16) default null');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`hideedit` tinyint(1) not null default 0
	)');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` ADD COLUMN (
		`xthreads_hideforum` tinyint(3) not null default 0
	)');
	$cache->update_forums();
	
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

if($info['version'] < 1.3) {
	// we won't bother to fix potential issues with multiple values with textboxes
}

/*
if($info['version'] < 1.31) {
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

if($info['version'] < 1.32) {
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

if($info['version'] < 1.33) {
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`tabstop` tinyint(1) not null default 1
	)');
	
	xthreads_buildtfcache();
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` MODIFY `xthreads_tplprefix` varchar(255) not null default ""');
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

return true;
