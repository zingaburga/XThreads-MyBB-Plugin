<?php

$l['postdata_threadfield_required'] = 'Value for &quot;{1}&quot; is required.';
$l['postdata_threadfield_toolong'] = 'Value for &quot;{1}&quot; may not exceed {2} characters.';
$l['postdata_threadfield_invalidvalue'] = 'Invalid value supplied for &quot;{1}&quot;.';
$l['xthreads_threadfield_attacherror'] = 'Error attaching file for &quot;{1}&quot;; <em>{2}</em>';
$l['xthreads_xtaerr_error_attachsize'] = 'The file you attached is too large. The maximum file size allowed is {1} kilobytes.';
$l['xthreads_xtaerr_admindrop_not_found'] = 'The specified file &quot;{1}&quot; was not found on the server - ensure that the file <em>{2}</em> exists.';
$l['xthreads_xtaerr_admindrop_file_unwritable'] = 'The specified file &quot;{1}&quot; is not writable.  You will probably need to CHMOD the file to something like 0666 for this function to work properly.';
$l['xthreads_xtaerr_admindrop_index_error'] = 'Sorry, you cannot move index.html (or the various capitalised variants).  Please rename the file and try again.';

$l['xthreads_xtfurlerr_nofetcher'] = 'No means to fetch remote URL found';
$l['xthreads_xtfurlerr_invalidurl'] = 'Invalid URL supplied';
$l['xthreads_xtfurlerr_badhost'] = 'Fetching from this host has been disallowed';
$l['xthreads_xtfurlerr_badport'] = 'Specifying custom ports has been disallowed';
$l['xthreads_xtfurlerr_invalidscheme'] = 'Invalid URI scheme specified';
$l['xthreads_xtfurlerr_cantwrite'] = 'Could not open file for writing';
$l['xthreads_xtfurlerr_cantwritesocket'] = 'Could not write to socket';
$l['xthreads_xtfurlerr_urlopenfailed'] = 'Could not connect to specified URL';
$l['xthreads_xtfurlerr_headernotfound'] = 'Could not determine response HTTP headers';
$l['xthreads_xtfurlerr_errcode'] = '{1} error ({2}): {3}';
$l['xthreads_xtfurlerr_badresponse'] = 'Could not fetch specified URL - the server returned <em>{1} {2}</em>';

$l['xthreads_md5hash'] = 'MD5 Hash: {1}';
$l['xthreads_rmattach'] = 'Remove/Replace';
$l['xthreads_replaceattach'] = 'Replace';
$l['xthreads_attachfile'] = 'File';
$l['xthreads_attachurl'] = 'URL';

$l['xthreads_val_blank'] = '(Not set)';
$l['xthreads_no_prefix'] = 'No Prefix';
$l['xthreads_no_icon'] = 'No Icon';

$l['sort_by_prefix'] = 'Sort by: Thread Prefix';
$l['sort_by_icon'] = 'Sort by: Thread Icon';
$l['sort_by_lastposter'] = 'Sort by: Last Post Author';
$l['sort_by_numratings'] = 'Sort by: Number of Ratings';
$l['sort_by_attachmentcount'] = 'Sort by: Number of Attachments';

$l['task_xtaorphan_run_cleaned'] = 'The XThreads Orphaned Attachment Cleanup task successfully ran, and removed {1} orphaned file(s).';
$l['task_xtaorphan_run_done'] = 'The XThreads Orphaned Attachment Cleanup task successfully ran, but nothing was cleaned.';
