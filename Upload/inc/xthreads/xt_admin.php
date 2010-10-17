<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

//define('XTHREADS_THREADFILTER_SQL_STRICT', 1);
define('XTHREADS_ADMIN_PATHSEP', ($mybb->version_code >= 1500 ? '-':'/'));
define('XTHREADS_ADMIN_CONFIG_PATH', 'index.php?module=config'.XTHREADS_ADMIN_PATHSEP);

$plugins->add_hook('admin_tools_cache_start', 'xthreads_admin_cachehack');
$plugins->add_hook('admin_tools_cache_rebuild', 'xthreads_admin_cachehack');
//$plugins->add_hook('admin_tools_recount_rebuild_start', 'xthreads_admin_statshack');
$plugins->add_hook('admin_config_menu', 'xthreads_admin_menu');
$plugins->add_hook('admin_config_action_handler', 'xthreads_admin_action');
$plugins->add_hook('admin_config_permissions', 'xthreads_admin_perms');
// priority = 9 for the following to hook in before (bad!) MyPlaza Turbo
$plugins->add_hook('admin_forum_management_edit', 'xthreads_admin_forumedit', 9);
$plugins->add_hook('admin_forum_management_add', 'xthreads_admin_forumedit', 9);
$plugins->add_hook('admin_forum_management_add_commit', 'xthreads_admin_forumcommit');
$plugins->add_hook('admin_forum_management_add_insert_query', 'xthreads_admin_forumcommit_myplazaturbo_fix');
$plugins->add_hook('admin_forum_management_edit_commit', 'xthreads_admin_forumcommit');

$plugins->add_hook('admin_tools_recount_rebuild_start', 'xthreads_admin_rebuildthumbs');

$plugins->add_hook('admin_load', 'xthreads_vercheck');
if($GLOBALS['run_module'] == 'config' && $GLOBALS['action_file'] == 'plugins.php') {
	require_once MYBB_ROOT.'inc/xthreads/xt_install.php';
} else {
	// this file might be included in plugin load
	$plugins->add_hook('admin_config_plugins_begin', 'xthreads_load_install');
	function xthreads_load_install() {
		global $plugins;
		require_once MYBB_ROOT.'inc/xthreads/xt_install.php';
	}
}

function xthreads_db_fielddef($type, $size=null, $unsigned=null) {
	// defaults
	if(!isset($unsigned)) {
		$unsigned = ($type != 'tinyint');
	}
	if($type == 'text') $size = 0;
	if($type == 'text' || $type == 'varchar')
		$unsigned = false;
	if(!isset($size)) {
		switch($type) {
			case 'tinyint': $size = 3; break;
			case 'smallint': $size = 5; break;
			case 'int': $size = 10; break;
			case 'bigint': $size = 20; break;
			case 'varchar': $size = 255; break;
			default: $size = 0;
		}
		if($size && $unsigned) ++$size;
	}
	if($size !== 0)
		$size = '('.$size.')';
	elseif($type == 'varchar') // force length for varchar
		$size = '(255)';
	else
		$size = '';
	$unsigned = ($unsigned ? ' unsigned':'');
	if($type == 'varchar' || $type == 'text') {
		$type .= $size;
		$size = '';
		//$unsigned = '';
	}
	switch(xthreads_db_type()) {
		case 'sqlite':
			if($type == 'tinyint') $type = 'smallint';
			return $type.$unsigned;
		case 'pg':
			if($type == 'tinyint') $type = 'smallint';
			return $type;
		default: // mysql
			return $type.$size.$unsigned;
	}
}

function &xthreads_threadfields_props() {
	static $props = array(
		'field' => array(
			'db_size' => 50,
			'default' => '',
		),
		'title' => array(
			'db_size' => 100,
			'default' => '',
		),
		'forums' => array(
			'db_size' => 255,
			'default' => '',
			'inputtype' => 'forum_select',
		),
		'editable' => array(
			'db_type' => 'tinyint',
			'default' => XTHREADS_EDITABLE_ALL,
			'inputtype' => 'select_box',
		),
		'editable_gids' => array(
			'db_size' => 255,
			'default' => '',
			'inputtype' => 'group_select',
		),
		'viewable_gids' => array(
			'db_size' => 255,
			'default' => '',
			'inputtype' => 'group_select',
		),
		'unviewableval' => array(
			'db_type' => 'text',
			'default' => '',
		),
		'blankval' => array(
			'db_type' => 'text',
			'default' => '',
		),
		'dispformat' => array(
			'db_type' => 'text',
			'default' => '{VALUE}',
		),
		'dispitemformat' => array(
			'db_type' => 'text',
			'default' => '{VALUE}',
		),
		'formatmap' => array(
			'db_type' => 'text',
			'default' => '',
		),
		'textmask' => array(
			'db_size' => 150,
			'default' => '^.*$',
		),
		'maxlen' => array(
			'default' => 0,
		),
		'vallist' => array(
			'db_type' => 'text',
			'default' => '',
		),
		'multival' => array(
			'db_size' => 100,
			'default' => '',
		),
		'sanitize' => array(
			'db_type' => 'smallint',
			'default' => 0x1A8, //XTHREADS_SANITIZE_HTML | XTHREADS_SANITIZE_PARSER_NOBADW | XTHREADS_SANITIZE_PARSER_MYCODE | XTHREADS_SANITIZE_PARSER_SMILIES | XTHREADS_SANITIZE_PARSER_VIDEOCODE,
			'inputtype' => '', // custom
		),
		'allowfilter' => array(
			'default' => false,
		),
		
		'desc' => array(
			'db_size' => 255,
			'default' => '',
		),
		'inputtype' => array(
			'db_type' => 'tinyint',
			'default' => XTHREADS_INPUT_TEXT,
			'inputtype' => 'select_box',
		),
		'disporder' => array(
			'db_unsigned' => true,
			'default' => 1,
		),
		'tabstop' => array(
			'default' => true,
		),
		'hideedit' => array(
			'default' => false,
		),
		'formhtml' => array(
			'db_type' => 'text',
			'default' => '',
		),
		'defaultval' => array(
			'db_size' => 255,
			'default' => '',
		),
		'fieldwidth' => array(
			'db_type' => 'smallint',
			'default' => 40,
		),
		'fieldheight' => array(
			'db_type' => 'smallint',
			'default' => 5,
		),
		
		'filemagic' => array(
			'db_size' => 255,
			'default' => '',
		),
		'fileexts' => array(
			'db_size' => 255,
			'default' => '',
		),
		'filemaxsize' => array(
			'default' => 0,
		),
		'fileimage' => array(
			'db_size' => 30,
			'default' => '',
		),
		'fileimgthumbs' => array(
			'db_size' => 255,
			'default' => '',
		),
	);
	
	static $parsed = false;
	if(!$parsed) {
		$parsed = true;
		foreach($props as $field => &$d) {
			if(!isset($d['datatype']))
				$d['datatype'] = gettype($d['default']);
			if(!isset($d['db_type'])) {
				switch($d['datatype']) {
					case 'boolean':
						$d['db_type'] = 'tinyint';
						break;
					case 'integer':
						$d['db_type'] = 'int';
						break;
					case 'string':
						$d['db_type'] = 'varchar';
						break;
					case 'double':
						$d['db_type'] = 'float';
						break;
				}
			}
			if(!isset($d['inputtype'])) {
				switch($d['datatype']) {
					case 'varchar': case 'tinyint': case 'smallint': case 'int': case 'bigint': case 'float':
						if($d['datatype'] == 'boolean')
							$d['inputtype'] = 'yes_no_radio';
						else
							$d['inputtype'] = 'text_box';
						break;
					case 'text':
						$d['inputtype'] = 'text_area';
						break;
				}
			}
		}
	}
	return $props;
}


function xthreads_write_xtcachefile() {
	if($fp = @fopen(MYBB_ROOT.'cache/xthreads.php', 'w')) {
		fwrite($fp, '<?php if(!defined("IN_MYBB")) exit;
return array(
	"version" => '.XTHREADS_VERSION.'
);');
		fclose($fp);
	}
}
function xthreads_buildtfcache() {
	global $db, $cache;
	
	$sanitise_fields_normal = array('VALUE', 'RAWVALUE');
	$sanitise_fields_file = array('DOWNLOADS', 'DOWNLOADS_FRIENDLY', 'FILENAME', 'UPLOADMIME', 'URL', 'FILESIZE', 'FILESIZE_FRIENDLY', 'MD5HASH', 'UPLOAD_TIME', 'UPLOAD_DATE', 'UPDATE_TIME', 'UPDATE_DATE', 'ICON');
	$sanitise_fields_none = array();
	$cd = array();
	$query = $db->simple_select('threadfields', '*', '', array('order_by' => '`disporder`', 'order_dir' => 'asc'));
	while($tf = $db->fetch_array($query)) {
		// remove unnecessary fields
		if($tf['editable_gids']) $tf['editable'] = 0;
		if(!$tf['viewable_gids']) unset($tf['unviewableval']);
		if($tf['inputtype'] != XTHREADS_INPUT_CUSTOM)
			unset($tf['formhtml']);
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_FILE:
			case XTHREADS_INPUT_FILE_URL:
				unset(
					$tf['dispitemformat'],
					$tf['formatmap'],
					$tf['textmask'],
					$tf['maxlen'],
					$tf['vallist'],
					$tf['multival'],
					$tf['sanitize'],
					$tf['allowfilter'],
					$tf['defaultval'],
					$tf['fieldheight'],
					$tf['sanitize']
				);
				if(!$tf['fileimage'])
					unset($tf['fileimgthumbs']);
				break;
			
			case XTHREADS_INPUT_TEXTAREA:
				unset($tf['allowfilter']);
				// fall through
			case XTHREADS_INPUT_TEXT:
			//case XTHREADS_INPUT_CUSTOM:
				unset($tf['vallist']);
				break;
			case XTHREADS_INPUT_RADIO:
				unset($tf['multival']);
				// fall through
			case XTHREADS_INPUT_CHECKBOX:
			case XTHREADS_INPUT_SELECT:
				unset($tf['textmask'], $tf['maxlen']);
				
				// fix multival here; we don't want it to be an array for textual inputs
				if($tf['multival']) {
					$tf['defaultval'] = explode("\n", str_replace("\r", '', $tf['defaultval']));
				}
		}
		
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_FILE:
			case XTHREADS_INPUT_FILE_URL:
				break;
			case XTHREADS_INPUT_TEXT:
			case XTHREADS_INPUT_CHECKBOX:
				unset($tf['fieldheight']);
				// fall through
			default:
				unset(
					$tf['filemagic'],
					$tf['fileexts'],
					$tf['filemaxsize'],
					$tf['fileimage'],
					$tf['fileimgthumbs']
				);
		}
		
		if(!$tf['multival'])
			unset($tf['dispitemformat']);
		
		
		// preformat stuff to save time later
		if($tf['formatmap'])
			$tf['formatmap'] = @unserialize($tf['formatmap']);
		else
			$tf['formatmap'] = null;
		
		if($tf['vallist']) {
			$tf['vallist'] = array_unique(array_map('trim', explode("\n", str_replace("\r", '', $tf['vallist']))));
		}
		// TODO: explode forums, fileexts?
		if($tf['editable_gids']) {
			$tf['editable_gids'] = array_unique(explode(',', $tf['editable_gids']));
		}
		if($tf['viewable_gids']) {
			$tf['viewable_gids'] = array_unique(explode(',', $tf['viewable_gids']));
		}
		if($tf['fileimgthumbs']) {
			$tf['fileimgthumbs'] = array_unique(explode('|', $tf['fileimgthumbs']));
		}
		if($tf['filemagic']) {
			$tf['filemagic'] = array_map('urldecode', array_unique(explode('|', $tf['filemagic'])));
		}
		// santize -> separate mycode stuff?
		
		if($tf['allowfilter']) {
			$tf['ignoreblankfilter'] = ($tf['editable'] == XTHREADS_EDITABLE_REQ);
			if($tf['ignoreblankfilter'] && !empty($tf['vallist'])) {
				$tf['ignoreblankfilter'] = !in_array('', $tf['vallist']);
			}
		}
		
		// sanitise eval'd stuff
		if($tf['inputtype'] == XTHREADS_INPUT_FILE) {
			$sanitise_fields =& $sanitise_fields_file;
		}
		else {
			$sanitise_fields =& $sanitise_fields_normal;
			/*
			$tf['regex_tokens'] = false;
			if($tf['inputtype'] != XTHREADS_INPUT_FILE && (
				($tf['unviewableval']  && preg_match('~\<(?:RAW)?VALUE\$\d+\>~', $tf['unviewableval'])) ||
				($tf['dispformat']     && preg_match('~\<(?:RAW)?VALUE\$\d+\>~', $tf['dispformat'])) ||
				($tf['dispitemformat'] && preg_match('~\<(?:RAW)?VALUE\$\d+\>~', $tf['dispitemformat']))
			)) {
				$tf['regex_tokens'] = true;
			}
			*/
			$tf['regex_tokens'] = (
				($tf['unviewableval']  && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['unviewableval'])) ||
				($tf['dispformat']     && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['dispformat'])) ||
				($tf['dispitemformat'] && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['dispitemformat']))
			);
		}
		if($tf['unviewableval']) xthreads_sanitize_eval($tf['unviewableval'], $sanitise_fields);
		if($tf['dispformat']) xthreads_sanitize_eval($tf['dispformat'], $sanitise_fields);
		if($tf['dispitemformat']) xthreads_sanitize_eval($tf['dispitemformat'], $sanitise_fields);
		if($tf['blankval']) xthreads_sanitize_eval($tf['blankval'], $sanitise_fields_none);
		if(!empty($tf['formatmap']) && is_array($tf['formatmap']))
			foreach($tf['formatmap'] as &$fm)
				xthreads_sanitize_eval($fm, $sanitise_fields_none);
		
		$cd[$tf['field']] = $tf;
	}
	$db->free_result($query);
	$cache->update('threadfields', $cd);
}
// sanitises string $s so that we can directly eval it during "run-time" rather than performing sanitisation there
function xthreads_sanitize_eval(&$s, &$fields) {
	// the following won't work properly with array indexes which have non-alphanumeric and underscore chars; also, it won't do ${var} syntax
	// also, damn PHP's magic quotes for preg_replace - but it does assist with backslash fun!!!
	$s = preg_replace(
		array(
			'~\\{\\\\\\$([a-zA-Z_][a-zA-Z_0-9]*)((-\\>[a-zA-Z_][a-zA-Z_0-9]*|\\[(\'|\\\\"|)[a-zA-Z_ 0-9]+\\4\\])*)\\}~e',
			'~\{\\\\\$forumurl\\\\\$\}~i',
			'~\{\\\\\$forumurl\?\}~i',
			'~\{\\\\\$threadurl\\\\\$\}~i',
			'~\{\\\\\$threadurl\?\}~i'
		), array(
			'\'{$GLOBALS[\\\'$1\\\']\'.strtr(\'$2\', array(\'\\\\\\\\\\\'\' => \'\\\'\', \'\\\\\\\\\\\\\\\\"\' => \'\\\'\')).\'}\'', // rewrite double-quote to single quotes, cos it's faster
			'{$GLOBALS[\'forumurl\']}',
			'{$GLOBALS[\'forumurl_q\']}',
			'{$GLOBALS[\'threadurl\']}',
			'{$GLOBALS[\'threadurl_q\']}',
		), strtr($s, array('\\' => '\\\\', '$' => '\\$', '"' => '\\"'))
	);
	
	// replace conditionals
	@include_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	if(function_exists('xthreads_phptpl_parsetpl')) {
		xthreads_phptpl_parsetpl($s, $fields);
	}
	
	// replace value tokens at the end
	$do_value_repl = false;
	$tr = array();
	foreach($fields as &$f) {
		$tr['{'.$f.'}'] = '{$vars[\''.$f.'\']}';
		
		if($f == 'VALUE') $do_value_repl = true;
	}
	if($do_value_repl) $s = preg_replace('~\{((?:RAW)?VALUE)\\\\?\$(\d+)\}~', '{$vars[\'$1$\'][$2]}', $s);
	$s = strtr($s, $tr);
	
	//if(strpos($s, '{$') === false) // reverse our eval optimisation
	//	$s = strtr($s, array('\\$' => '$', '\\"' => '"', '\\\\' => '\\'));
}


function xthreads_admin_cachehack() {
	control_object($GLOBALS['cache'], '
		function update_threadfields() {
			xthreads_buildtfcache();
		}
	');
}
function xthreads_admin_menu(&$menu) {
	global $lang;
	$lang->load('xthreads');
	$menu['32'] = array('id' => 'threadfields', 'title' => $lang->custom_threadfields, 'link' => XTHREADS_ADMIN_CONFIG_PATH.'threadfields');
}
function xthreads_admin_action(&$actions) {
	$actions['threadfields'] = array('active' => 'threadfields', 'file' => 'threadfields.php');
}
function xthreads_admin_perms(&$perms) {
	global $lang;
	if(!$lang->can_manage_threadfields) $lang->load('xthreads');
	$perms['threadfields'] = $lang->can_manage_threadfields;
}
function xthreads_admin_forumedit() {
	function xthreads_admin_forumedit_hook(&$args) {
		static $done = false;
		if($done || $args['title'] != $GLOBALS['lang']->misc_options) return;
		//$GLOBALS['plugins']->add_hook('admin_formcontainer_end', 'xthreads_admin_forumedit_hook2');
		$done = true;
		$fixcode='';
		if($GLOBALS['mybb']->version_code >= 1500) {
			// unfortunately, the above effectively ditches the added Misc row
			$GLOBALS['xt_fc_args'] = $args;
			$fixcode = 'call_user_func_array(array($this, "output_row"), $GLOBALS[\'xt_fc_args\']);';
		}
		control_object($GLOBALS['form_container'], '
			function end($return=false) {
				static $done=false;
				if(!$done && !$return) {
					$done = true;
					'.$fixcode.'
					parent::end($return);
					xthreads_admin_forumedit_run();
					return;
				}
				return parent::end($return);
			}
		');
	}
	$GLOBALS['plugins']->add_hook('admin_formcontainer_output_row', 'xthreads_admin_forumedit_hook');
	function xthreads_admin_forumedit_run() {
		global $lang, $form, $forum_data, $form_container;
		
		if(!$lang->xthreads_tplprefix) $lang->load('xthreads');
		$form_container = new FormContainer($lang->xthreads_opts);
		
		if(isset($forum_data['xthreads_tplprefix'])) // editing (or adding with submitted errors)
			$data =& $forum_data;
		else // adding
			$data = array(
				'xthreads_tplprefix' => '',
				'xthreads_grouping' => 0,
				'xthreads_firstpostattop' => 0,
				'xthreads_inlinesearch' => 0,
				'xthreads_threadsperpage' => 0,
				'xthreads_postsperpage' => 0,
				'xthreads_force_postlayout' => '',
				'xthreads_hideforum' => 0,
				'xthreads_allow_blankmsg' => 0,
				'xthreads_nostatcount' => 0,
				'xthreads_wol_announcements' => '',
				'xthreads_wol_forumdisplay' => '',
				'xthreads_wol_newthread' => '',
				'xthreads_wol_attachment' => '',
				'xthreads_wol_newreply' => '',
				'xthreads_wol_showthread' => '',
				'xthreads_wol_xtattachment' => '',
			);
		
		
		$inputs = array(
			'tplprefix' => 'text_box',
			'grouping' => 'text_box',
			'firstpostattop' => 'yes_no_radio',
			'inlinesearch' => 'yes_no_radio',
			'threadsperpage' => 'text_box',
			'postsperpage' => 'text_box',
			'force_postlayout' => array('' => 'none', 'horizontal' => 'horizontal', 'classic' => 'classic'),
			'hideforum' => 'yes_no_radio',
			'allow_blankmsg' => 'yes_no_radio',
			'nostatcount' => 'yes_no_radio',
		);
		foreach($inputs as $name => $type) {
			$name = 'xthreads_'.$name;
			$langdesc = $name.'_desc';
			$formfunc = 'generate_'.$type;
			if(is_array($type)) {
				foreach($type as &$t) {
					$ln = $name.'_'.$t;
					$t = $lang->$ln;
				}
				$html = $form->generate_select_box($name, $type, $data[$name], array($id => $name));
			}
			elseif($type == 'text_box')
				$html = $form->generate_text_box($name, $data[$name], array('id' => $name));
			elseif($type == 'yes_no_radio')
				$html = $form->generate_yes_no_radio($name, ($data[$name] ? '1':'0'), true);
			//elseif($type == 'check_box')
			//	$html = $form->generate_check_box($name, 1, $);
			$form_container->output_row($lang->$name, $lang->$langdesc, $html);
		}
		
		$wolfields = array(
			'xthreads_wol_announcements',
			'xthreads_wol_forumdisplay',
			'xthreads_wol_newthread',
			'xthreads_wol_attachment',
			'xthreads_wol_newreply',
			'xthreads_wol_showthread',
			//'xthreads_wol_xtattachment',
		);
		$wolhtml = '';
		foreach($wolfields as &$w) {
			$wolhtml .= '<tr><td width="40%" style="border: 0; padding: 1px;"><label for="'.$w.'">'.$lang->$w.':</label></td><td style="border: 0; padding: 1px;">'.$form->generate_text_box($w, $data[$w], array('id' => $w, 'style' => 'margin-top: 0;')).'</td></tr>';
		}
		$form_container->output_row($lang->xthreads_cust_wolstr, $lang->xthreads_cust_wolstr_desc, '<table style="border: 0; margin-left: 2em;" cellspacing="0" cellpadding="0">'.$wolhtml.'</table>');
		
		$form_container->end();
	}
}

function xthreads_admin_forumcommit_myplazaturbo_fix() {
	// pull out the fid into global scope
	control_object($GLOBALS['db'], '
		function insert_query($table, $array) {
			static $done=false;
			if(!$done && $table == "forums") {
				$done = true;
				$r = $GLOBALS["fid"] = parent::insert_query($table, $array);
				return $r;
			}
			return parent::insert_query($table, $array);
		}
	');
}

function xthreads_admin_forumcommit() {
	// hook is after forum is added/edited, so we actually need to go back and update
	global $fid, $db, $cache, $mybb;
	if(!$fid) {
		// bad MyPlaza Turbo! (or any other plugin which does the same thing)
		$fid = intval($mybb->input['fid']);
	}
	$db->update_query('forums', array(
		'xthreads_tplprefix' => $db->escape_string(implode(',', array_map('trim', explode(',', $mybb->input['xthreads_tplprefix'])))),
		'xthreads_grouping' => intval(trim($mybb->input['xthreads_grouping'])),
		'xthreads_firstpostattop' => intval(trim($mybb->input['xthreads_firstpostattop'])),
		'xthreads_allow_blankmsg' => intval(trim($mybb->input['xthreads_allow_blankmsg'])),
		'xthreads_nostatcount' => intval(trim($mybb->input['xthreads_nostatcount'])),
		'xthreads_inlinesearch' => intval(trim($mybb->input['xthreads_inlinesearch'])),
		'xthreads_threadsperpage' => intval(trim($mybb->input['xthreads_threadsperpage'])),
		'xthreads_postsperpage' => intval(trim($mybb->input['xthreads_postsperpage'])),
		'xthreads_force_postlayout' => trim($mybb->input['xthreads_force_postlayout']),
		'xthreads_hideforum' => intval($mybb->input['xthreads_hideforum']),
//		'xthreads_deffilter' => $db->escape_string($deffilter),
		'xthreads_wol_announcements' => $db->escape_string(trim($mybb->input['xthreads_wol_announcements'])),
		'xthreads_wol_forumdisplay' => $db->escape_string(trim($mybb->input['xthreads_wol_forumdisplay'])),
		'xthreads_wol_newthread' => $db->escape_string(trim($mybb->input['xthreads_wol_newthread'])),
		'xthreads_wol_attachment' => $db->escape_string(trim($mybb->input['xthreads_wol_attachment'])),
		'xthreads_wol_newreply' => $db->escape_string(trim($mybb->input['xthreads_wol_newreply'])),
		'xthreads_wol_showthread' => $db->escape_string(trim($mybb->input['xthreads_wol_showthread'])),
		'xthreads_wol_xtattachment' => $db->escape_string(trim($mybb->input['xthreads_wol_xtattachment'])),
	), 'fid='.$fid);
	
	$cache->update_forums();
}

// TODO: special formatting mappings



function &xthreads_admin_getthumbfields() {
	// generate list of fields which accept thumbs
	$fields = $GLOBALS['cache']->read('threadfields');
	$thumbfields = array();
	foreach($fields as $k => &$tf) {
		if(($tf['inputtype'] == XTHREADS_INPUT_FILE || $tf['inputtype'] == XTHREADS_INPUT_FILE_URL) && $tf['fileimage'] && !empty($tf['fileimgthumbs']))
			$thumbfields[$k] = $tf['fileimgthumbs'];
	}
	return $thumbfields;
}

function xthreads_admin_rebuildthumbs() {
	global $mybb, $db;
	if($mybb->request_method == 'post') {
		if(isset($mybb->input['do_rebuildxtathumbs'])) {
			$page = intval($mybb->input['page']);
			if($page < 1)
				$page = 1;
			$perpage = intval($mybb->input['xtathumbs']);
			if(!$perpage) $perpage = 500;
			
			global $lang;
			if(!$lang->xthreads_rebuildxtathumbs_nofields) $lang->load('xthreads');
			
			$thumbfields = xthreads_admin_getthumbfields();
			if(empty($thumbfields)) {
				flash_message($lang->xthreads_rebuildxtathumbs_nofields, 'error');
				admin_redirect('index.php?module=tools'.XTHREADS_ADMIN_PATHSEP.'recount_rebuild');
				return;
			}
			$where = 'field IN ("'.implode('","',array_keys($thumbfields)).'")'; //  AND tid!=0
			$num_xta = $db->fetch_field($db->simple_select('xtattachments','count(*) as n',$where),'n');
			
			@set_time_limit(1800);
			require_once MYBB_ROOT.'inc/xthreads/xt_upload.php';
			require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
			$xtadir = $mybb->settings['uploadspath'].'/xthreads_ul/';
			if($xtadir{0} != '/') $xtadir = '../'.$xtadir; // TODO: perhaps check for absolute Windows paths as well?  but then, who uses Windows on a production server? :>
			$query = $db->simple_select('xtattachments', '*', $where, array('order_by' => 'aid', 'limit' => $perpage, 'limit_start' => ($page-1)*$perpage));
			while($xta = $db->fetch_array($query)) {
				// remove thumbs, then rebuild
				$name = xthreads_get_attach_path($xta);
				// unfortunately, we still need $xtadir
				if($thumbs = @glob(substr($name, 0, -6).'*x*.thumb'))
					foreach($thumbs as &$thumb) {
						@unlink($xtadir.$xta['indir'].basename($thumb));
					}
				
				$thumb = xthreads_build_thumbnail($thumbfields[$xta['field']], $xta['aid'], $name, $xtadir, $xta['indir']);
				// TODO: perhaps check for errors? but then, what to do?
			}
			$db->free_result($query);
			++$page;
			check_proceed($num_xta, $page*$perpage, $page, $perpage, 'xtathumbs', 'do_rebuildxtathumbs', $lang->xthreads_rebuildxtathumbs_done);
		}
	}
	else {
		$GLOBALS['plugins']->add_hook('admin_formcontainer_end', 'xthreads_admin_rebuildthumbs_show');
	}
}
function xthreads_admin_rebuildthumbs_show() {
	global $form_container, $form, $lang;
	
	$thumbfields = xthreads_admin_getthumbfields();
	
	$form_container->output_cell('<a id="rebuild_xtathumbs"></a><label>'.$lang->xthreads_rebuildxtathumbs.'</label><div class="description">'.$lang->xthreads_rebuildxtathumbs_desc.'</div>');
	if(empty($thumbfields)) {
		$form_container->output_cell($lang->xthreads_rebuildxtathumbs_nothumbs, array('colspan' => 2));
	} else {
		$form_container->output_cell($form->generate_text_box('xtathumbs', 500, array('style' => 'width: 150px;')));
		$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuildxtathumbs')));
	}
	$form_container->construct_row();
}


function xthreads_vercheck() {
	$info = @include(MYBB_ROOT.'cache/xthreads.php');
	//if(!is_array($info)) return;
	if(!is_array($info)) $info = array('version' => 0.54); // assumption for earlier versions without this upgrader system
	if($info['version'] < XTHREADS_VERSION) {
		global $admin_session, $lang, $mybb;
		// need to upgrade
		if(!$lang->xthreads_upgrade_done) $lang->load('xthreads');
		if($mybb->input['xthreads_upgrade'] && $mybb->input['my_post_key'] == $mybb->post_code) {
			// perform upgrade
			$result = require(MYBB_ROOT.'inc/xthreads/xt_upgrader.php');
			if($result === true) {
				xthreads_write_xtcachefile();
				$msg = array('message' => $lang->xthreads_upgrade_done, 'type' => 'success');
			} else {
				$msg = array('message' => $lang->xthreads_upgrade_failed, 'type' => 'error');
				if(is_string($result) && $result)
					$msg['message'] .= ' '.$result;
			}
			unset($mybb->input['xthreads_upgrade']);
		} else {
			$link = 'index.php?xthreads_upgrade=1&amp;my_post_key='.$mybb->post_code;
			if($mybb->request_mode != 'post') {
				if($qs = $_SERVER['QUERY_STRING']) {
					$qs = preg_replace('~(^|&)my_post_key=.*?(&|$)~i', '$2', $qs);
					if($qs{0} != '&') $qs = '&'.$qs;
					$link .= htmlspecialchars($qs);
				}
			}
			
			$msg = array('message' => $lang->sprintf($lang->xthreads_do_upgrade, number_format(XTHREADS_VERSION, 2), number_format($info['version'], 2), $link), 'type' => 'alert');
		}
		if($admin_session['data']['flash_message'])
			$admin_session['data']['flash_message']['message'] .= '</div><br /><div class="'.$msg['type'].'">'.$msg['message'];
		else
			$admin_session['data']['flash_message'] =& $msg;
	}
}

