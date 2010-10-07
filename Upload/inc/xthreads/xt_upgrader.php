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
	xthreads_buildtfcache();
}

return true;
