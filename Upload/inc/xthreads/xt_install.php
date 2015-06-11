<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

// don't add hooks if we've included this file for other reasons
if(isset($plugins) && is_object($plugins)) {
	if(XTHREADS_ALLOW_PHP_THREADFIELDS == 2) {
		$plugins->add_hook('admin_config_plugins_activate_commit', 'xthreads_plugins_phptpl_activate');
		$plugins->add_hook('admin_config_plugins_deactivate_commit', 'xthreads_plugins_phptpl_deactivate');
	}
	$plugins->add_hook('admin_config_plugins_activate_commit', 'xthreads_plugins_quickthread_install');
}
// if you don't wish to have XThreads modify any templates, set this value to true (the Quick Thread mod will still be done regardless)
// note that once you have XThreads installed, this will be stored in cache/xthreads.php instead
@define('XTHREADS_MODIFY_TEMPLATES', true);

function xthreads_is_installed() {
	static $is_installed = null;
	if(!isset($is_installed))
		$is_installed = $GLOBALS['db']->table_exists('threadfields');
	return $is_installed;
}

function xthreads_install() {
	global $db, $cache, $plugins;
	$plugins->run_hooks('xthreads_install_start');
	$create_table_suffix = $db->build_create_table_collation();
	
	$dbtype = xthreads_db_type();
	
	switch($dbtype) {
		case 'mysql':
			$engine = 'MyISAM';
			// try to see if a custom table engine is being used
			$query = $db->query('SHOW TABLE STATUS LIKE "'.$db->table_prefix.'threads"', true);
			if($query) {
				$eng = $db->fetch_field($query, 'Engine');
				if(in_array(strtolower($eng), array('innodb','aria','xtradb'))) // only stick to common possibilities to avoid issues with exquisite setups
					$engine = $eng;
			}
			$create_table_suffix = ' ENGINE='.$engine.$create_table_suffix;
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
			filename varchar(255) not null default \'\',
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
					$query .= ' default '.(float)$prop['default'];
				else
					$query .= ' default '.(int)$prop['default'];
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
		'langprefix' => 'text not null',
		'allow_blankmsg' => xthreads_db_fielddef('tinyint').' not null default 0',
		'nostatcount' => xthreads_db_fielddef('tinyint').' not null default 0',
		'fdcolspan_offset' => xthreads_db_fielddef('smallint', null, false).' not null default 0',
		'settingoverrides' => 'text not null',
		'postsperpage' => xthreads_db_fielddef('smallint').' not null default 0',
		'hideforum' => xthreads_db_fielddef('tinyint').' not null default 0',
		'hidebreadcrumb' => xthreads_db_fielddef('tinyint').' not null default 0',
		'defaultfilter' => 'text not null',
		'wol_announcements' => 'varchar(255) not null default \'\'',
		'wol_forumdisplay' => 'varchar(255) not null default \'\'',
		'wol_newthread' => 'varchar(255) not null default \'\'',
		'wol_attachment' => 'varchar(255) not null default \'\'',
		'wol_newreply' => 'varchar(255) not null default \'\'',
		'wol_showthread' => 'varchar(255) not null default \'\'',
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
	// increase size of sorting column
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` MODIFY `defaultsortby` varchar(255) NOT NULL default \'\'');
	$cache->update_forums();
	
	// check for xthreads_attachment.php supported URL type
	if(file_exists(MYBB_ROOT.'xthreads_attach.php')) { // if not, our admin is a dufus
		$rand = 'aA0._|'.mt_rand();
		$rand_md5 = md5($rand);
		$baseurl = $GLOBALS['mybb']->settings['bburl'].'/xthreads_attach.php';
		if(fetch_remote_file($baseurl.'/test/'.$rand) == $rand_md5)
			define('XTHREADS_ATTACH_USE_QUERY', -1); // our default works
		elseif(fetch_remote_file($baseurl.'?file=test/'.$rand) == $rand_md5)
			define('XTHREADS_ATTACH_USE_QUERY', 1);
		elseif(fetch_remote_file($baseurl.'?file=test|'.$rand) == $rand_md5)
			define('XTHREADS_ATTACH_USE_QUERY', 2);
		// else, well, sucks for the user...
	}
	
	xthreads_buildtfcache();
	xthreads_write_xtcachefile();
	
	
	xthreads_insert_templates(xthreads_new_templates(), -2);
	xthreads_plugins_quickthread_tplmod();
	
	// admin permissions - default to all allow
	$query = $db->simple_select('adminoptions', 'uid,permissions');
	while($adminopt = $db->fetch_array($query)) {
		$perms = @unserialize($adminopt['permissions']);
		if(empty($perms)) continue; // inherited or just messed up
		$perms['config']['threadfields'] = 1;
		$db->update_query('adminoptions', array('permissions' => $db->escape_string(serialize($perms))), 'uid='.$adminopt['uid']);
	}
	$db->free_result($query);
	$plugins->run_hooks('xthreads_install_end');
}

function xthreads_insert_templates($new_templates, $set=-1) {
	global $mybb, $db;
	if($mybb->version_code >= 1700) // MyBB 1.8 beta or final
		$tpl_ver = 1800;
	elseif($mybb->version_code >= 1500) // MyBB 1.6 beta or final
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
function xthreads_new_templates() {
	return array(
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
		'post_threadfields_inputrow' => '<tr class="xthreads_inputrow">
<td class="{$altbg}" width="20%"><strong>{$tf[\'title\']}</strong></td>
<td class="{$altbg}">{$inputfield}<small style="display: block;">{$tf[\'desc\']}</small></td>
</tr>'
	) + ($GLOBALS['mybb']->version_code >= 1700 ? array(
		'showthread_threadfield_row' => '<tr><td width="15%" class="{$bgcolor}"><strong>{$title}</strong></td><td class="{$bgcolor}">{$value}</td></tr>',
		'showthread_threadfields' => '<tr><td id="showthread_threadfields" style="padding: 0;"><table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" width="100%">{$threadfields_display_rows}</table></td></tr>',
	) : array(
		'showthread_threadfield_row' => '<tr><td class="{$bgcolor}" width="15%"><strong>{$title}</strong></td><td class="{$bgcolor}">{$value}</td></tr>',
		'showthread_threadfields' => '{$threadfields_display_rows}',
	));
}

function xthreads_undo_template_edits() {
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('editpost', '#\\{\\$extra_threadfields\\}#', '', 0);
	find_replace_templatesets('newthread', '#\\{\\$extra_threadfields\\}#', '', 0);
	find_replace_templatesets('showthread', '#\\{\\$first_post\\}#', '', 0);
	find_replace_templatesets('showthread', '#\\{\\$threadfields_display\\}#', '', 0);
	find_replace_templatesets('forumdisplay_threadlist', '#\\{\\$nullthreads\\}#', '', 0);
	find_replace_templatesets('forumdisplay_threadlist', '#\\{\\$sort_by_prefix\\}#', '', 0);
	find_replace_templatesets('forumdisplay_threadlist', "#\n?".preg_replace("#[\t\r\n]+#", '\\s*', preg_quote(XTHREADS_INSTALL_TPLADD_EXTRASORT))."#", '', 0);
	find_replace_templatesets('forumdisplay_threadlist_sortrating', '#\\<option value="numratings" \\{\\$sortsel\\[\'numratings\'\\]\\}\\>\\{\\$lang-\\>sort_by_numratings\\}\\</option\\>#', '', 0);
}

define('XTHREADS_INSTALL_TPLADD_EXTRASORT', str_replace("\r", '',
'					<option value="icon" {$sortsel[\'icon\']}>{$lang->sort_by_icon}</option>
					<option value="lastposter" {$sortsel[\'lastposter\']}>{$lang->sort_by_lastposter}</option>
					<option value="attachmentcount" {$sortsel[\'attachmentcount\']}>{$lang->sort_by_attachmentcount}</option>
					{$xthreads_extra_sorting}'
));
function xthreads_activate() {
	global $db, $cache, $lang, $plugins;
	$plugins->run_hooks('xthreads_activate_start');
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
	
	if(XTHREADS_MODIFY_TEMPLATES) {
		// prevent doubling of template edits
		xthreads_undo_template_edits();
		// following original in the _install() function, as these variables aren't evaluated when deactivated
		// but putting them here has the advantage of allowing users to redo template edits with new themes
		require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
		find_replace_templatesets('editpost', '#\\{\\$posticons\\}#', '{$extra_threadfields}{$posticons}');
		find_replace_templatesets('newthread', '#\\{\\$posticons\\}#', '{$extra_threadfields}{$posticons}');
		find_replace_templatesets('showthread', '#\\{\\$posts\\}#', '{$first_post}{$posts}');
		if($GLOBALS['mybb']->version_code >= 1700)
			find_replace_templatesets('showthread', '#\<tr\>\s*\<td id\="posts_container"\>#', '{$threadfields_display}$0');
		else
			find_replace_templatesets('showthread', '#\\{\\$classic_header\\}#', '{$threadfields_display}{$classic_header}');
		find_replace_templatesets('forumdisplay_threadlist', '#\\{\\$threads\\}#', '{$threads}{$nullthreads}');
		find_replace_templatesets('forumdisplay_threadlist', '#\\<option value="subject" \\{\\$sortsel\\[\'subject\'\\]\\}\\>\\{\\$lang-\\>sort_by_subject\\}\\</option\\>#', '{$sort_by_prefix}<option value="subject" {$sortsel[\'subject\']}>{$lang->sort_by_subject}</option>');
		find_replace_templatesets('forumdisplay_threadlist', '#\\<option value="views" \\{\\$sortsel\\[\'views\'\\]\\}\\>\\{\\$lang-\\>sort_by_views\\}\\</option\\>#', '<option value="views" {$sortsel[\'views\']}>{$lang->sort_by_views}</option>'."\n".XTHREADS_INSTALL_TPLADD_EXTRASORT);
		find_replace_templatesets('forumdisplay_threadlist_sortrating', '#$#', '<option value="numratings" {$sortsel[\'numratings\']}>{$lang->sort_by_numratings}</option>');
	}
	$plugins->run_hooks('xthreads_activate_end');
}
function xthreads_deactivate() {
	global $db, $cache, $plugins;
	$plugins->run_hooks('xthreads_deactivate_start');
	$db->delete_query('tasks', 'file="xtaorphan_cleanup"');
	$cache->update_tasks();
	
	if(XTHREADS_MODIFY_TEMPLATES)
		xthreads_undo_template_edits();
	$plugins->run_hooks('xthreads_deactivate_end');
}

function xthreads_uninstall() {
	global $db, $cache, $mybb, $plugins;
	
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
	
	$plugins->run_hooks('xthreads_uninstall_start');
	
	$query = $db->simple_select('adminoptions', 'uid,permissions');
	while($adminopt = $db->fetch_array($query)) {
		$perms = @unserialize($adminopt['permissions']);
		if(empty($perms)) continue; // inherited or just messed up
		unset($perms['config']['threadfields']);
		$db->update_query('adminoptions', array('permissions' => $db->escape_string(serialize($perms))), 'uid='.$adminopt['uid']);
	}
	$db->free_result($query);
	
	if($db->table_exists('threadfields_data'))
		$db->write_query('DROP TABLE '.$db->table_prefix.'threadfields_data');
	if($db->table_exists('threadfields'))
		$db->write_query('DROP TABLE '.$db->table_prefix.'threadfields');
	if($db->table_exists('xtattachments')) {
		// remove attachments first
		require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
		$query = $db->simple_select('xtattachments', 'aid,indir,attachname');
		while($xta = $db->fetch_array($query)) {
			xthreads_rm_attach_fs($xta);
		}
		$db->free_result($query);
		$db->write_query('DROP TABLE '.$db->table_prefix.'xtattachments');
	}
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
		'xthreads_fdcolspan_offset',
		'xthreads_settingoverrides',
		'xthreads_postsperpage',
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
	
	// remove any custom default sorts and reduce size of sorting column back to original
	$db->update_query('forums', array('defaultsortby' => ''), 'defaultsortby LIKE "tf_%" OR defaultsortby LIKE "tfa_%"');
	$db->write_query('ALTER TABLE `'.$db->table_prefix.'forums` MODIFY `defaultsortby` varchar(10) NOT NULL default \'\'');
	$cache->update_forums();
	
	xthreads_delete_datacache('threadfields');
	
	@unlink(MYBB_ROOT.'cache/xthreads.php');
	@unlink(MYBB_ROOT.'cache/xthreads_evalcache.php');
	
	$db->delete_query('templates', 'title IN ("'.implode('","', array_keys(xthreads_new_templates())).'") AND sid=-2');
	
	// revert QuickThread modification
	if(function_exists('quickthread_uninstall')) {
		$tpl = $db->fetch_array($db->simple_select('templates', 'tid,template', 'title="forumdisplay_quick_thread" AND sid=-1', array('limit' => 1)));
		if($tpl && strpos($tpl['template'], '{$GLOBALS[\'extra_threadfields\']}') !== false) {
			$newtpl = preg_replace('~\\{\\$GLOBALS\\[\'extra_threadfields\'\\]\\}'."\r?(\n\t{0,3})?".'~is', '', $tpl['template'], 1);
			if($newtpl != $tpl['template'])
				$db->update_query('templates', array('template' => $db->escape_string($newtpl)), 'tid='.$tpl['tid']);
		}
	}
	
	// try to determine and remove stuff added to the custom moderation table
	$query = $db->simple_select('modtools', 'tid,threadoptions');
	while($tool = $db->fetch_array($query)) {
		$opts = unserialize($tool['threadoptions']);
		if(isset($opts['edit_threadfields'])) {
			unset($opts['edit_threadfields']);
			$db->update_query('modtools', array('threadoptions' => $db->escape_string(serialize($opts))), 'tid='.$tool['tid']);
		}
	}
	
	$plugins->run_hooks('xthreads_uninstall_end');
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

function xthreads_plugins_quickthread_install() {
	if($GLOBALS['codename'] != 'quickthread' || !$GLOBALS['install_uninstall']) return;
	xthreads_plugins_quickthread_tplmod();
}
function xthreads_plugins_quickthread_tplmod() {
	if(!function_exists('quickthread_install')) return;
	global $db;
	$tpl = $db->fetch_array($db->simple_select('templates', 'tid,template', 'title="forumdisplay_quick_thread" AND sid=-1', array('limit' => 1)));
	if($tpl && strpos($tpl['template'], '{$GLOBALS[\'extra_threadfields\']}') === false) {
		$newtpl = preg_replace('~(\<tbody.*?\<tr\>.*?)(\<tr\>)~is', '$1{\\$GLOBALS[\'extra_threadfields\']}
			$2', $tpl['template'], 1);
		if($newtpl != $tpl['template'])
			$db->update_query('templates', array('template' => $db->escape_string($newtpl)), 'tid='.$tpl['tid']);
	}
}

