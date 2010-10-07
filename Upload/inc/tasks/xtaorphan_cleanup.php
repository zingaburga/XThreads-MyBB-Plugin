<?php

function task_xtaorphan_cleanup(&$task) {
	global $db, $lang;
	// clean out orphaned xtattachments more than 1 day old
	require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
	$count = xthreads_rm_attach_query('tid=0 AND uploadtime<'.(TIME_NOW-86400));
	
	if($count)
		add_task_log($task, $lang->sprintf($lang->task_xtaorphan_run_cleaned, $count));
	else
		add_task_log($task, $lang->task_xtaorphan_run_done);
	
	/*
	$where = 'tid=0 AND uploadtime<'.(TIME_NOW-86400);
	$query = $db->simple_select('xtattachments', '*', $where);
	
	if($count = $db->num_rows($query)) {
		require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
		
		while($xta = $db->fetch_array($query)) {
			xthreads_rm_attach_fs($xta);
		}
		$db->free_result($query);
		$db->delete_query('xtattachments', $where);
		
		add_task_log($task, $lang->sprintf($lang->task_xtaorphan_run_cleaned, $count));
	} else
		add_task_log($task, $lang->task_xtaorphan_run_done);
	*/
}
