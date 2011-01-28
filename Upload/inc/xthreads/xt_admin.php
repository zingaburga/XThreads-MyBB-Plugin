<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

//define('XTHREADS_THREADFILTER_SQL_STRICT', 1);

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

$plugins->add_hook('admin_config_mod_tools_add_thread_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_edit_thread_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_add_post_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_edit_post_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_add_thread_tool_commit', 'xthreads_admin_modtool_commit');
$plugins->add_hook('admin_config_mod_tools_edit_thread_tool_commit', 'xthreads_admin_modtool_commit');
$plugins->add_hook('admin_config_mod_tools_add_post_tool_commit', 'xthreads_admin_modtool_commit');
$plugins->add_hook('admin_config_mod_tools_edit_post_tool_commit', 'xthreads_admin_modtool_commit');

$plugins->add_hook('admin_tools_recount_rebuild_start', 'xthreads_admin_rebuildthumbs');

$plugins->add_hook('admin_tools_get_admin_log_action', 'xthreads_admin_logs');

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
	$text_type = false;
	switch($type) {
		case 'text': case 'blob':
			$size = 0;
			// fall through
		case 'varchar': case 'varbinary': case 'char': case 'binary':
			$unsigned = false;
			$text_type = true;
	}
	if(!isset($size)) {
		switch($type) {
			case 'tinyint': $size = 3; break;
			case 'smallint': $size = 5; break;
			case 'int': $size = 10; break;
			case 'bigint': $size = 20; break;
			case 'varchar': case 'varbinary': $size = 255; break;
			default: $size = 0;
		}
		if($size && $unsigned) ++$size;
	}
	if($size !== 0)
		$size = '('.$size.')';
	elseif($type == 'varchar' || $type == 'varbinary') // force length for varchar if one not defined
		$size = '(255)';
	else
		$size = '';
	$unsigned = ($unsigned ? ' unsigned':'');
	if($text_type) {
		if(xthreads_db_type() != 'pg' || $type != 'varbinary')
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
			if($type == 'binary') $type = 'bytea';
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
		'datatype' => array(
			'db_type' => 'tinyint',
			'default' => XTHREADS_DATATYPE_TEXT,
			'inputtype' => 'select_box',
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
		/* fwrite($fp, '<?php if(!defined("IN_MYBB")) exit;
return array(
	"version" => '.XTHREADS_VERSION.'
);'); */

		$defines = array();
		foreach(array(
			'XTHREADS_ALLOW_URL_FETCH' => true,
			'XTHREADS_URL_FETCH_DISALLOW_HOSTS' => 'localhost,127.0.0.1',
			'XTHREADS_URL_FETCH_DISALLOW_PORT' => false,
			'XTHREADS_UPLOAD_FLOOD_TIME' => 1800,
			'XTHREADS_UPLOAD_FLOOD_NUMBER' => 50,
			'XTHREADS_UPLOAD_EXPIRE_TIME' => 3*3600,
			'XTHREADS_UPLOAD_LARGEFILE_SIZE' => 10*1048576,
			'XTHREADS_ALLOW_PHP_THREADFIELDS' => 2,
			'XTHREADS_ATTACH_USE_QUERY' => 0,
			
			'COUNT_DOWNLOADS' => 2,
			'CACHE_TIME' => 604800,
			'PROXY_REDIR_HEADER_PREFIX' => '',
		) as $name => $val) {
			if(defined($name))
				$val = constant($name);
			// support legacy query string definition
			elseif($name == 'XTHREADS_ATTACH_USE_QUERY' && defined('ARCHIVE_QUERY_STRINGS') && ARCHIVE_QUERY_STRINGS)
				$val = 1;
			
			if(is_string($val))
				// don't need to escape ' characters as we don't use them here
				$val = '\''.$val.'\'';
			elseif(is_bool($val))
				$val = ($val ? 'true':'false');
			else {
				// nice formatters
				switch($name) {
					// size fields
					case 'XTHREADS_UPLOAD_LARGEFILE_SIZE':
						$suf = '';
						while($val > 1 && !($val % 1024)) {
							$val /= 1024;
							$suf .= '*1024';
						}
						if($val == 1)
							$val = ($suf ? substr($suf, 1) : $val);
						else
							$val .= $suf;
						break;
					
					// time fields
					case 'XTHREADS_UPLOAD_FLOOD_TIME': case 'XTHREADS_UPLOAD_EXPIRE_TIME': case 'CACHE_TIME':
						if(!($val % 3600)) {
							$val /= 3600;
							if(!($val % 24))
								$val = ($val / 24).'*24*3600';
							else
								$val = $val.'*3600';
							break;
						} elseif(!($val % 60)) {
							$val = ($val / 60).'*60';
							break;
						}
						
						// fall through
					default:
						$val = strval($val);
				}
			}
			$defines[$name] = 'define(\''.$name.'\', '.$val.');';
		}
		$defines['XTHREADS_INSTALLED_VERSION'] = 'define(\'XTHREADS_INSTALLED_VERSION\', '.XTHREADS_VERSION.');';
		
		fwrite($fp, <<<ENDSTR
<?php
// XThreads definition file
// This file contains a number of "internal" settings which you can modify if you wish

/**********  XTHREADS ATTACHMENT URL FETCHING  **********/
/**
 * Allows users to upload files through URL fetching
 */
$defines[XTHREADS_ALLOW_URL_FETCH]
/**
 * Hosts which URLs cannot be fetched from, note that this is based on the supplied URL
 *  hosts or IPs are not resolved; separate with commas
 */
$defines[XTHREADS_URL_FETCH_DISALLOW_HOSTS]
/** 
 * Disallow users to specify custom ports in URL, eg http://example.com:1234/ [default=enabled (false)]
 */
$defines[XTHREADS_URL_FETCH_DISALLOW_PORT]

/**
 * Try to stop xtattachment flooding through orphaning (despite MyBB itself being vulnerable to it)
 *  we'll silently remove orphaned xtattachments that are added within a certain timeframe; note, this does not apply to guests, if you allow them to upload xtattachments...
 *  by default, we'll start removing old xtattachments made by a user within the last half hour if there's more than 50 orphaned xtattachments
 */
$defines[XTHREADS_UPLOAD_FLOOD_TIME] // in seconds
$defines[XTHREADS_UPLOAD_FLOOD_NUMBER]
// also, automatically remove xtattachments older than 3 hours when they try to upload something new
$defines[XTHREADS_UPLOAD_EXPIRE_TIME] // in seconds

/**
 * The size a file must be above to be considered a "large file"
 *  large files will have their MD5 calculation deferred to a task
 *  set to 0 to disable deferred MD5 hashing
 */
$defines[XTHREADS_UPLOAD_LARGEFILE_SIZE] // in bytes, default is 10MB



/**********  XTHREADS ATTACH DOWNLOAD  **********/
/**
 * Use query string format in XThreads attachment URLs;
 * This should only be enabled if your host doesn't support the standard URL format
 *  if 0: default URL structure (if Apache webserver running in CGI)
 *  if 1: force use of query string (ex: xthreads_attach.php?file=xx_xxxx_xxxxxxxx/file.zip)
 *  if 2: force use of query string and use non-slash delimeters (ex: xthreads_attach.php?file=xx_xxxx_xxxxxxxx|file.zip)
 */
$defines[XTHREADS_ATTACH_USE_QUERY]


/**
 * The following controls whether you wish to count downloads
 *  if 0: is disabled, and the DB won't be queried at all
 *  if 1: downloads = number of requests made (MyBB style attachment download counting)
 *  if 2 [default]: will count download only when entire file is sent; in the case of segmented download, will only count if last segment is requested (and completed)
 * mode 2 is perhaps the most accurate method of counting downloads under normal circumstances
 */
$defines[COUNT_DOWNLOADS]


/**
 * The following is just the default cache expiry period for downloads, specified in seconds
 * as XThreads changes the URL if a file is modified, you can safely use a very long cache expiry time
 * the default value is 1 week (604800 seconds)
 */
$defines[CACHE_TIME]


/**
 * Redirect proxy response; this only applies if you're using a front-end web server to serve static files (eg nginx -> Apache for serving PHP files)
 * to use this feature, you specify the header, along with the root of the xthreads_ul folder (with trailing slash) as the front-end webserver sees it.  Note that it is up to you to set up the webserver correctly
 * 
 * example for nginx
 *  define('PROXY_REDIR_HEADER_PREFIX', 'X-Accel-Redirect: /forums/uploads/xthreads_ul/');
 * example for lighttpd / mod_xsendfile
 *  define('PROXY_REDIR_HEADER_PREFIX', 'X-Sendfile: /forums/uploads/xthreads_ul/');
 *
 * defaults to empty string, which tunnels the file through PHP
 * note that using this option will cause a COUNT_DOWNLOADS setting of 2, to become 1 (can't count downloads after redirect header sent)
 */
$defines[PROXY_REDIR_HEADER_PREFIX]



/**********  OTHER  **********/

/**
 * Allow PHP in threadfields' display format, unviewable format etc; note that if you change this value after XThreads has been installed, you may need to rebuild your "threadfields" cache
 * 0=disable, 1=enable, 2=enable only if PHP in Templates plugin is activated (default)
 */
$defines[XTHREADS_ALLOW_PHP_THREADFIELDS]





// internal version tracker, used to determine whether an upgrade is required and shown in the AdminCP
// DO NOT MODIFY!
$defines[XTHREADS_INSTALLED_VERSION]

ENDSTR
);
		fclose($fp);
	}
}
function xthreads_buildtfcache() {
	global $db, $cache;
	
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	
	$sanitise_fields_normal = array('VALUE', 'RAWVALUE');
	$sanitise_fields_file = array('DOWNLOADS', 'DOWNLOADS_FRIENDLY', 'FILENAME', 'UPLOADMIME', 'URL', 'FILESIZE', 'FILESIZE_FRIENDLY', 'MD5HASH', 'UPLOAD_TIME', 'UPLOAD_DATE', 'UPDATE_TIME', 'UPDATE_DATE', 'ICON');
	$cd = array();
	$query = $db->simple_select('threadfields', '*', '', array('order_by' => 'disporder', 'order_dir' => 'asc'));
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
					$tf['fieldheight']
				);
				if(!$tf['fileimage'])
					unset($tf['fileimgthumbs']);
				$tf['datatype'] = XTHREADS_DATATYPE_TEXT;
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
		
		if(xthreads_empty($tf['multival']))
			unset($tf['dispitemformat']);
		else
			$tf['datatype'] = XTHREADS_DATATYPE_TEXT;
		
		if($tf['datatype'] != XTHREADS_DATATYPE_TEXT) {
			// disable santizer for a free speed boost
			if(($tf['sanitize'] & XTHREADS_SANITIZE_MASK) != XTHREADS_SANITIZE_PARSER)
				$tf['sanitize'] = XTHREADS_SANITIZE_NONE;
		}
		
		// preformat stuff to save time later
		if($tf['formatmap'])
			$tf['formatmap'] = @unserialize($tf['formatmap']);
		else
			$tf['formatmap'] = null;
		
		if(!xthreads_empty($tf['vallist'])) {
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
			$tf['fileimgthumbs'] = array_unique(explode('|', str_replace(',','|',$tf['fileimgthumbs'])));
		}
		if(!xthreads_empty($tf['filemagic'])) {
			$tf['filemagic'] = array_map('urldecode', array_unique(explode('|', $tf['filemagic'])));
		}
		
		// fix sanitize
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_TEXT:
				//if($tf['sanitize'] == XTHREADS_SANITIZE_HTML_NL)
				//	$tf['sanitize'] = XTHREADS_SANITIZE_HTML;
				break;
			case XTHREADS_INPUT_SELECT:
				$tf['sanitize'] = XTHREADS_SANITIZE_HTML;
				break;
			case XTHREADS_INPUT_CHECKBOX:
			case XTHREADS_INPUT_RADIO:
				$tf['sanitize'] = XTHREADS_SANITIZE_NONE;
				break;
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
			$tf['regex_tokens'] = (
				($tf['unviewableval']  && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['unviewableval'])) ||
				($tf['dispformat']     && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['dispformat'])) ||
				($tf['dispitemformat'] && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['dispitemformat']))
			);
		}
		if($tf['defaultval']) xthreads_sanitize_eval($tf['defaultval']);
		if($tf['unviewableval']) xthreads_sanitize_eval($tf['unviewableval'], $sanitise_fields);
		if($tf['dispformat']) xthreads_sanitize_eval($tf['dispformat'], $sanitise_fields);
		if($tf['dispitemformat']) xthreads_sanitize_eval($tf['dispitemformat'], $sanitise_fields);
		if($tf['blankval']) xthreads_sanitize_eval($tf['blankval']);
		if(!empty($tf['formatmap']) && is_array($tf['formatmap']))
			foreach($tf['formatmap'] as &$fm)
				xthreads_sanitize_eval($fm);
		
		$cd[$tf['field']] = $tf;
	}
	$db->free_result($query);
	$cache->update('threadfields', $cd);
	
	// rebuild the forums cache too - there's a dependency because this can affect the filtering etc allows
	xthreads_buildcache_forums();
}

// build xt_forums cache from forums cache (also reduce size of forums cache)
function xthreads_buildcache_forums() {
	global $cache;
	$forums = $cache->read('forums');
	$xtforums = array();
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	foreach($forums as $fid => $forum) {
		$xtforum = array();
		$xtforum['tplprefix'] = $forum['xthreads_tplprefix'];
		xthreads_sanitize_eval($xtforum['tplprefix']);
		$xtforum['langprefix'] = $forum['xthreads_langprefix'];
		xthreads_sanitize_eval($xtforum['langprefix']);
		
		$xtforum['defaultfilter_tf'] = array();
		$xtforum['defaultfilter_xt'] = array();
		unset($threadfield_cache);
		foreach(explode("\n", str_replace("{\n}", "\r", str_replace("\r", '', $forum['xthreads_defaultfilter']))) as $filter) {
			list($n, $v) = explode('=', str_replace("\r", "\n", $filter), 2);
			if(!isset($v)) continue;
			//$n = urldecode($n); // - this is not necessary, since $n can never contain newlines or = signs
			$isarray = false;
			if($p = strrpos($n, '[')) {
				$n = substr($n, 0, $p);
				$isarray = true;
			}
			unset($filter_array);
			if(substr($n, 0, 5) == '__xt_') {
				$n = substr($n, 5);
				if(in_array($n, array('uid','lastposteruid','icon','prefix')))
					$filter_array =& $xtforum['defaultfilter_xt'];
			} else {
				if(!isset($threadfield_cache))
					$threadfield_cache = xthreads_gettfcache($fid);
				if(isset($threadfield_cache[$n]) && $threadfield_cache[$n]['allowfilter'])
					$filter_array =& $xtforum['defaultfilter_tf'];
			}
			if(isset($filter_array)) {
				xthreads_sanitize_eval($v);
				if($isarray)
					$filter_array[$n][] = $v;
				else
					$filter_array[$n] = $v;
			}
		}
		
		unset($forum['xthreads_tplprefix'], $forum['xthreads_langprefix'], $forum['xthreads_defaultfilter']);
		if(!empty($xtforum)) $xtforums[$fid] = $xtforum;
	}
	
	$cache->update('xt_forums', $xtforums);
	$cache->update('forums', $forums);
}

function xthreads_admin_cachehack() {
	control_object($GLOBALS['cache'], '
		function update_threadfields() {
			xthreads_buildtfcache();
		}
		function update_xt_forums() {
			xthreads_buildcache_forums();
		}
	');
}
function xthreads_admin_menu(&$menu) {
	global $lang;
	$lang->load('xthreads');
	$menu['32'] = array('id' => 'threadfields', 'title' => $lang->custom_threadfields, 'link' => xthreads_admin_url('config', 'threadfields'));
}
function xthreads_admin_action(&$actions) {
	$actions['threadfields'] = array('active' => 'threadfields', 'file' => 'threadfields.php');
}
function xthreads_admin_perms(&$perms) {
	global $lang;
	if(!$lang->can_manage_threadfields) $lang->load('xthreads');
	$perms['threadfields'] = $lang->can_manage_threadfields;
}
function &xthreads_admin_forumedit_get_description($lv) {
	global $lang;
	static $expander_id = 0;
	$langdesc = $lv.'_desc';
	$desc = $lang->$langdesc;
	if(($p = strpos($desc, '<!-- more -->')) !== false) {
		$desc = substr($desc, 0, $p) .
				'<a href="#" onclick="this.style.display=\'none\';$(\'xthreads_desc_expander_'.$expander_id.'\').style.display=\'\';return false;" style="padding-left: 1em; padding-right: 1em;">'.$lang->xthreads_desc_more.'</a><span style="display: none;" id="xthreads_desc_expander_'.$expander_id.'">' .
				substr($desc, $p+13 /*strlen('<!-- more -->')*/) . '</span>';
		
		++$expander_id;
	}
	return $desc;
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
	function xthreads_admin_forumedit_hook_sorter(&$args) {
		global $lang;
		static $done = false;
		if($done || $args['title'] != $lang->default_view_options) return;
		$done = true;
		global $view_options, $default_sort_by, $form, $forum_data;
		if(count($view_options) != 3) return; // back out if things seem a little odd
		// add our custom sortby options here
		if($GLOBALS['mybb']->version_code >= 1500)
			$default_sort_by['prefix'] = $lang->xthreads_sort_ext_prefix;
		$default_sort_by['icon'] = $lang->xthreads_sort_ext_icon;
		$default_sort_by['lastposter'] = $lang->xthreads_sort_ext_lastposter;
		$default_sort_by['numratings'] = $lang->xthreads_sort_ext_numratings;
		$default_sort_by['attachmentcount'] = $lang->xthreads_sort_ext_attachmentcount;
		
		$threadfield_cache = xthreads_gettfcache($forum_data['fid'] ? $forum_data['fid'] : -1);
		if(!empty($threadfield_cache)) {
			//$changed = false;
			foreach($threadfield_cache as &$tf) {
				if($tf['inputtype'] == XTHREADS_INPUT_TEXTAREA) continue;
				if(!$lang->xthreads_sort_threadfield_prefix) $lang->load('xthreads');
				
				//$changed = true;
				$itemname = $lang->xthreads_sort_threadfield_prefix.$tf['title'];
				if($tf['inputtype'] == XTHREADS_INPUT_FILE) {
					foreach(array('filename', 'filesize', 'uploadtime', 'updatetime', 'downloads') as $tfan) {
						$langvar = 'xthreads_sort_'.$tfan;
						$default_sort_by['tfa_'.$tfan.'_'.$tf['field']] = $itemname.' ['.$lang->$langvar.']';
					}
				} else
					$default_sort_by['tf_'.$tf['field']] = $itemname;
			}
		}
		//if(!$changed) return;
		
		// regenerate stuff
		$view_options[1] = $lang->default_sort_by."<br />\n".$form->generate_select_box('defaultsortby', $default_sort_by, $forum_data['defaultsortby'], array('checked' => $forum_data['defaultsortby'], 'id' => 'defaultsortby'));
		$args['content'] = '<div class="forum_settings_bit">'.implode('</div><div class="forum_settings_bit">', $view_options).'</div>';
	}
	$GLOBALS['plugins']->add_hook('admin_formcontainer_output_row', 'xthreads_admin_forumedit_hook');
	$GLOBALS['plugins']->add_hook('admin_formcontainer_output_row', 'xthreads_admin_forumedit_hook_sorter');
	function xthreads_admin_forumedit_run() {
		global $lang, $form, $forum_data, $form_container;
		
		if(!$lang->xthreads_tplprefix) $lang->load('xthreads');
		$form_container = new FormContainer($lang->xthreads_opts);
		
		if(isset($forum_data['xthreads_tplprefix'])) { // editing (or adding with submitted errors)
			$data =& $forum_data;
			/*
			// additional filter enable needs to be split up
			if(!isset($data['xthreads_afe_uid']) && isset($data['xthreads_addfiltenable'])) {
				foreach(explode(',', $data['xthreads_addfiltenable']) as $afe)
					$data['xthreads_afe_'.$afe] = 1;
			}
			*/
		}
		else // adding
			$data = array(
				'xthreads_tplprefix' => '',
				'xthreads_langprefix' => '',
				'xthreads_grouping' => 0,
				'xthreads_firstpostattop' => 0,
				'xthreads_inlinesearch' => 0,
				'xthreads_threadsperpage' => 0,
				'xthreads_postsperpage' => 0,
				'xthreads_force_postlayout' => '',
				'xthreads_hideforum' => 0,
				'xthreads_hidebreadcrumb' => 0,
				'xthreads_defaultfilter' => '',
				//'xthreads_afe_uid' => 0,
				//'xthreads_afe_lastposteruid' => 0,
				//'xthreads_afe_prefix' => 0,
				//'xthreads_afe_icon' => 0,
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
			'tplprefix' => 'text_area_2',
			'langprefix' => 'text_area_2',
			'grouping' => 'text_box',
			'firstpostattop' => 'yes_no_radio',
			'inlinesearch' => 'yes_no_radio',
			'threadsperpage' => 'text_box',
			'postsperpage' => 'text_box',
			'force_postlayout' => array('' => 'none', 'horizontal' => 'horizontal', 'classic' => 'classic'),
			'hideforum' => 'yes_no_radio',
			'hidebreadcrumb' => 'yes_no_radio',
			'allow_blankmsg' => 'yes_no_radio',
			'nostatcount' => 'yes_no_radio',
			'defaultfilter' => 'text_area',
		);
		foreach($inputs as $name => $type) {
			$name = 'xthreads_'.$name;
			$description = xthreads_admin_forumedit_get_description($name);
			//$formfunc = 'generate_'.$type;
			if(is_array($type)) {
				foreach($type as &$t) {
					$ln = $name.'_'.$t;
					$t = $lang->$ln;
				}
				$html = $form->generate_select_box($name, $type, $data[$name], array('id' => $name));
			}
			elseif($type == 'text_box')
				$html = $form->generate_text_box($name, $data[$name], array('id' => $name));
			elseif($type == 'text_area_2')
				// do a 2 row textarea
				$html = $form->generate_text_area($name, $data[$name], array('id' => $name, 'rows' => 2, 'style' => 'font-family: monospace'));
			elseif($type == 'text_area')
				$html = $form->generate_text_area($name, $data[$name], array('id' => $name, 'style' => 'font-family: monospace'));
			elseif($type == 'yes_no_radio')
				$html = $form->generate_yes_no_radio($name, ($data[$name] ? '1':'0'), true);
			//elseif($type == 'check_box')
			//	$html = $form->generate_check_box($name, 1, $);
			$form_container->output_row($lang->$name, $description, $html);
		}
		
		/*
		$afefields = array(
			'uid',
			'lastposteruid',
			'prefix',
			'icon',
		);
		$afehtml = '';
		foreach($afefields as &$field) {
			if(!$GLOBALS['db']->field_exists($field, 'threads')) continue;
			$afe = 'xthreads_afe_'.$field;
			$afehtml .= '<tr><td width="15%" style="border: 0; padding: 1px; vertical-align: top; white-space: nowrap;">'.$form->generate_check_box($afe, 1, $field, array('checked' => $data[$afe])).'</td><td style="border: 0; padding: 1px; vertical-align: top;">&nbsp;('.$lang->$afe.')</td></tr>';
		}
		$form_container->output_row($lang->xthreads_addfiltenable, xthreads_admin_forumedit_get_description('xthreads_addfiltenable'), '<table style="border: 0; margin-left: 2em;" cellspacing="0" cellpadding="0">'.$afehtml.'</table>');
		*/
		
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
		$form_container->output_row($lang->xthreads_cust_wolstr, xthreads_admin_forumedit_get_description('xthreads_cust_wolstr'), '<table style="border: 0; margin-left: 2em;" cellspacing="0" cellpadding="0">'.$wolhtml.'</table>');
		
		$form_container->end();
		
		xthreads_admin_common_ofe('xthreads_defaultfilter');
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
	
	/*
	// handle additional filters
	$afefields = array(
		'uid',
		'lastposteruid',
		'prefix',
		'icon',
	);
	$addfiltenable = '';
	foreach($afefields as &$afe)
		if($db->field_exists($afe, 'threads') && $mybb->input['xthreads_afe_'.$afe]) {
			$addfiltenable .= ($addfiltenable?',':'').$afe;
			if($afe != 'uid') {
				// try to add key - if it already exists, MySQL will fail for us :P
				$db->write_query('ALTER TABLE `'.$db->table_prefix.'threads` ADD KEY `xthreads_'.$afe.'` (`'.$afe.'`)', true);
			}
		} elseif($afe != 'uid') {
			// check if any other forum is using this field
			if(!isset($afe_usage_cache)) {
				$afe_usage_cache = array();
				$query = $db->simple_select('forums', 'DISTINCT xthreads_addfiltenable', 'xthreads_addfiltenable != "" AND fid != '.$fid);
				while($fafelist = $db->fetch_field($query, 'xthreads_addfiltenable')) {
					foreach(explode(',', $fafelist) as $fafe)
						$afe_usage_cache[$fafe] = 1;
				}
				$db->free_result($query);
				unset($fafelist, $fafe);
			}
			if(!$afe_usage_cache[$afe]) {
				// this filter isn't being used anywhere - try to drop the key
				$db->write_query('ALTER TABLE `'.$db->table_prefix.'threads` DROP KEY `xthreads_'.$afe.'`', true);
			}
		}
	*/
	
	$db->update_query('forums', array(
		'xthreads_tplprefix' => $db->escape_string($mybb->input['xthreads_tplprefix']),
		'xthreads_langprefix' => $db->escape_string($mybb->input['xthreads_langprefix']),
		'xthreads_grouping' => intval(trim($mybb->input['xthreads_grouping'])),
		'xthreads_firstpostattop' => intval(trim($mybb->input['xthreads_firstpostattop'])),
		'xthreads_allow_blankmsg' => intval(trim($mybb->input['xthreads_allow_blankmsg'])),
		'xthreads_nostatcount' => intval(trim($mybb->input['xthreads_nostatcount'])),
		'xthreads_inlinesearch' => intval(trim($mybb->input['xthreads_inlinesearch'])),
		'xthreads_threadsperpage' => intval(trim($mybb->input['xthreads_threadsperpage'])),
		'xthreads_postsperpage' => intval(trim($mybb->input['xthreads_postsperpage'])),
		'xthreads_force_postlayout' => trim($mybb->input['xthreads_force_postlayout']),
		'xthreads_hideforum' => intval($mybb->input['xthreads_hideforum']),
		'xthreads_hidebreadcrumb' => intval($mybb->input['xthreads_hidebreadcrumb']),
		'xthreads_defaultfilter' => $db->escape_string($mybb->input['xthreads_defaultfilter']),
		//'xthreads_addfiltenable' => $db->escape_string($addfiltenable),
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
	xthreads_buildcache_forums();
}


function xthreads_admin_modtool() {
	$GLOBALS['plugins']->add_hook('admin_formcontainer_output_row', 'xthreads_admin_modtool_2');
	function xthreads_admin_modtool_2(&$args) {
		if($args['title'] == $GLOBALS['lang']->new_subject.' <em>*</em>') {
			$GLOBALS['plugins']->remove_hook('admin_formcontainer_output_row', 'xthreads_admin_modtool_2');
			$GLOBALS['plugins']->add_hook('admin_formcontainer_end', 'xthreads_admin_modtool_3');
		}
	}
	function xthreads_admin_modtool_3($rtn) {
		$GLOBALS['plugins']->remove_hook('admin_formcontainer_end', 'xthreads_admin_modtool_3');
		
		global $lang;
		$lang->load('xthreads');
		if($GLOBALS['errors']) {
			$val =& $GLOBALS['mybb']->input['edit_threadfields'];
		} else {
			$val =& $GLOBALS['thread_options']['edit_threadfields'];
		}
		$GLOBALS['form_container']->output_row($lang->xthreads_modtool_edit_threadfields, $lang->xthreads_modtool_edit_threadfields_desc, $GLOBALS['form']->generate_text_area('edit_threadfields', $val, array('id' => 'edit_threadfields', 'style' => 'font-family: monospace')));
		$GLOBALS['plugins']->add_hook('admin_formcontainer_output_row', 'xthreads_admin_modtool_4');
	}
	function xthreads_admin_modtool_4(&$args) {
		$GLOBALS['plugins']->remove_hook('admin_formcontainer_output_row', 'xthreads_admin_modtool_4');
		xthreads_admin_common_ofe('edit_threadfields');
	}
}

function xthreads_admin_modtool_commit() {
	global $mybb, $thread_options, $db;
	if(isset($GLOBALS['update_tool'])) {
		// updating
		$tid = intval($mybb->input['tid']);
	} else {
		$tid = $GLOBALS['tid'];
	}
	
	// MyBB bug with adding modtool ignoring prefix??
	
	// TODO: validate this?
	$thread_options['edit_threadfields'] = $mybb->input['edit_threadfields'];
	
	$db->update_query('modtools', array('threadoptions' => $db->escape_string(serialize($thread_options))), 'tid='.$tid);
}


// just because both the ModTools and Default Thread Filter fields use a very similar OFE...
function xthreads_admin_common_ofe($fieldname) {
	global $lang;
	if(!$lang->xthreads_js_confirm_form_submit) $lang->load('xthreads');
?><script type="text/javascript" src="jscripts/xtofedit.js?xtver=<?php echo XTHREADS_VERSION; ?>"></script>
<script type="text/javascript">
<!--
xtOFEditorLang.confirmFormSubmit = "<?php echo $lang->xthreads_js_confirm_form_submit; ?>";
xtOFEditorLang.windowTitle = "<?php echo $lang->xthreads_js_edit_value; ?>";
xtOFEditorLang.saveButton = "<?php echo $lang->xthreads_js_save_changes; ?>";
xtOFEditorLang.closeSaveChanges = "<?php echo $lang->xthreads_js_close_save_changes; ?>";

var ofEditor = new xtOFEditor();
ofEditor.src = $('<?php echo $fieldname; ?>');
ofEditor.loadFunc = function(s) {
	var a = s.replace(/\r/g, "").replace(/\{\n\}/g, "\r").split("\n");
	var data = [];
	for(var i=0; i<a.length; i++) {
		a[i] = a[i].replace(/\r/g, "\n");
		var p = a[i].indexOf("=");
		if(p < 0) continue;
		data.push([ a[i].substring(0, p), a[i].substring(p+1) ]);
	}
	return data;
};
ofEditor.saveFunc = function(a) {
	var ret = "";
	for(var i=0; i<a.length; i++) {
		ret += a[i].join("=").replace(/\n/g, "{\n}") + "\n";
	}
	return ret;
};
ofEditor.fields = [
	{title: "<?php echo $lang->xthreads_js_defaultfilter_field; ?>", width: '45%', elemFunc: ofEditor.textBoxFunc},
	{title: "<?php echo $lang->xthreads_js_defaultfilter_value; ?>", width: '55%', elemFunc: ofEditor.textAreaFunc}
];

ofEditor.copyStyles=true;
ofEditor.init();

//-->
</script><?php
}



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
				admin_redirect(xthreads_admin_url('tools', 'recount_rebuild'));
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

function xthreads_admin_url($cat, $module) {
	return 'index.php?module='.$cat.($GLOBALS['mybb']->version_code >= 1500 ? '-':'/').$module;
}

function xthreads_admin_logs(&$a) {
	global $lang;
	if(!$lang->admin_log_config_threadfields_inline) $lang->load('xthreads');
	if($a['lang_string'] == 'admin_log_config_threadfields_inline') {
		// update non-legacy (>= v1.40) items
		$data =& $a['logitem']['data'];
		if($data[0] || $data[1]) {
			$lang->__xthreads_log_inline = '';
			if($data[0])
				$lang->__xthreads_log_inline = $lang->sprintf($lang->admin_log_config_threadfields_inline_order, $data[0]);
			if($data[1])
				$lang->__xthreads_log_inline .= ($lang->__xthreads_log_inline ? $lang->admin_log_config_threadfields_inline_delim : '') . $lang->sprintf($lang->admin_log_config_threadfields_inline_del, $data[1]);
			
			$data = array();
			$a['lang_string'] = '__xthreads_log_inline';
		}
	}
}

function xthreads_vercheck() {
	if(!defined('XTHREADS_INSTALLED_VERSION')) { // 1.32 or older
		$info = @include(MYBB_ROOT.'cache/xthreads.php');
		if(is_array($info))
			define('XTHREADS_INSTALLED_VERSION', $info['version']);
		else
			define('XTHREADS_INSTALLED_VERSION', 0.54); // fallback
	}
	if(XTHREADS_INSTALLED_VERSION < XTHREADS_VERSION) {
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
			
			$msg = array('message' => $lang->sprintf($lang->xthreads_do_upgrade, xthreads_format_version_number(XTHREADS_VERSION), xthreads_format_version_number(XTHREADS_INSTALLED_VERSION), $link), 'type' => 'alert');
		}
		if($admin_session['data']['flash_message'])
			$admin_session['data']['flash_message']['message'] .= '</div><br /><div class="'.$msg['type'].'">'.$msg['message'];
		else
			$admin_session['data']['flash_message'] =& $msg;
	}
}

function xthreads_format_version_number($v) {
	$ret = number_format($v, 3);
	if(substr($ret, -1) === '0')
		return substr($ret, 0, -1);
	else
		return $ret;
}

/**
 * Convert shorthand size notation to byte value.  Eg '2k' => 2048
 * 
 * @param s Size string
 * @return size in bytes
 */
function xthreads_size_to_bytes($s) {
	$s = strtolower(trim($s));
	if(!$s) return 0;
	$v = floatval($s);
	$last = substr($s, -1);
	if($last == 'b')
		$last = substr($s, -2, 1);
	switch($last) {
		case 'e': $v *= 1024;
		case 'p': $v *= 1024;
		case 't': $v *= 1024;
		case 'g': $v *= 1024;
		case 'm': $v *= 1024;
		case 'k': $v *= 1024;
	}
	return intval(round($v));
}
