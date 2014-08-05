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

function xthreads_info() {
	global $lang, $mybb, $plugins;
	$lang->load('xthreads');
	
	$info = array(
		'name'          => '<span style="color: #008000;">'.$lang->xthreads_name.'</span>',
		'description'   => $lang->xthreads_desc,
		'website'       => 'http://mybbhacks.zingaburga.com/showthread.php?tid=288',
		'author'        => 'ZiNgA BuRgA',
		'authorsite'    => 'http://mybbhacks.zingaburga.com/',
		'version'       => xthreads_format_version_number(XTHREADS_VERSION),
		'compatibility' => '14*,15*,16*,17*,18*',
		'guid'          => ''
	);
	if(is_object($plugins)) {
		$info = $plugins->run_hooks('xthreads_info_needs_moar_pimpin', $info);
	}
	if($mybb->input['action'] || !is_object($GLOBALS['table'])) // not main plugins page
		return $info;
	if($mybb->version_code >= 1700) return $info;
	
	static $done = false;
	if(!$done) {
		$done = true;
		// let's have some fun here
		control_object($GLOBALS['table'], '
			function construct_row($extra = array()) {
				static $done=false;
				if(!$done) {
					xthreads_info_no_more_fun();
					$done = true;
				}
				return parent::construct_row($extra);
			}
		');
		$lang->__activate = $lang->activate;
		$lang->__deactivate = $lang->deactivate;
		$lang->__install_and_activate = $lang->install_and_activate;
		$lang->__uninstall = $lang->uninstall;
		
		$imgcode = '<![if gte IE 8]><img src="data:image/png;base64,{data}" alt="" style="vertical-align: middle;" /><![endif]> ';
		$lang->install_and_activate = str_replace('{data}', xthreads_install_img_install(), $imgcode).'<span style="color: #008000;">'.$lang->xthreads_install_and_activate.'</span>';
		$lang->activate = str_replace('{data}', xthreads_install_img_activate(), $imgcode).'<span style="color: #FF8000;">'.$lang->activate.'</span>';
		$lang->deactivate = str_replace('{data}', xthreads_install_img_deactivate(), $imgcode).'<span style="color: #20A0A0;">'.$lang->deactivate.'</span>';
		$lang->uninstall = str_replace('{data}', xthreads_install_img_uninstall(), $imgcode).'<span style="color: #FF0000;">'.$lang->uninstall.'</span>';
	}
	return array(
		'name'          => '</strong><small style="font-family: Tempus Sans ITC, Lucida Calligraphy, Harrington, Comic Sans MS, Some other less-readable goofy font, Serif"><a href="'.$info['website'].'">'.$info['name'].'</a> v'.$info['version'].', '.$lang->xthreads_fun_desc.'<!-- ',
		'author'        => '--><i><small>',
		'compatibility' => $info['compatibility'],
		'guid'          => $info['guid']
	);
}
function xthreads_info_no_more_fun() {
	global $lang;
	$lang->install_and_activate = $lang->__install_and_activate;
	$lang->activate = $lang->__activate;
	$lang->deactivate = $lang->__deactivate;
	$lang->uninstall = $lang->__uninstall;
}

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


function xthreads_install_img_install() {
	return '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';
}
function xthreads_install_img_uninstall() {
	return 'iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAC9UlEQVR4Xr3Wb2hVdRzH8c/3d87v3J2z3bN7d9du97L2J5nNmQ/miKZjiSU5SCmm7s4H94YPSnoiwd2wgmYrS+kPFkOKsCat0iH4N6wH9uDKzNG00ZAxzf4wLwxRs8mNKL1+hOBAG+PuupEv+D5+P/rAFyT/17t3AU+t61ZGHOcBzJESQXM4/MgSn69hSiBaWBhJvfv2SQ6dIg9+ycOJ2EBU6zrkqQQIfNC0fPfka108sK71yoNA65TAV3t7T5AkT35D7t9D9n9Idmzi5lJ3B2axNRrZzs4kb7/RzfbFdX0ANABMCSytrlo91PXKBI9+Tu59h9lkgny+hXymjv2lehCAgWmWC1b92lB/nZuf4/al9acBROGZHvA0ld23aaytJcMta5ltqWFmSYB8uJjfBvW4ACEvsMOUfVy4gP80PspG234dnlkCHite4uz8vTZE1viZjvjIsMV+2/ghCiw7otUIi2yedf0sU6oNnnwDHgso3+WYA5mg5oUik5dsgxNa8YYS9imVKQAa4JlLwNOsJP6bpXjOEP4E8DuApSIr4ZlXQICXlHw0ImAa4GUBx03hy4Y6MO+AI+IeN9TFGwJeA7hbZPRVQw3Qb5JBzTVabZ1zwFUSOKUkfRXgJYApUxEidQDQXWB8zWqH16schkQa7zqgRQpSSsavABwVMG0pVinZiP94K6CP8fEQ+yrsM3cXEMEXIkN/APzeEN5yDCa0+gQz2FduD7I5xKcdI5l3IAm8f1PAQUNI12SvY44B0JhZ4cWaosk/F7kEUDxrIAos/kXAC1o44Zo8X6IZAFYhhwqgibXFfLHY6pk18N794c9YHeWoA2bDFtsLjF3IQ4ff2pmt9FMBJTkDJ55aPczYGl5eEOTfFRYXmbIBefq5zMk+a5tdOQOpji0/sudN3ow9Rq6oZLVgPfLUqo3Ep7YezhnY9uQTe3j+NHmohyNrl/0FIII8mYBRo2QFAF+uHVgfx2Opc50v8CGfjmPOcg9N/r35uzdfxR2oy6klFaNqGwAAAABJRU5ErkJggg==';
}
function xthreads_install_img_activate() {
	return 'iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAADq0lEQVR4Xp3TS2xUZRjG8f83M+fMnLkxZTodZ8YOIm25F2nACCEk4CWkIUoCxnRlYtwaExIxkRgSNsa4cYUbY4MLjSxAIiUqbVORWuQSC4rQ0pYyFMOll+mZy5mZM+f7XLAxBFNOn+Tdvr/kffMIpRSP56suAUClDC2bd70XnJ14ozR9877H50cphBcs5eDYVUc4ypD1op21sI51nVbdPBYfT4oAIaCch+dXrnq35c1d7eTOg08HFKg6ODVwHKhF6Pvwhxv3560cwFMBQoAEYn6SGe+FdpDQGAKPDUKCdKDug4hG7adhJu18VznBsCvAKkFbG68b65MwUQRpgacOOCDr4NVhcpwrx0YvFg2GQxo8NWBXoVqBzMbwXrwpmJsBnwThADZ4l4A+QuHcCMNT1Q+MJtCVC6CiIKBDvLZ8E/MGlOvgBXDAo4F8AHPjXBgyevLR8i8Jxf/GwxMidPDZpDU7G6echyIwX4eChLIO0+c5942i//fZU8uaQgmBMJRyAcgCaBadkWwjCBOkDVYdbANuXYacSSAVkXtfaf4iXUldrCpl1d0A4aB33fodnW/rgQbM8RJm0YaAAdY9mLkJJdi0Jedpzkr+nBjb79FB01wAseYVz6jG+O3eL7/7/LPDp6/cM33QaIMzBBkeTSXO2b7aoGzmuE+CwsWTZ+6M9t79e7TX0WD1quSJtm2JDcyegoQNPiAFt07qTJr3Po5ngSoIN0/WvBBeCqpIcN1La/eQvgH6A0gkIdVIbTbJiaPmUCat/tDLZJQCpAsAL9Q1COZ5a9kLZbBzEOoAYw00rGV8agWpDSu3LPfExizBQwCEixPVJFQLsG17+/vh1LMUbttEUq1AFGzF6n1BmsxBThzP73MMahqg3JzIAwQ9UA3OfXvkQH//xLU4pNsgvBLSu5n9eoTvuy+9U4szoCtAAW6arAsQARi8fufT5/xkNmzN7qQShujLcLWHvqM/Hi5l6I44/93rApAAEjQJLR1Nq0mHwL8EzKucPXRwYCrKoagGUgGLARQAICoQSTZsJmiCfZe/Dhy5e6nCjlAclARYJCAA5QFfiY5QtLAEpri+/1cGRqe3GhnQJCgWD4AA6QevJV5dmrYZOzjMmcGHe/SN5Py1R7hwDTzegyosa43tnP+tgTM9ox/pL3IyUAElFly+cNFsCY4Jzdntr128Jn+Wa/gkUF3gLG6AegVisXDrtDn/z4Qz1hkMgFpwu5se+EAgYpOly7u1AI6qsej8C+nDfgnmbZ2GAAAAAElFTkSuQmCC';
}
function xthreads_install_img_deactivate() {
	return 'iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAFWklEQVR4XrWSS2xcdxWHv/99zXjGnplre+xx/KonTWNSC9LgYBSCVESoRNUHCoSqbFqEKl4SS8QCiBJA7NlVLCIkgkTVIiSgCyjQli5aqJvQEkRVYvKwm4lnPJ65d2bu838PyKoUpYOUBeKTzuJszqefzk+JCACfePAMAKGyONBr0b5vjnWrRrF3k549IPC2wKhilQTdS+m++DKWfwXl1pBwgNg2KFA6m5PMOJZeefMZAIP/EWUYSBTAoItpWyjUEpl8EOCOAgWIUogIOuqTxTE6TUFxC9NCQo+stYNIASG+quD5OwuUQgvkB32s3oDizL0nC/tmT1bcSk1HIcQRQE0pPmtY1nFJRkmapUkxZc3I2T3ew+J9yHtWM4wInJiyVTjO/v3f9Su1TxleBxXs7hZKxnGpVlqDy1e/qcL4YXHnLuDsvGIkFwemnnlczOgV4M3bBIZhgVJYysLwemThxF3W/SvPvjtR+rBhgtPpEsUeQSNyrXzpUvn+k69ZhdGvdf/yu4Rq5fuqug/Vj79FGryjLePHQwkMZYAI+Z1t/rpcP+I99sj61KKLkfQhCskMC+waIMSbDbQ9sqaW7l0fu3H1ydhvFtN295OZ8BvTtu9WZnkBuHSb4K0L51Fak68sOPKDp/9kzeWx0y6YggBiKISULG9iuC4qyYjeuYBVnfmO6U4fif724hvKdH6p6h96Tf/rHww9WeqH0AsH6T3++XPuXROFRTsilyRIpJFMkYYpI3aO+qFDuJMugqawcgxjYXl/fml5p7x64idOpYbKFZatylh9SLB45NPMfvzUlLt2/Atj2S6qVCbyA5JWB9EKlWZMLx0kN1tH2xaSRtgzi1jlKqLUaWdipqBGy2pk5egzpUe/+o0hQXMspb1QegI1YLfd5t3rDYIoQ0fJ3phi0mx1+OfFN/CjiEQpsiRCmRaMlIzg5vVTykSsYnVu8Ooffj0ksLtdTK0/IElMzs6TUznCXZ+g00OliqTdxWh3KGaCMh3i/gCJIpSTgyTCqq983Vl79OndjZfOhd23XxhqUTwyms/EWLRNi16jgd/XhDdSxouTmDt5VOKiW30GMsBv3EBHMWZ5HO11Ucog0+poGu8ePRgsfrlWW2VI4G20nNyYW7Q1hJ4m7m6hpgfsTE+R+K8zkhbodzqYrSmIIkoz80icEgcBOo6gvUmim9S3H2iu9vcNt8jf2DiVG/QO0+6Rjx0m7EWS+lv0Vl5gcPhVmkvnKDh9pmaWmZhfQjCwo5B6bYaJqX1Y47MUiwf41fwvXj59z/eGE+RnLaNiGTnv+jYBGmt0mrHXP8bY+ttk+Spp5z5y0SrxqEfa6yG+T8UxcauTKNdl7/F+4/cjo0s7yjwwnGCw2f7pYHt7vRD3iTo+OmpTyt+N65yk3H+I6dIJ7EpG3OkQeT0qTo7xSoXYUIRZio48spvx+ZHLRZzLueEE2cWXgqvdrR+WPnrs2cwuU5iq4ro2qU6QUTAkIR8VSNMd+o0toryDKu3fq3CnsYlEfuBUD5/XvgNWNixAWpgbveeKiyunm/O5M+agT39rh0wEUGSJRmXCiNb4XoPmtZsEVy5hZCleElMbn/zcxIqKU+2DqGEBgBhC/+9/PBteK9i+8O2s30cyvdf7TKdkYYBKY6zQJ/R28YwOkiQYra3HGs1rz98IeqAMAPjRWQAQkb15P2Zp/LnKRx6QqQeflOlHnpLaZ74i0w9/6T/7EzL90BdlbPWE2PP3hObY+M+BUW5x212L/47SXvupzp9/+zPDzq0p264rO1dBMlu0jgFPgv5mlul1YB0QhhlOcGewAJNb3PGuEhH+n/wbrNqoOPOTlEUAAAAASUVORK5CYII=';
}
function xthreads_install_img_icon() {
	return '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';
}
