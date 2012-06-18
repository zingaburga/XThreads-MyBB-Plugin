<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

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

$plugins->add_hook('admin_forum_management_delete', 'xthreads_admin_forumdel');
$plugins->add_hook('admin_user_users_inline', 'xthreads_admin_userprune');

$plugins->add_hook('admin_config_mod_tools_add_thread_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_edit_thread_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_add_post_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_edit_post_tool', 'xthreads_admin_modtool');
$plugins->add_hook('admin_config_mod_tools_add_thread_tool_commit', 'xthreads_admin_modtool_commit');
$plugins->add_hook('admin_config_mod_tools_edit_thread_tool_commit', 'xthreads_admin_modtool_commit');
$plugins->add_hook('admin_config_mod_tools_add_post_tool_commit', 'xthreads_admin_modtool_commit');
$plugins->add_hook('admin_config_mod_tools_edit_post_tool_commit', 'xthreads_admin_modtool_commit');

$plugins->add_hook('admin_tools_recount_rebuild_start', 'xthreads_admin_rebuildthumbs');

$plugins->add_hook('admin_tools_system_health_start', 'xthreads_admin_fileperms');

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
		'editable_values' => array(
			'db_type' => 'text',
			'default' => '',
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
		'inputformat' => array(
			'db_type' => 'text',
			'default' => '{VALUE}',
		),
		'inputvalidate' => array(
			'db_type' => 'text',
			'default' => '',
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

function xthreads_default_threadfields_formhtml($type) {
	$common_vars = array('KEY', 'NAME_PROP', 'VALUE', 'TABINDEX', 'TABINDEX_PROP', 'REQUIRED');
	switch($type) {
		case XTHREADS_INPUT_TEXTAREA:
			return array(
				'<textarea{NAME_PROP}{MAXLEN_PROP}{HEIGHT_PROP_ROWS}{WIDTH_PROP_COLS}{TABINDEX_PROP}>{VALUE}</textarea>',
				array_merge($common_vars,array('MAXLEN','MAXLEN_PROP','HEIGHT','HEIGHT_PROP_SIZE','HEIGHT_CSS','HEIGHT_PROP_ROWS','WIDTH','WIDTH_PROP_SIZE','WIDTH_CSS','WIDTH_PROP_COLS'))
			);
			// <textarea name="xthreads_{KEY}"<if {MAXLEN} then> maxlength="{MAXLEN}"</if><if {HEIGHT} then> rows="{HEIGHT}"</if><if {WIDTH} then> cols="{WIDTH}"</if><if {TABSTOP} then> tabindex="__xt_{TABINDEX_SHIFT}"</if>>{VALUE}</textarea>
		case XTHREADS_INPUT_SELECT:
			return array(
'<select style="{WIDTH_CSS}"{NAME_PROP}{MULTIPLE_PROP}{HEIGHT_PROP_SIZE}{TABINDEX_PROP}>
	<![ITEM[<option value="{VALUE}"{STYLE}{SELECTED}>{LABEL}</option>]]>
</select>',
				array_merge($common_vars,array('MULTIPLE_PROP','HEIGHT','HEIGHT_PROP_SIZE','HEIGHT_CSS','HEIGHT_PROP_ROWS','WIDTH','WIDTH_PROP_SIZE','WIDTH_CSS','WIDTH_PROP_COLS','STYLE','STYLECSS','SELECTED','CHECKED','LABEL'))
			);
		case XTHREADS_INPUT_CHECKBOX:
			return array(
				'<![ITEM[<label style="display: block;"><input{NAME_PROP} type="checkbox" class="checkbox" value="{VALUE}"{CHECKED}{TABINDEX_PROP} />{LABEL}</label>]]>',
				array_merge($common_vars,array('SELECTED','CHECKED','LABEL'))
			);
		case XTHREADS_INPUT_RADIO:
			return array(
				'<![ITEM[<label style="display: block;"><input{NAME_PROP} type="radio" class="radio" value="{VALUE}"{CHECKED}{TABINDEX_PROP} />{LABEL}</label>]]>',
				array_merge($common_vars,array('SELECTED','CHECKED','LABEL'))
			);
		case XTHREADS_INPUT_FILE:
			return array(
'<table border="0" cellspacing="0" cellpadding="0"><![ITEM[
	<tr class="xta_file">
		<td class="xta_file_link"{ATTACH_MD5_TITLE}><a href="{ATTACH_URL}" target="_blank">{ATTACH_FILENAME}</a> ({ATTACH_SIZE_FRIENDLY})</td>
		<td><label class="xtarm_label"<if {REQUIRED} and !({MULTIPLE}) then> style="display: none;"</if>><input type="checkbox" class="xtarm" name="xtarm_{KEY}<if {MULTIPLE} then>[{ATTACH_ID}]</if>"<if !({MULTIPLE}) then> data="xtarow_{KEY}"</if> value="1"{REMOVE_CHECKED} /><if {REQUIRED} and !({MULTIPLE}) then>{$lang->xthreads_replaceattach}<else>{$lang->xthreads_rmattach}</if></label></td>
	</tr>
]]></table>
<div id="xtarow_{KEY}" class="xta_input">
	<if {URLFETCH} then>
		<if !({MULTIPLE}) then>
			<div class="xtasel" style="display: none; font-size: x-small;"><label style="margin: 0 0.6em;"><input type="radio" class="xtasel_opt" name="xtasel_{KEY}" value="file"{CHECKED_UPLOAD} />{$lang->xthreads_attachfile}</label><label style="margin: 0 0.6em;"><input type="radio" class="xtasel_opt" name="xtasel_{KEY}" value="url"{CHECKED_URL} />{$lang->xthreads_attachurl}</label></div>
		</if>
		<table border="0" cellspacing="0" cellpadding="1"><tr class="xta_input_file_row">
			<td class="xtasel_label" style="vertical-align: top;">{$lang_xthreads_attachfile}: </td>
			<td>
	</if>
		<if {MAXSIZE} then><input type="hidden" name="MAX_FILE_SIZE" value="{MAXSIZE}" /></if>
			<div class="xta_input_file_container"><div class="xta_input_file_wrapper">
				<input type="file" class="fileupload xta_input_file"{NAME_PROP}{WIDTH_PROP_SIZE}{TABINDEX_PROP}{MULTIPLE_PROP} /><input type="button" class="button xta_input_file_clr" value="{$lang->file_clear}" style="display: none;" />
			</div></div>
		<if {MAXSIZE} then><input type="hidden" name="MAX_FILE_SIZE" value="0" /></if>
	<if {URLFETCH} then>
			</td>
		</tr>
		<tr class="xta_input_url_row">
			<td class="xtasel_label" style="vertical-align: top;">{$lang_xthreads_attachurl}: </td>
			<td>
				<if {MULTIPLE} then><textarea name="xtaurl_{KEY}"{WIDTH_PROP_COLS} rows="3" class="xta_input_url">{VALUE_URL}</textarea><else /><input type="url" class="textbox xta_input_url" name="xtaurl_{KEY}"{WIDTH_PROP_SIZE} value="{VALUE_URL}" /></if>
			</td>
		</tr></table>
	</if>
</div>
<if !$tplvars[\'xta_js_added\'] and ({ATTACH_ID} or {URLFETCH} or {MULTIPLE}) then>
	<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/xthreads_attach_input.js"></script>
</if>
<setvar xta_js_added>1</setvar>', array('KEY','NAME_PROP','ATTACH_ID','ATTACH_MD5','ATTACH_MD5_TITLE','ATTACH_URL','ATTACH_FILENAME','ATTACH_FILEEXT','ATTACH_SIZE_FRIENDLY','ATTACH_SIZE','ATTACH_MIME','ATTACH_UPLOAD_TIME','ATTACH_UPLOAD_DATE','ATTACH_UPDATE_TIME','ATTACH_UPDATE_DATE','ATTACH_DOWNLOADS','ATTACH_DOWNLOADS_FRIENDLY','REQUIRED','REMOVE_CHECKED','URLFETCH','CHECKED_UPLOAD','SELECTED_UPLOAD','CHECKED_URL','SELECTED_URL','MAXSIZE','WIDTH','WIDTH_PROP_SIZE','WIDTH_CSS','WIDTH_PROP_COLS','TABINDEX','TABINDEX_PROP','VALUE_URL','MULTIPLE','MULTIPLE_PROP')
			);
		default:
			return array(
				'<input type="text" class="textbox"{NAME_PROP}{MAXLEN_PROP}{WIDTH_PROP_SIZE}{TABINDEX_PROP} value="{VALUE}" />',
				array_merge($common_vars,array('MAXLEN','MAXLEN_PROP','WIDTH','WIDTH_PROP_SIZE','WIDTH_CSS','WIDTH_PROP_COLS'))
			);
	}
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
			'XTHREADS_MODIFY_TEMPLATES' => true,
			
			'XTHREADS_COUNT_DOWNLOADS' => 2,
			'XTHREADS_CACHE_TIME' => 604800,
			'XTHREADS_PROXY_REDIR_HEADER_PREFIX' => '',
			'XTHREADS_EXPIRE_ATTACH_LINK' => 0,
			'XTHREADS_ATTACH_LINK_IPMASK' => 0,
			'XTHREADS_MIME_OVERRIDE' => '',
		) as $name => $val) {
			if(defined($name))
				$val = constant($name);
			// support legacy query string definition
			elseif($name == 'XTHREADS_ATTACH_USE_QUERY' && defined('ARCHIVE_QUERY_STRINGS') && ARCHIVE_QUERY_STRINGS)
				$val = 1;
			// name transition from XThreads 1.45 and older
			elseif(in_array($name, array('XTHREADS_COUNT_DOWNLOADS','XTHREADS_CACHE_TIME','XTHREADS_PROXY_REDIR_HEADER_PREFIX')) && defined(substr($name, 0, 9)))
				$val = constant(substr($name, 0, 9));
			
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
 *  if -1: force no query string (ex: xthreads_attach.php/xx_xxxx_xxxxxxxx/file.zip)
 *  if 0: autodetect (prefer default URL structure if running with mod_php or CGI)
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
$defines[XTHREADS_COUNT_DOWNLOADS]


/**
 * The following is just the default cache expiry period for downloads, specified in seconds
 * as XThreads changes the URL if a file is modified, you can safely use a very long cache expiry time
 * the default value is 1 week (604800 seconds)
 * Set to 0 to explicitly disable client-side caching
 */
$defines[XTHREADS_CACHE_TIME]


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
 * note that using this option will cause a XTHREADS_COUNT_DOWNLOADS setting of 2, to become 1 (can't count downloads after redirect header sent)
 */
$defines[XTHREADS_PROXY_REDIR_HEADER_PREFIX]


/**
 * Expire attachment links after a certain period of time.  This may be useful if you wish to prevent users distributing direct links to attachments.
 * If non-zero, attachment links will change every n seconds (where n is the value below).  Note that actual link expiry will range from n to n*2 seconds.
 * Setting this value too low will cause links to be broken frequently.  You should also take into consideration download pausing, which may break if the download is resumed after the link expires.  A recommended number would be 43200 (12 hours)
 *  - if set to 43200, this means that links will expire 12-24 hours after the user sees it
 * You may also wish to consider modifying the XTHREADS_CACHE_TIME setting above to <= this value.
 */
$defines[XTHREADS_EXPIRE_ATTACH_LINK]


/**
 * Tie download links to IP networks.
 * If non-zero, download links will vary depending on the IP address used to access the link.
 * The IP will be masked by the number of host bits you specify below.  For example, a value of 8 would mean that all 224.0.0.0/8 would get the same download link, which would be different to the link accessible from 225.0.0.0/8
 * A recommended number would be 16.  Do NOT set a value above 32.
 */
$defines[XTHREADS_ATTACH_LINK_IPMASK]


/**
 * Override MIME types (Content-Type header) sent out by xthreads_attach.php
 * Enter in a comma separated list of MIME type to extension mappings
 *  eg 'application/x-httpd-php phtml pht php, application/x-httpd-php3 php3'
 *  maps 3 extensions to the first MIME type, and the .php3 extension to the second MIME type
 */
$defines[XTHREADS_MIME_OVERRIDE]



/**********  OTHER  **********/

/**
 * Allow PHP in threadfields' display format, unviewable format etc; note that if you change this value after XThreads has been installed, you may need to rebuild your "threadfields" cache
 * 0=disable, 1=enable, 2=enable only if PHP in Templates plugin is activated (default)
 */
$defines[XTHREADS_ALLOW_PHP_THREADFIELDS]

/**
 * This switch can be used to disable automatic template editing XThreads performs (will no longer call find_replace_templatesets())
 * This is only really useful if you enabled this before XThreads was installed (edit define in inc/xthreads/xt_install.php)
 */
$defines[XTHREADS_MODIFY_TEMPLATES]




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
	
	$cd = array();
	$evalcache = '';
	$query = $db->simple_select('threadfields', '*', '', array('order_by' => 'disporder', 'order_dir' => 'asc'));
	while($tf = $db->fetch_array($query)) {
		xthreads_buildtfcache_parseitem($tf);
		$evalcache .= '
		function xthreads_evalcache_'.$tf['field'].'($field, $vars=array()) {
			switch($field) {';
		foreach(array('inputformat', 'inputvalidate', 'unviewableval', 'dispformat', 'dispitemformat', 'blankval', 'formhtml', 'formhtml_item') as $field) {
			if(isset($tf[$field])) {
				if($tf[$field] !== '') {
					// slight optimisation - reduces amount of code if will return empty string
					$evalcache .= '
				case \''.$field.'\': return "'.$tf[$field].'";';
				}
				if($field == 'formhtml' || $field == 'formhtml_item' || $field == 'inputvalidate')
					$tf[$field] = ($tf[$field] !== '');
				elseif($field == 'inputformat')
					$tf[$field] = ($tf[$field] !== '{$vars[\'VALUE\']}');
				else
					$tf[$field] = (bool)preg_match('~\$vars[^a-z0-9_]~i', $tf[$field]); // whether to evaluate vars
				// ^ above preg_match is a simple optimisation - not the best, but simple and usually effective
			} else
				$tf[$field] = false;
		}
		$evalcache .= '
			} return \'\';
		}';
		
		$cd[$tf['field']] = $tf;
	}
	$db->free_result($query);
	$cache->update('threadfields', $cd);
	
	$fp = fopen(MYBB_ROOT.'cache/xthreads_evalcache.php', 'w');
	fwrite($fp, '<?php /* this file caches preparsed versions of display formats - please do not modify this file */
'.$evalcache);
	
	// rebuild the forums cache too - there's a dependency because this can affect the filtering etc allows
	xthreads_buildcache_forums($fp);
	fclose($fp);
}
function xthreads_buildtfcache_parseitem(&$tf) {
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	// remove unnecessary fields
	if($tf['editable_gids']) $tf['editable'] = 0;
	if(!$tf['viewable_gids']) unset($tf['unviewableval']);
	switch($tf['inputtype']) {
		case XTHREADS_INPUT_FILE_URL:
			unset($tf['multival'], $tf['dispitemformat']);
		case XTHREADS_INPUT_FILE:
			unset(
				$tf['editable_values'],
				$tf['formatmap'],
				$tf['textmask'],
				$tf['inputformat'],
				$tf['maxlen'],
				$tf['vallist'],
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
		$vallist = $tf['vallist'];
		$tf['vallist'] = array();
		foreach(array_map('trim', explode("\n", str_replace("\r", '', $vallist))) as $vallistitem) {
			if(($p = strpos($vallistitem, '{|}')) !== false)
				$tf['vallist'][substr($vallistitem, 0, $p)] = substr($vallistitem, $p+3);
			else
				$tf['vallist'][$vallistitem] = $vallistitem;
		}
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
			$tf['ignoreblankfilter'] = !isset($tf['vallist']['']);
		}
	}
	
	if(!xthreads_empty($tf['editable_values'])) {
		if($tf['editable'] == XTHREADS_EDITABLE_NONE)
			unset($tf['editable_values']);
		else
			$tf['editable_values'] = @unserialize($tf['editable_values']);
	}
	
	// sanitise eval'd stuff
	if($tf['inputtype'] == XTHREADS_INPUT_FILE) {
		$sanitise_fields = array('DOWNLOADS', 'DOWNLOADS_FRIENDLY', 'FILENAME', 'UPLOADMIME', 'URL', 'FILESIZE', 'FILESIZE_FRIENDLY', 'MD5HASH', 'UPLOAD_TIME', 'UPLOAD_DATE', 'UPDATE_TIME', 'UPDATE_DATE', 'ICON');
		$validate_fields = array('FILENAME', 'FILESIZE');
	}
	else {
		$sanitise_fields = array('VALUE', 'RAWVALUE');
		$tf['regex_tokens'] = (
			($tf['unviewableval']  && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['unviewableval'])) ||
			($tf['dispformat']     && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['dispformat'])) ||
			($tf['dispitemformat'] && preg_match('~\{(?:RAW)?VALUE\$\d+\}~', $tf['dispitemformat']))
		);
		$validate_fields = array('VALUE');
	}
	if($tf['defaultval']) xthreads_sanitize_eval($tf['defaultval']);
	if(!empty($tf['formatmap']) && is_array($tf['formatmap']))
		foreach($tf['formatmap'] as &$fm)
			xthreads_sanitize_eval($fm);
	
	foreach(array('inputformat', 'inputvalidate', 'unviewableval', 'dispformat', 'dispitemformat', 'blankval') as $field) {
		if(isset($tf[$field])) {
			if($field == 'blankval' || $field == 'defaultval')
				xthreads_sanitize_eval($tf[$field]);
			elseif($field == 'inputformat')
				xthreads_sanitize_eval($tf[$field], array('VALUE'));
			elseif($field == 'inputvalidate')
				xthreads_sanitize_eval($tf[$field], $validate_fields);
			elseif($tf['inputtype'] == XTHREADS_INPUT_FILE && !xthreads_empty($tf['multival']) && ($field == 'unviewableval' || $field == 'dispformat'))
				// special case for multi file inputs
				xthreads_sanitize_eval($tf[$field], array('VALUE'));
			else
				xthreads_sanitize_eval($tf[$field], $sanitise_fields);
		}
	}
	$formhtml = xthreads_default_threadfields_formhtml($tf['inputtype']);
	if($tf['formhtml'] !== '') {
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_SELECT:
			case XTHREADS_INPUT_CHECKBOX:
			case XTHREADS_INPUT_RADIO:
			case XTHREADS_INPUT_FILE:
				// item block extraction
				$tf['formhtml_item'] = '';
				$GLOBALS['__xt_formhtml_item'] =& $tf['formhtml_item'];
				$GLOBALS['__xt_formhtml_sanitise_fields'] =& $formhtml[1];
				$tf['formhtml'] = preg_replace_callback('~\<\!\[ITEM\[(.*?)\]\]\>~is','xthreads_buildcache_parseitem_formhtml_pr', $tf['formhtml'], 1);
				unset($GLOBALS['__xt_formhtml_item'], $GLOBALS['__xt_formhtml_sanitise_fields']);
				$formhtml[1][] = 'ITEMS';
		}
		xthreads_sanitize_eval($tf['formhtml'], $formhtml[1]);
	}
}
function xthreads_buildcache_parseitem_formhtml_pr($s) {
	if($GLOBALS['__xt_formhtml_item']) return ''; // multiple instance of replacement - BAD! (shouldn't happen because of limit specified in preg_replace)
	xthreads_sanitize_eval($s[1], $GLOBALS['__xt_formhtml_sanitise_fields']);
	$GLOBALS['__xt_formhtml_item'] = $s[1];
	return '{ITEMS}';
}

// build xt_forums cache from forums cache (also reduce size of forums cache)
// actually, it now just writes the evalcache stuff for forums
function xthreads_buildcache_forums($fp) {
	global $cache;
	$forums = $cache->read('forums');
	$xtforums = array();
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	fwrite($fp, '
		function xthreads_evalcacheForums($fid) {
			switch($fid) {');
	foreach($forums as $fid => $forum) {
		$tplprefix = $forum['xthreads_tplprefix'];
		xthreads_sanitize_eval($tplprefix);
		$langprefix = $forum['xthreads_langprefix'];
		xthreads_sanitize_eval($langprefix);
		
		$settingoverrides = '';
		foreach(explode("\n", str_replace("{\n}", "\r", str_replace("\r", '', $forum['xthreads_settingoverrides']))) as $override) {
			list($n, $v) = explode('=', str_replace("\r", "\n", $override), 2);
			if(!isset($v)) continue;
			$n = strtr($n, array('\\' => '', '\'' => ''));
			xthreads_sanitize_eval($v);
			$settingoverrides .= '\''.$n.'\' => "'.$v.'", ';
		}
		
		if(!xthreads_empty($tplprefix) || !xthreads_empty($langprefix) || $settingoverrides !== '') // slight optimisation: only write if there's something interesting
			fwrite($fp, '
				case '.$fid.': return array(
					\'tplprefix\' => '.(xthreads_empty($tplprefix) ? '\'\'' : 'array_map(\'trim\', explode(\',\', "'.$tplprefix.'"))').',
					\'langprefix\' => '.(xthreads_empty($langprefix) ? '\'\'' : 'array_map(\'trim\', explode(\',\', "'.$langprefix.'"))').',
					\'settingoverrides\' => '.($settingoverrides==='' ? '\'\'' : 'array('.$settingoverrides.')').',
				);');
		$xtforum = array(
			'defaultfilter_tf' => array(),
			'defaultfilter_xt' => array(),
		);
		
		foreach(explode("\n", str_replace("{\n}", "\r", str_replace("\r", '', $forum['xthreads_defaultfilter']))) as $filter) {
			list($n, $v) = explode('=', str_replace("\r", "\n", $filter), 2);
			if(!isset($v)) continue;
			$n = strtr($n, array('\\' => '', '\'' => ''));
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
		
		unset($forum['xthreads_tplprefix'], $forum['xthreads_langprefix'], $forum['xthreads_defaultfilter'], $forum['xthreads_settingoverrides']);
		if(!empty($xtforum)) $xtforums[$fid] = $xtforum;
	}
	fwrite($fp, '
			} return array(\'tplprefix\' => \'\', \'langprefix\' => \'\', \'settingoverrides\' => \'\');
		}
		
		function xthreads_evalcacheForumFilters($fid) {
			switch($fid) {');
		
	{ // dummy brace
		foreach($xtforums as $fid => &$xtforum) {
			// check to see if everything is empty
			$allempty = true;
			foreach($xtforum as $k => &$filter)
				if(!empty($filter)) {
					$allempty = false;
					break;
				}
			if($allempty) continue; // don't write anything if there's nothing interesting to write about
			fwrite($fp, '
				case '.$fid.': return array(');
			foreach($xtforum as $k => &$filter) {
				fwrite($fp, '
					\''.$k.'\' => array(');
				foreach($filter as $n => &$v) {
					fwrite($fp, '\''.$n.'\' => ');
					if(is_array($v))
						fwrite($fp, 'array("'.implode('","', $v).'"),');
					else
						fwrite($fp, '"'.$v.'",');
				}
				fwrite($fp, '
					),');
			}
			fwrite($fp, '
				);');
		}
	}
	
	fwrite($fp, '
			} return array(\'defaultfilter_tf\' => array(), \'defaultfilter_xt\' => array());
		}');
	$cache->update('forums', $forums);
}

function xthreads_check_evalstr($s) {
	return (bool)@create_function('', 'return "'.$s.'";');
}

// checks whether the conditional supported text has any syntax errors
function xthreads_check_condstr($s) {
	require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
	xthreads_sanitize_eval($s);
	return xthreads_check_evalstr($s);
}

function xthreads_catch_errorhandler() {
	// we'll now overwrite the error handler since MyBB's handler seems to interfere with the following
	if(!function_exists('_xthreads_catch_php_error')) { //paranoia
		function _xthreads_catch_php_error($errno, $errstr) {
			$GLOBALS['_previous_error'] = array($errno, $errstr);
		}
	}
	unset($GLOBALS['_previous_error']);
	set_error_handler('_xthreads_catch_php_error');
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
	global $mybb;
	if($mybb->request_method == 'post') {
		global $errors, $lang;
		$lang->load('xthreads');
		// check for conditional syntax errors
		if($mybb->input['xthreads_tplprefix'] && !xthreads_check_condstr($mybb->input['xthreads_tplprefix']))
			$errors[] = $lang->sprintf($lang->error_bad_conditional, $lang->xthreads_tplprefix);
		if($mybb->input['xthreads_langprefix'] && !xthreads_check_condstr($mybb->input['xthreads_langprefix']))
			$errors[] = $lang->sprintf($lang->error_bad_conditional, $lang->xthreads_langprefix);
		// the default forum filter will take some more work
		if($mybb->input['xthreads_defaultfilter']) {
			foreach(explode("\n", str_replace("{\n}", "\r", str_replace("\r", '', $mybb->input['xthreads_defaultfilter']))) as $filter) {
				list($n, $v) = explode('=', str_replace("\r", "\n", $filter), 2);
				if($v && !xthreads_check_condstr($v)) {
					$errors[] = $lang->sprintf($lang->error_bad_conditional, $lang->xthreads_defaultfilter);
					break;
				}
			}
		}
		// do same for setting overrides
		if($mybb->input['xthreads_settingoverrides']) {
			foreach(explode("\n", str_replace("{\n}", "\r", str_replace("\r", '', $mybb->input['xthreads_settingoverrides']))) as $override) {
				list($n, $v) = explode('=', str_replace("\r", "\n", $override), 2);
				if($v && !xthreads_check_condstr($v)) {
					$errors[] = $lang->sprintf($lang->error_bad_conditional, $lang->xthreads_settingoverrides);
					break;
				}
			}
		}
	}
	
	function xthreads_admin_forumedit_hook(&$args) {
		static $done = false;
		if($done || $args['title'] != $GLOBALS['lang']->misc_options) return;
		//$GLOBALS['plugins']->add_hook('admin_formcontainer_end', 'xthreads_admin_forumedit_hook2');
		$done = true;
		$fixcode='';
		if($GLOBALS['mybb']->version_code >= 1500) {
			// unfortunately, the above effectively ditches the added Misc row
			$GLOBALS['xt_fc_args'] = $args;
			$fixcode = '
				// need to disable hooks temporarily to prevent other plugins running twice
				$hooks =& $GLOBALS[\'plugins\']->hooks[\'admin_formcontainer_output_row\'];
				$hooks_copy = $hooks;
				$hooks = array();
				call_user_func_array(array($this, "output_row"), $GLOBALS[\'xt_fc_args\']);
				$hooks = $hooks_copy;
			';
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
				if($tf['inputtype'] == XTHREADS_INPUT_TEXTAREA || !xthreads_empty($tf['multival'])) continue;
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
				'xthreads_fdcolspan_offset' => 0,
				'xthreads_settingoverrides' => '',
				'xthreads_postsperpage' => 0,
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
			);
		
		
		$inputs = array(
			'tplprefix' => 'text_area_2',
			'langprefix' => 'text_area_2',
			'grouping' => 'text_box',
			'firstpostattop' => 'yes_no_radio',
			'inlinesearch' => 'yes_no_radio',
			'fdcolspan_offset' => 'text_box',
			'settingoverrides' => 'text_area',
			'postsperpage' => 'text_box',
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
			$form_container->output_row($lang->$name, $description, $html, $name);
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
			array('xthreads_wol_announcements', 'xthreads_wol_attachment'),
			array('xthreads_wol_forumdisplay', 'xthreads_wol_newthread'),
			array('xthreads_wol_showthread', 'xthreads_wol_newreply'),
		);
		$wolhtml = '';
		foreach($wolfields as &$r) {
			$wolhtml .= '<tr>';
			foreach($r as &$w) {
				$wolhtml .= '<td width="15%" style="border: 0; padding: 1px 5px 1px 15px;"><label for="'.$w.'" style="white-space: nowrap;">'.$lang->$w.':</label></td><td style="border: 0; padding: 1px;">'.$form->generate_text_box($w, $data[$w], array('id' => $w, 'style' => 'margin-top: 0; width: 250px;')).'</td>';
			}
			$wolhtml .= '</tr>';
		}
		$form_container->output_row($lang->xthreads_cust_wolstr, xthreads_admin_forumedit_get_description('xthreads_cust_wolstr'), '<table style="border: 0;" cellspacing="0" cellpadding="0">'.$wolhtml.'</table>', '', array(
			// hack to change style
			'id' => 'xthreads_wol" style="margin: 10px 15px 10px 0px'
		));
		
		$form_container->end();
		
		xthreads_admin_common_ofe('xthreads_defaultfilter');
?>
<script type="text/javascript">
<!--
var ofEditorSO = new xtOFEditor();
ofEditorSO.src = $('xthreads_settingoverrides');
ofEditorSO.loadFunc = ofEditor.loadFunc;
ofEditorSO.saveFunc = ofEditor.saveFunc;
ofEditorSO.fields = [
	{title: "<?php echo $lang->xthreads_js_settingoverrides_setting; ?>", width: '45%', elemFunc: function(c) {
		var o = appendNewChild(c, "select");
		o.size = 1;
		o.style.width = '100%';
		o.innerHTML = '<option value=""></option><?php
			global $db;
			// cache settings
			$qorder = array('order_by' => 'disporder', 'order_dir' => 'asc');
			$query = $db->simple_select('settings','name,title,gid', '', $qorder);
			$setting_cache = array();
			while($stng = $db->fetch_array($query)) {
				$setting_cache[$stng['gid']][$stng['name']] = $stng['title'];
			}
			$db->free_result($query);
			$query = $db->simple_select('settinggroups', 'gid,name,title', '', $qorder);
			while($settinggroup = $db->fetch_array($query)) {
				$stngs =& $setting_cache[$settinggroup['gid']];
				if(!empty($stngs)) {
					$lang_group = 'setting_group_'.$settinggroup['name'];
					if($lang->$lang_group)
						$settinggroup['title'] = $lang->$lang_group;
					echo '<optgroup label="'.strtr(htmlspecialchars_uni($settinggroup['title']), array('\\'=>'\\\\','\''=>'\\\'')).'">';
					foreach($stngs as $name => &$title) {
						$lang_setting = 'setting_'.$name;
						if($lang->$lang_setting)
							$title = $lang->$lang_setting;
						echo '<option value="'.htmlspecialchars_uni($name).'">'.strtr(htmlspecialchars_uni($title), array('\\'=>'\\\\','\''=>'\\\'')).'</option>';
					}
					echo '</optgroup>';
				}
			}
			$db->free_result($query);
			unset($setting_cache);
		?>';
		return o;
	}},
	{title: "<?php echo $lang->xthreads_js_settingoverrides_value; ?>", width: '55%', elemFunc: ofEditorSO.textAreaFunc}
];

ofEditorSO.copyStyles=true;
ofEditorSO.init();
//-->
</script>
<?php
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
		$fid = (int)$mybb->input['fid'];
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
		'xthreads_grouping' => (int)trim($mybb->input['xthreads_grouping']),
		'xthreads_firstpostattop' => (int)trim($mybb->input['xthreads_firstpostattop']),
		'xthreads_allow_blankmsg' => (int)trim($mybb->input['xthreads_allow_blankmsg']),
		'xthreads_nostatcount' => (int)trim($mybb->input['xthreads_nostatcount']),
		'xthreads_inlinesearch' => (int)trim($mybb->input['xthreads_inlinesearch']),
		'xthreads_fdcolspan_offset' => (int)trim($mybb->input['xthreads_fdcolspan_offset']),
		'xthreads_settingoverrides' => $db->escape_string($mybb->input['xthreads_settingoverrides']),
		'xthreads_postsperpage' => (int)trim($mybb->input['xthreads_postsperpage']),
		'xthreads_hideforum' => (int)$mybb->input['xthreads_hideforum'],
		'xthreads_hidebreadcrumb' => (int)$mybb->input['xthreads_hidebreadcrumb'],
		'xthreads_defaultfilter' => $db->escape_string($mybb->input['xthreads_defaultfilter']),
		//'xthreads_addfiltenable' => $db->escape_string($addfiltenable),
//		'xthreads_deffilter' => $db->escape_string($deffilter),
		'xthreads_wol_announcements' => $db->escape_string(trim($mybb->input['xthreads_wol_announcements'])),
		'xthreads_wol_forumdisplay' => $db->escape_string(trim($mybb->input['xthreads_wol_forumdisplay'])),
		'xthreads_wol_newthread' => $db->escape_string(trim($mybb->input['xthreads_wol_newthread'])),
		'xthreads_wol_attachment' => $db->escape_string(trim($mybb->input['xthreads_wol_attachment'])),
		'xthreads_wol_newreply' => $db->escape_string(trim($mybb->input['xthreads_wol_newreply'])),
		'xthreads_wol_showthread' => $db->escape_string(trim($mybb->input['xthreads_wol_showthread'])),
	), 'fid='.$fid);
	
	$cache->update_forums();
	xthreads_buildtfcache();
}

function xthreads_admin_forumdel() {
	control_object($GLOBALS['db'], '
		function delete_query($table, $where="", $limit="") {
			static $done=false;
			if(!$done && $table == "threads" && substr($where, 0, 4) == "fid=") {
				$done = true;
				xthreads_admin_forumdel_do($where);
			}
			return parent::delete_query($table, $where, $limit);
		}
	');
}
function xthreads_admin_forumdel_do($where) {
	require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
	global $db;
	//$query = $db->simple_select('threads', 'tid', $where);
	$query = $db->query('
		SELECT t.tid AS tid FROM '.TABLE_PREFIX.'threads t INNER JOIN '.TABLE_PREFIX.'threadfields_data d ON t.tid=d.tid
		WHERE '.$where);
	
	do {
		$count = 0;
		$continue = false;
		$tids = '';
		while($tid = $db->fetch_field($query, 'tid')) {
			$tids .= ($count?',':'') . $tid;
			// stagger updates to 1000 thread chunks for larger forums
			// TODO: since queries are buffered, should we actually put the limit in the select query?
			if(++$count >= 1000) {
				$continue = true;
				break;
			}
		}
		if($tids) {
			$twhere = 'tid IN ('.$tids.')';
			xthreads_rm_attach_query($twhere);
			$db->delete_query('threadfields_data', $twhere);
		}
	} while($continue);
	$db->free_result($query);
}


function xthreads_admin_userprune() {
	global $mybb;
	if(empty($mybb->cookies['inlinemod_useracp']) || $mybb->input['inline_action'] != 'multiprune' || $mybb->input['processed'] != 1) return;
	
	// no plugin hooks?  attach to DB
	control_object($GLOBALS['db'], '
		function delete_query($table, $where="", $limit="") {
			static $done=false;
			if(!$done && $table == "threads" && substr($where,0,4) == "tid=") {
				$done = true;
				xthreads_admin_userprune_do();
			}
			return parent::delete_query($table, $where, $limit);
		}
	');
}
function xthreads_admin_userprune_do() {
	$tids = implode(',', $GLOBALS['prune_array']['to_delete']);
	if(!$tids) return;
	$qin = 'tid IN ('.$tids.')';
	$GLOBALS['db']->delete_query('threadfields_data', $qin);
	require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
	xthreads_rm_attach_query($qin);
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
		$tid = (int)$mybb->input['tid'];
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
		if(a[i][0]) ret += a[i].join("=").replace(/\n/g, "{\n}") + "\n";
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
			$page = (int)$mybb->input['page'];
			if($page < 1)
				$page = 1;
			$perpage = (int)$mybb->input['xtathumbs'];
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

function xthreads_admin_fileperms() {
	global $lang;
	// MyBB only appends '.', but '../' is more accurate IMO
	$path = $GLOBALS['mybb']->settings['uploadspath'].'/xthreads_ul';
	if(is_writable('../'.$path))
		$message_xtupload = '<span style="color: green;">'.$lang->writable.'</span>';
	else {
		$message_xtupload = '<strong><span style="color: #C00">'.$lang->not_writable.'</span></strong><br />'.$lang->please_chmod_777;
		++$GLOBALS['errors'];
	}
	$lang->load('xthreads');
	// would be nicer to grab the $table object, but doesn't seem easily possible, and these are really simple tables anyway, so unlikely to be much of an issue
	// so just do a simple language hack
	// alt_row won't work properly though :|
	$lang->language_files = $lang->xthreads_uploads_dir.'</strong></td><td class="alt_col">'.$path.'</td><td class="last">'.$message_xtupload.'</td></tr><tr><td class="first"><strong>'.$lang->language_files;
}

function xthreads_vercheck() {
	global $admin_session, $lang, $mybb;
	
	$upgrade_link = 'index.php?xthreads_upgrade=1&amp;my_post_key='.$mybb->post_code;
	if($mybb->request_mode != 'post') {
		if($qs = $_SERVER['QUERY_STRING']) {
			$qs = preg_replace('~(^|&)my_post_key=.*?(&|$)~i', '$2', $qs);
			if($qs{0} != '&') $qs = '&'.$qs;
			$upgrade_link .= htmlspecialchars($qs);
		}
	}
	
	// whilst we're here, also check if evalcache file exists
	if(!file_exists(MYBB_ROOT.'cache/xthreads_evalcache.php'))
		xthreads_buildtfcache();
	
	
	if(!defined('XTHREADS_INSTALLED_VERSION')) { // 1.32 or older
		$info = @include(MYBB_ROOT.'cache/xthreads.php');
		if(is_array($info))
			define('XTHREADS_INSTALLED_VERSION', $info['version']);
		else {
			// can't retrieve info!
			define('XTHREADS_INSTALLED_VERSION', XTHREADS_VERSION); // fallback
			
			// try to rewrite file if possible
			if(!$lang->xthreads_cachefile_rewritten) $lang->load('xthreads');
			if($mybb->input['xthreads_upgrade'] && $mybb->input['my_post_key'] == $mybb->post_code) {
				xthreads_write_xtcachefile();
				$msg = array('message' => $lang->sprintf($lang->xthreads_cachefile_rewritten, XTHREADS_INSTALLED_VERSION), 'type' => 'success');
				unset($mybb->input['xthreads_upgrade']);
			} else {
				$msg = array('message' => $lang->sprintf($lang->xthreads_cachefile_missing, xthreads_format_version_number(XTHREADS_VERSION), $upgrade_link), 'type' => 'alert');
			}
		}
	}
	if(XTHREADS_INSTALLED_VERSION < XTHREADS_VERSION) {
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
			$msg = array('message' => $lang->sprintf($lang->xthreads_do_upgrade, xthreads_format_version_number(XTHREADS_VERSION), xthreads_format_version_number(XTHREADS_INSTALLED_VERSION), $upgrade_link), 'type' => 'alert');
		}
	}
	if($msg) {
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
	$v = (float)$s;
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
	return (int)round($v);
}
