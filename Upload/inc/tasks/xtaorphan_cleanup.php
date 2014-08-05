<?php

function task_xtaorphan_cleanup(&$task) {
	global $db, $lang, $plugins, $mybb;
	if(is_object($plugins))
		$plugins->run_hooks('xthreads_task_xtacleanup', $task);
	
	// clean out orphaned xtattachments more than 1 day old
	require_once MYBB_ROOT.'inc/xthreads/xt_modupdhooks.php';
	$count = xthreads_rm_attach_query('tid=0 AND uploadtime<'.(TIME_NOW-86400));
	
	// setting "isdatahandler" to true is destructive!!!
	if(!$lang->task_xtaorphan_run_done) $lang->load('xthreads', true);
	if($count)
		add_task_log($task, $lang->sprintf($lang->task_xtaorphan_run_cleaned, $count));
	else
		add_task_log($task, $lang->task_xtaorphan_run_done);
	
	
	// also perform deferred MD5 hashing
	$query = $db->simple_select('xtattachments', 'aid,indir,attachname,updatetime', 'md5hash IS NULL');
	if(isset($db->db_encoding)) { // hack for MyBB >= 1.6.12 to force it to not screw up our binary field
		$old_db_encoding = $db->db_encoding;
		$db->db_encoding = 'binary';
	}
	while($xta = $db->fetch_array($query)) {
		$file = xthreads_get_attach_path($xta);
		$file_md5 = @md5_file($file, true);
		if(strlen($file_md5) == 32) {
			// perhaps not PHP5
			$file_md5 = pack('H*', $file_md5);
		}
		// we ensure that the attachment hasn't been updated during the hashing process by double-checking the updatetime field
		
		$db->update_query('xtattachments', array('md5hash' => $db->escape_string($file_md5)), 'aid='.$xta['aid'].' AND updatetime='.$xta['updatetime']);
	}
	if(isset($old_db_encoding)) $db->db_encoding = $old_db_encoding;
}
