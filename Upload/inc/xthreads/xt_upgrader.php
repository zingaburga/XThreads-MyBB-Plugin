<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

if(!is_array($info)) return false;

// even if there are no upgrade actions to be run for a particular upgrade, we'll get the user into the habbit of running the upgrader

global $db, $cache;

if($info['version'] < 1.1) {
	// add viewable groups thing to thread fields
	
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields` ADD COLUMN (
		`viewable_gids` varchar(255) not null default "",
		`unviewableval` text not null
	)');
	//$db->update_query('threadfields', array('unviewableval' => $db->escape_string('{BLANKVAL}')));
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

if($info['version'] < 1.26) {
	xthreads_buildtfcache();
}

return true;
