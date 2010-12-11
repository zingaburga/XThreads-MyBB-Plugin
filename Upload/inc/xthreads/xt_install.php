<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

if(XTHREADS_ALLOW_PHP_THREADFIELDS == 2) {
	$plugins->add_hook('admin_config_plugins_activate_commit', 'xthreads_plugins_phptpl_activate');
	$plugins->add_hook('admin_config_plugins_deactivate_commit', 'xthreads_plugins_phptpl_deactivate');
}

function xthreads_info() {
	global $lang;
	$lang->load('xthreads');
	return array(
		'name'			=> '<span style="color: green;">'.$lang->xthreads_name.'</span>',
		'description'	=> $lang->xthreads_desc,
		'website'		=> 'http://mybbhacks.zingaburga.com/',
		'author'		=> 'ZiNgA BuRgA',
		'authorsite'	=> 'http://zingaburga.com/',
		'version'		=> xthreads_format_version_number(XTHREADS_VERSION),
		'compatibility'	=> '14*,15*,16*',
		'guid'			=> ''
	);
}

function xthreads_is_installed() {
	static $is_installed = null;
	if(!isset($is_installed))
		$is_installed = $GLOBALS['db']->table_exists('threadfields');
	return $is_installed;
}

function xthreads_install() {
	global $db, $cache;
	$create_table_suffix = $db->build_create_table_collation();
	
	$dbtype = xthreads_db_type();
	
	switch($dbtype) {
		case 'mysql':
			$create_table_suffix = ' TYPE=MyISAM'.$create_table_suffix;
			$auto_increment = ' auto_increment';
		break;
		case 'sqlite':
			$auto_increment = ' PRIMARY KEY';
			break;
		case 'pgsql':
			$auto_increment = '';
	}
	
	if($dbtype != 'mysql') die('XThreads currently does not support database systems other than MySQL/i.');
	
	if(!$db->table_exists('threadfields_data')) {
		$db->write_query('CREATE TABLE '.$db->table_prefix.'threadfields_data (
			tid '.xthreads_db_fielddef('int').' not null
			'.($dbtype != 'sqlite' ? ', PRIMARY KEY (tid)':'').'
		)'.$create_table_suffix);
	}
	
	if(!$db->table_exists('xtattachments')) {
		$db->write_query('CREATE TABLE '.$db->table_prefix.'xtattachments (
			aid '.xthreads_db_fielddef('int').' not null'.$auto_increment.',
			downloads '.xthreads_db_fielddef('bigint').' not null default 0,
			
			tid '.xthreads_db_fielddef('int').' not null,
			uid '.xthreads_db_fielddef('int').' not null default 0,
			field varchar(50) not null default \'\',
			posthash varchar(50) not null default \'\',
			filename varchar(120) not null default \'\',
			uploadmime varchar(120) not null default \'\',
			filesize '.xthreads_db_fielddef('bigint').' not null default 0,
			attachname varchar(120) not null default \'\',
			indir varchar(40) not null default \'\',
			md5hash '.xthreads_db_fielddef('binary', 16).' default null,
			uploadtime '.xthreads_db_fielddef('bigint').' not null default 0,
			updatetime '.xthreads_db_fielddef('bigint').' not null default 0,
			
			thumbs text not null
			
			'.($dbtype != 'sqlite' ? ',
				PRIMARY KEY (aid)
				'.($dbtype != 'pg' ? ',
					KEY (tid),
					KEY (tid,uid),
					KEY (posthash),
					KEY (field)
				':'').'
			':'').'
		)'.$create_table_suffix);
	}
	if(!$db->table_exists('threadfields')) {
		$fieldprops = xthreads_threadfields_props();
		$query =  '';
		foreach($fieldprops as $field => &$prop) {
			$query .= ($query?',':'').'`'.$field.'` '.xthreads_db_fielddef($prop['db_type'], $prop['db_size'], $prop['db_unsigned']).' not null';
			if(isset($prop['default']) && ($prop['db_type'] != 'text')) {
				if($prop['datatype'] == 'string')
					$query .= ' default \''.$db->escape_string($prop['default']).'\'';
				elseif($prop['datatype'] == 'double')
					$query .= ' default '.floatval($prop['default']);
				else
					$query .= ' default '.intval($prop['default']);
			}
			if($field == 'field' && $dbtype == 'sqlite')
				$query .= ' PRIMARY KEY';
		}
		$db->write_query('CREATE TABLE '.$db->table_prefix.'threadfields (
			'.$query.'
			'.($dbtype != 'sqlite' ? ',
				PRIMARY KEY (field)
				'.($dbtype != 'pg' ? ',
					KEY (disporder)
				':'').'
			':'').'
		)'.$create_table_suffix);
		// `allowsort` '.xthreads_db_numdef('tinyint').' not null default 0,
	}
	
	foreach(array(
		'grouping' => xthreads_db_fielddef('int').' not null default 0',
		'firstpostattop' => xthreads_db_fielddef('tinyint').' not null default 0',
		'inlinesearch' => xthreads_db_fielddef('tinyint').' not null default 0',
		'tplprefix' => 'text not null',
		'langprefix' => 'varchar(255) not null default \'\'',
		'allow_blankmsg' => xthreads_db_fielddef('tinyint').' not null default 0',
		'nostatcount' => xthreads_db_fielddef('tinyint').' not null default 0',
		'threadsperpage' => xthreads_db_fielddef('smallint').' not null default 0',
		'postsperpage' => xthreads_db_fielddef('smallint').' not null default 0',
		'force_postlayout' => 'varchar(15) not null default \'\'',
		'hideforum' => xthreads_db_fielddef('tinyint').' not null default 0',
		'hidebreadcrumb' => xthreads_db_fielddef('tinyint').' not null default 0',
		'defaultfilter' => 'text not null',
		'wol_announcements' => 'varchar(255) not null default \'\'',
		'wol_forumdisplay' => 'varchar(255) not null default \'\'',
		'wol_newthread' => 'varchar(255) not null default \'\'',
		'wol_attachment' => 'varchar(255) not null default \'\'',
		'wol_newreply' => 'varchar(255) not null default \'\'',
		'wol_showthread' => 'varchar(255) not null default \'\'',
		'wol_xtattachment' => 'varchar(255) not null default \'\''
	) as $field => $fdef) {
		if(!$db->field_exists($field, 'forums')) {
			$db->write_query('ALTER TABLE '.$db->table_prefix.'forums ADD COLUMN xthreads_'.$field.' '.$fdef);
		}
	}
	// add indexes
	foreach(array(
		'uid',
		'lastposteruid',
		'prefix',
		'icon',
	) as $afe) {
		if($afe == 'uid') continue; // we won't remove this from the above array
		$db->write_query('ALTER TABLE `'.$db->table_prefix.'threads` ADD KEY `xthreads_'.$afe.'` (`'.$afe.'`)', true);
	}
	$cache->update_forums();
	xthreads_buildcache_forums();
	
	xthreads_buildtfcache();
	xthreads_write_xtcachefile();
	
	
	xthreads_insert_templates(array(
		'editpost_first' => '<!-- this template allows you to have something different from the editpost template for when editing the first post of a thread; by default, will just display the editpost template -->'."\n".'{$editpost}',
		'forumdisplay_group_sep' => '<!-- stick your thread group separator template here -->',
		'forumdisplay_thread_null' => '<!-- stick your null thread template here -->',
		'showthread_noreplies' => '<!-- template to be used if there are no replies to a thread. For this to work with quick reply properly, you should uncomment and use the following -->
<!--
<div id="xthreads_noreplies">
Put your stuff here
</div>
-->',
		'forumdisplay_searchforum_inline' => '<form action="forumdisplay.php" method="get">
	<span class="smalltext"><strong>{$lang->search_forum}</strong></span>
	<input type="text" class="textbox" name="search" value="{$searchval}" /> {$gobutton}
	<input type="hidden" name="fid" value="{$fid}" />
	<input type="hidden" name="sortby" value="{$sortby}" />
	<input type="hidden" name="order" value="{$sortordernow}" />
	<input type="hidden" name="datecut" value="{$datecut}" />
	{$xthreads_forum_filter_form}
	</form><br />',
		'threadfields_inputrow' => '<tr>
<td class="{$altbg}" width="20%"><strong>{$tf[\'title\']}</strong></td>
<td class="{$altbg}">{$inputfield}<small style="display: block;">{$tf[\'desc\']}</small></td>
</tr>'
	));
	
	
	
	// admin permissions - default to all allow
	$query = $db->simple_select('adminoptions', 'uid,permissions');
	while($adminopt = $db->fetch_array($query)) {
		$perms = unserialize($adminopt['permissions']);
		$perms['config']['threadfields'] = 1;
		$db->update_query('adminoptions', array('permissions' => $db->escape_string(serialize($perms))), 'uid='.$adminopt['uid']);
	}
	$db->free_result($query);
}

function xthreads_insert_templates($new_templates, $set=-1) {
	global $mybb, $db;
	if($mybb->version_code >= 1500) // MyBB 1.6 beta or final
		$tpl_ver = 1600;
	elseif($mybb->version_code >= 1400) {
		//$tpl_ver = min($mybb->version_code, 1411);
		$tpl_ver = 1413;
	}
	foreach($new_templates as $name => &$tpl) {
		$db->insert_query('templates', array(
			'title' => $name,
			'template' => $db->escape_string($tpl),
			'sid' => $set,
			'version' => $tpl_ver
		));
	}
}

function xthreads_undo_template_edits() {
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('editpost', '#\\{\\$extra_threadfields\\}#', '', 0);
	find_replace_templatesets('newthread', '#\\{\\$extra_threadfields\\}#', '', 0);
	find_replace_templatesets('showthread', '#\\{\\$first_post\\}#', '', 0);
	find_replace_templatesets('forumdisplay_threadlist', '#\\{\\$nullthreads\\}#', '', 0);
}

function xthreads_activate() {
	global $db, $cache, $lang;
	$db->insert_query('tasks', array(
		'title' => $db->escape_string($lang->xthreads_orphancleanup_name),
		'description' => $db->escape_string($lang->xthreads_orphancleanup_desc),
		'file' => 'xtaorphan_cleanup',
		'minute' => '35',
		'hour' => '10',
		'day' => '*',
		'month' => '*',
		'weekday' => '*',
		'nextrun' => TIME_NOW + 86400*3, // low priority - we'll assume you don't accumulate many orphans in the first few days :P
		'lastrun' => 0,
		'enabled' => 1,
		'logging' => 1,
		'locked' => 0,
	));
	$cache->update_tasks();
	
	// prevent doubling of template edits
	xthreads_undo_template_edits();
	// following original in the _install() function, as these variables aren't evaluated when deactivated
	// but putting them here has the advantage of allowing users to redo template edits with new themes
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('editpost', '#\\{\\$posticons\\}#', '{$extra_threadfields}{$posticons}');
	find_replace_templatesets('newthread', '#\\{\\$posticons\\}#', '{$extra_threadfields}{$posticons}');
	find_replace_templatesets('showthread', '#\\{\\$posts\\}#', '{$first_post}{$posts}');
	find_replace_templatesets('forumdisplay_threadlist', '#\\{\\$threads\\}#', '{$threads}{$nullthreads}');
}
function xthreads_deactivate() {
	global $db, $cache;
	$db->delete_query('tasks', 'file="xtaorphan_cleanup"');
	$cache->update_tasks();
	
	xthreads_undo_template_edits();
}

function xthreads_uninstall() {
	global $db, $cache, $mybb;
	
	if($mybb->input['no']) {
		admin_redirect(xthreads_admin_url('config', 'plugins'));
		exit;
	}
	if(!$mybb->input['confirm_uninstall']) {
		$link = 'index.php?confirm_uninstall=1&amp;'.htmlspecialchars($_SERVER['QUERY_STRING']);
		
		$GLOBALS['page']->output_confirm_action($link, $GLOBALS['lang']->xthreads_confirm_uninstall);
		exit;
	} else
		unset($mybb->input['confirm_uninstall']);
	
	$query = $db->simple_select('adminoptions', 'uid,permissions');
	while($adminopt = $db->fetch_array($query)) {
		$perms = unserialize($adminopt['permissions']);
		unset($perms['config']['threadfields']);
		$db->update_query('adminoptions', array('permissions' => $db->escape_string(serialize($perms))), 'uid='.$adminopt['uid']);
	}
	$db->free_result($query);
	
	if($db->table_exists('threadfields_data'))
		$db->write_query('DROP TABLE '.$db->table_prefix.'threadfields_data');
	if($db->table_exists('threadfields'))
		$db->write_query('DROP TABLE '.$db->table_prefix.'threadfields');
	if($db->table_exists('xtattachments'))
		$db->write_query('DROP TABLE '.$db->table_prefix.'xtattachments');
	
	// remove any indexes added on the threads table
	foreach(array(
		'uid',
		'lastposteruid',
		'prefix',
		'icon',
	) as $afe) {
		if($afe == 'uid') continue; // we won't remove this from the above array
		$db->write_query('ALTER TABLE `'.$db->table_prefix.'threads` DROP KEY `xthreads_'.$afe.'`', true);
	}
	
	$fields = array(
		'xthreads_grouping',
		'xthreads_firstpostattop',
		'xthreads_inlinesearch',
		'xthreads_tplprefix',
		'xthreads_langprefix',
		'xthreads_allow_blankmsg',
		'xthreads_nostatcount',
		'xthreads_threadsperpage',
		'xthreads_postsperpage',
		'xthreads_force_postlayout',
		'xthreads_hideforum',
		'xthreads_hidebreadcrumb',
		'xthreads_defaultfilter',
		'xthreads_addfiltenable', // legacy (uninstall w/o upgrade)
		//'xthreads_pull_firstpost',
		'xthreads_wol_announcements',
		'xthreads_wol_forumdisplay',
		'xthreads_wol_newthread',
		'xthreads_wol_attachment',
		'xthreads_wol_newreply',
		'xthreads_wol_showthread',
		'xthreads_wol_xtattachment',
	);
	foreach($fields as $k => &$f)
		if(!$db->field_exists($f, 'forums'))
			unset($fields[$k]);
	
	if(!empty($fields)) {
		switch($db->type) {
			case 'sqlite3': case 'sqlite2': case 'sqlite':
				$db->alter_table_parse($db->table_prefix.'forums', 'DROP '.implode(', DROP COLUMN ', $fields).'');
				break;
			case 'pgsql':
				foreach($fields as &$f)
					$db->write_query('ALTER TABLE '.$db->table_prefix.'forums
						DROP COLUMN '.$f);
				break;
			default:
				$db->write_query('ALTER TABLE '.$db->table_prefix.'forums
					DROP COLUMN '.implode(', DROP COLUMN ', $fields));
		}
	}
	$cache->update_forums();
	
	xthreads_delete_datacache('threadfields');
	xthreads_delete_datacache('xt_forums');
	
	@unlink(MYBB_ROOT.'cache/xthreads.php');
	
	$db->delete_query('templates', 'title IN ("editpost_first","forumdisplay_group_sep","forumdisplay_thread_null","showthread_noreplies","forumdisplay_searchforum_inline","threadfields_inputrow")');
	
	// try to determine and remove stuff added to the custom moderation table
	$query = $db->simple_select('modtools', 'tid,threadoptions');
	while($tool = $db->fetch_array($query)) {
		$opts = unserialize($tool['threadoptions']);
		if(isset($opts['edit_threadfields'])) {
			unset($opts['edit_threadfields']);
			$db->update_query('modtools', array('threadoptions' => $db->escape_string(serialize($opts))), 'tid='.$tool['tid']);
		}
	}
	
}

function xthreads_delete_datacache($key) {
	global $cache, $db;
	$cache->update($key, null);
	if(is_object($cache->handler) && method_exists($cache->handler, 'delete')) {
		$cache->handler->delete($key);
	}
	$db->delete_query('datacache', 'title="'.$db->escape_string($key).'"');
}

// rebuild threadfields cache on phptpl activation/deactivation
function xthreads_plugins_phptpl_activate() { xthreads_plugins_phptpl_reparse(true); }
function xthreads_plugins_phptpl_deactivate() { xthreads_plugins_phptpl_reparse(false); }
function xthreads_plugins_phptpl_reparse($active) {
	if($GLOBALS['codename'] != 'phptpl' || !function_exists('phptpl_evalphp')) return;
	
	define('XTHREADS_ALLOW_PHP_THREADFIELDS_ACTIVATION', $active); // define is maybe safer?
	xthreads_buildtfcache();
}

