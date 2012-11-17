<?php
/**
 * All moderation related hooks/functions placed here
 */

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';


function xthreads_purge_draft() {
	global $mybb, $db;
	if(!$mybb->input['deletedraft']) return;
	// unfortunately, we need to grab a list of all the valid tids
	$tidin = '';
	foreach($mybb->input['deletedraft'] as $id => &$val) {
		if($val == 'thread')
			$tidin .= ($tidin===''?'':',') . (int)$id;
	}
	
	if(!$tidin) return;
	$query = $db->simple_select('threads', 'tid', 'tid IN ('.$tidin.') AND visible=-2 AND uid='.$mybb->user['uid']);
	$tidin = '';
	while($tid = $db->fetch_field($query, 'tid'))
		$tidin .= ($tidin==''?'':',') . $tid;
	$db->free_result($query);
	
	if(!$tidin) return;
	$db->delete_query('threadfields_data', 'tid IN ('.$tidin.')');
	require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
	xthreads_rm_attach_query('tid IN ('.$tidin.')');
}

function xthreads_delete_thread($tid) {
	global $db;
	// awesome thing about this is that it will delete threadfields even if the thread was moved to a different forum
	$db->delete_query('threadfields_data', 'tid='.$tid);
	
	xthreads_rm_attach_query('tid='.$tid);
}

function xthreads_copy_thread(&$a) {
	control_object($GLOBALS['db'], '
		function insert_query($table, $array) {
			static $done = false;
			$ret = parent::insert_query($table, $array);
			if(!$done) {
				$done = true;
				xthreads_duplicate_threadfield_data($this->xthreads_copy_thread_tid, $ret);
			}
			return $ret;
		}
	');
	$GLOBALS['db']->xthreads_copy_thread_tid = $a['tid'];
}
/* function xthreads_split_posts(&$a) {
	// impossible to get the new tid!!
	// or maybe there's a way...
} */

function xthreads_duplicate_threadfield_data($tid_old, $tid_new) {
	global $db, $mybb;
	@ignore_user_abort(true); // not really that good, since cancelling elsewhere will break transaction, but, well, copies might be slow, so...
	$tf = $db->fetch_array($db->simple_select('threadfields_data', '*', 'tid='.$tid_old));
	if(empty($tf)) { // no threadfields set for this thread -> nothing to duplicate
		@ignore_user_abort(false);
		return;
	}
	$tf['tid'] = $tid_new;
	
	// copy xtattachments over
	$query = $db->simple_select('xtattachments', '*', 'tid='.$tid_old);
	while($xta = $db->fetch_array($query)) {
		// we have a file we need to duplicate
		$xta['tid'] = $tid_new;
		$oldname = xthreads_get_attach_path($xta);
		$oldpath = dirname($oldname).'/';
		$xta['attachname'] = substr(md5(uniqid(mt_rand(), true).substr($mybb->post_code, 16)), 12, 8).substr($xta['attachname'], 8);
		unset($xta['aid']);
		$tf[$xta['field']] = $xta['aid'] = xthreads_db_insert('xtattachments', $xta);
		
		$newname = xthreads_get_attach_path($xta);
		$newpath = dirname($newname).'/';
		
		$oldfpref = basename(substr($oldname, 0, -6));
		$newfpref = basename(substr($newname, 0, -6));
		if($thumbs = @glob($oldpath.$oldfpref.'*.thumb')) {
			foreach($thumbs as &$thumb) {
				$thumb = basename($thumb);
				xthreads_hardlink_file($oldpath.$thumb, $newpath.str_replace($oldfpref, $newfpref, $thumb));
			}
		}
		xthreads_hardlink_file($oldname, $newname);
	}
	
	xthreads_db_insert('threadfields_data', $tf);
	@ignore_user_abort(false);
}


function xthreads_rm_attach_query($where) {
	global $db;
	$has_attach = $successes = 0;
	$query = $db->simple_select('xtattachments', 'aid,indir,attachname', $where);
	$rmaid = '';
	while($xta = $db->fetch_array($query)) {
		if(xthreads_rm_attach_fs($xta)) {
			if($successes) $rmaid .= ',';
			$rmaid .= $xta['aid'];
			++$successes;
		}
		++$has_attach;
	}
	$db->free_result($query);
	if($has_attach) {
		if($has_attach == $successes)
			$db->delete_query('xtattachments', $where);
		elseif($successes)
			$db->delete_query('xtattachments', 'aid IN ('.$rmaid.')');
	}
	return $successes;
}

// will try to create a hardlink/copy of a file
function xthreads_hardlink_file($src, $dest) {
	if($src == $dest) return false;
	if(@link($src, $dest)) return true;
	if(DIRECTORY_SEPARATOR == '\\' && @ini_get('safe_mode') != 'On') {
		$allow_exec = true;
		// check if exec() is allowed
		if(($func_blacklist = @ini_get('suhosin.executor.func.blacklist')) && strpos(','.$func_blacklist.',', ',exec,') !== false)
			$allow_exec = false;
		if(($func_blacklist = @ini_get('disable_functions')) && strpos(','.$func_blacklist.',', ',exec,') !== false)
			$allow_exec = false;
		
		if($allow_exec) {
			// try mklink (Windows Vista / Server 2008 and later only)
			// assuming mklink refers to the correct executable is a little dangerous perhaps, but it should work
			@unlink($dest); // mklink won't overwrite
			@exec('mklink /H '.escapeshellarg(str_replace('/', '\\', $src)).' '.escapeshellarg(str_replace('/', '\\', $dest)).' >NUL 2>NUL', $null, $ret);
			if($ret==0 && @file_exists($dest)) return true;
		}
	}
	// fail, resort to copy
	return @copy($src, $dest);
}

function xthreads_moderation() {
	// try to hook into custom moderation
	// lovely MyBB provides no custom moderation hook, what gives?
	$modactions = array(
		'openclosethread',
		'stick',
		'removeredirects',
		'deletethread',
		'do_deletethread',
		'deletepoll',
		'do_deletepoll',
		'approvethread',
		'unapprovethread',
		'deleteposts',
		'do_deleteposts',
		'mergeposts',
		'do_mergeposts',
		'move',
		'do_move',
		'threadnotes',
		'do_threadnotes',
		'getip',
		'merge',
		'do_merge',
		'split',
		'do_split',
		'removesubscriptions',
		'multideletethreads',
		'do_multideletethreads',
		'multiopenthreads',
		'multiclosethreads',
		'multiapprovethreads',
		'multiunapprovethreads',
		'multistickthreads',
		'multiunstickthreads',
		'multimovethreads',
		'do_multimovethreads',
		'multideleteposts',
		'do_multideleteposts',
		'multimergeposts',
		'do_multimergeposts',
		'multisplitposts',
		'do_multisplitposts',
		'multiapproveposts',
		'multiunapproveposts',
	);
	if($GLOBALS['mybb']->version_code >= 1500) {
		$modactions[] = 'cancel_delayedmoderation';
		$modactions[] = 'do_delayedmoderation';
		$modactions[] = 'delayedmoderation';
	}
	if(in_array($GLOBALS['mybb']->input['action'], $modactions)) return;
	
	// we are probably now looking at custom moderation - let's get ourselves a hook into the system
	control_object($GLOBALS['db'], '
		function simple_select($table, $fields="*", $conditions="", $options=array()) {
			static $done=false;
			if(!$done && $table == "modtools" && substr($conditions, 0, 4) == "tid=" && empty($options)) {
				$done = true;
				xthreads_moderation_custom();
			}
			return parent::simple_select($table, $fields, $conditions, $options);
		}
	');
}

function xthreads_moderation_custom() {
	//if($tool['type'] != 't') return;
	if(!is_object($GLOBALS['custommod'])) return;
	
	control_object($GLOBALS['custommod'], '
		function execute_thread_moderation($thread_options, $tids) {
			if($thread_options[\'deletethread\'] != 1)
				xthreads_moderation_custom_do($tids, $thread_options[\'edit_threadfields\']);
			return parent::execute_thread_moderation($thread_options, $tids);
		}
	');
	
	// this function is executed before copy thread (yay!)
	function xthreads_moderation_custom_do(&$tids, $editstr) {
		if(!$editstr) return;
		$edits = array();
		
		// caching stuff
		static $threadfields = null;
		if(!isset($threadfields))
			$threadfields = xthreads_gettfcache(); // grab all threadfields
		
		require_once MYBB_ROOT.'inc/xthreads/xt_phptpl_lib.php';
		foreach(explode("\n", str_replace("{\n}", "\r", str_replace("\r",'',$editstr))) as $editline) {
			$editline = trim(str_replace("\r", "\n", $editline));
			list($n, $v) = explode('=', $editline, 2);
			if(!isset($v)) continue;
			
			// don't allow editing of file fields
			if(!isset($threadfields[$n]) || $threadfields[$n]['inputtype'] == XTHREADS_INPUT_FILE) continue;
			// we don't do much validation here as we trust admins, right?
			
			// this is just a prelim check (speed optimisation) - we'll need to check this again after evaluating conditionals
			$upperv = strtoupper($v);
			if(($upperv === '' || $upperv == 'NULL' || $upperv == 'NUL') && $threadfields[$n]['datatype'] != XTHREADS_DATATYPE_TEXT)
				$edits[$n] = null;
			else {
				$edits[$n] = $v;
				xthreads_sanitize_eval($edits[$n], array('VALUE'=>null, 'TID'=>null));
			}
		}
		if(empty($edits)) return;
		$modfields = array_keys($edits);
		
		global $db;
		$query = $db->query('
			SELECT t.tid, tfd.`'.implode('`, tfd.`', $modfields).'`
			FROM '.TABLE_PREFIX.'threads t
			LEFT JOIN '.TABLE_PREFIX.'threadfields_data tfd ON t.tid=tfd.tid
			WHERE t.tid IN ('.implode(',', $tids).')
		');
		//$query = $db->simple_select('threadfields_data', 'tid,`'.implode('`,`', $modfields).'`', 'tid IN ('.implode(',', $tids).')');
		while($thread = $db->fetch_array($query)) {
			$updates = array();
			foreach($edits as $n => $v) {
				if($v !== null) {
					// TODO: allowing conditionals direct access to multivals?
					$v = trim(eval_str($v, array('VALUE' => $thread[$n], 'TID' => $thread['tid'])));
					if($threadfields[$n]['datatype'] != XTHREADS_DATATYPE_TEXT) {
						$upperv = strtoupper($v);
						if($upperv == '' || $upperv == 'NULL' || $upperv == 'NUL')
							$v = null;
						// TODO: intval/floatval here?
					}
				}
				if($v !== $thread[$n]) {
					// we'll do some basic validation for multival fields
					if(!xthreads_empty($threadfields[$n]['multival'])) {
						$d = "\n";
						if($threadfields[$n]['inputtype'] == XTHREADS_INPUT_TEXT)
							$d = ',';
						$v = array_unique(array_map('trim', explode($d, str_replace("\r", '', $v))));
						foreach($v as $key => &$val)
							if(xthreads_empty($val))
								unset($v[$key]);
						$v = implode($d, $v);
					}
					$updates[$n] = $v;
				}
			}
			if(!empty($updates)) {
				xthreads_db_update_replace('threadfields_data', $updates, 'tid', $thread['tid']);
			}
		}
		$db->free_result($query);
	}
}

