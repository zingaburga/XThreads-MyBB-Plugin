<?php

$l['xthreads_name'] = 'XThreads';
$l['xthreads_desc'] = 'eXtend your Threads with extra fields.';

$l['xthreads_rebuildxtathumbs'] = 'Rebuild XThreads Attachment Thumbnails';
$l['xthreads_rebuildxtathumbs_desc'] = 'This will rebuild thumbnails for images uploaded using the XThreads attachment system (custom thread fields with image uploads).';
$l['xthreads_rebuildxtathumbs_done'] = 'Successfully rebuilt all XThreads attachment thumbnails.';
$l['xthreads_rebuildxtathumbs_nofields'] = 'There are no thread fields which generate thumbnails.';
$l['xthreads_rebuildxtathumbs_nothumbs'] = 'There are no thumbnails to rebuild.';

$l['custom_threadfields'] = 'Custom Thread Fields';
$l['can_manage_threadfields'] = 'Can manage custom thread fields';
$l['custom_threadfields_desc'] = 'You can add/edit/remove custom thread fields here.';
$l['threadfields_name'] = 'Key';
$l['threadfields_name_desc'] = 'The key through which this field is accessed through.  Should be kept to alphanumeric characters, underscores (_) and hypens (-) only.  Note that only inputs for this field on newthread/editpost are automatically added for you; forumdisplay/showthread etc templates aren\'t affected, so you will need to make changes to the relevant templates for this to be useful.  Use <code>{$GLOBALS[\'threadfields\'][\'<em style="color: #00A000;">key</em>\']}</code> in templates to reference this field (this is slightly different for file inputs - more info given below if you choose to make a file input).';
$l['threadfields_file_name_info'] = 'Variables are referenced with <code>{$GLOBALS[\'threadfields\'][\'<em style="color: #00A000;">key</em>\'][\'<em style="color: #0000A0;">item</em>\']}</code>, where <em style="color: #0000A0;">item</em> can be one of the following:
<ul style="margin-top: 0.2em;">
	<li><em>downloads</em> - number of times the file has been downloaded</li>
	<li><em>downloads_friendly</em> - as above, but number is formatted (eg 1,234 vs 1234)</li>
	<li><em>filename</em> - original name of the uploaded file</li>
	<li><em>uploadmime</em> - MIME type sent by uploader\'s browser</li>
	<li><em>url</em> - URL of the file</li>
	<li><em>filesize</em> - size of file, in bytes</li>
	<li><em>filesize_friendly</em> - as above, but formatted (eg 1.5MB vs 1572864)</li>
	<li><em>md5hash</em> - MD5 hash of file</li>
	<li><em>upload_time</em> - time when file was initially uploaded</li>
	<li><em>upload_date</em> - date when file was initially uploaded</li>
	<li><em>update_time</em> - time when file was last updated (will be upload_time if never updated)</li>
	<li><em>update_date</em> - date when file was last updated (will be date_time if never updated)</li>
	<li><em>icon</em> - MyBB\'s attachment file type icon</li>
	<li><em>value</em> - if no file is uploaded, will be Blank Value (see below), otherwise, will be Display Format</li>
	<li><em>dims</em> - an array containing width/height of uploaded image if the option to require image uploads is chosen.  For example <code>{$GLOBALS[\'threadfields\'][\'myimage\'][\'dims\'][\'w\']}</code> would get the width of the uploaded image.</li>
	<li><em>thumbs</em> - an array containing width/height of thumbnails (if used).  For example <code>{$GLOBALS[\'threadfields\'][\'myimage\'][\'thumbs\'][\'320x240\'][\'w\']}</code> would get the real image width of the 320x240 thumbnail.</li>
</ul>';
$l['threadfields_title'] = 'Title';
$l['threadfields_title_desc'] = 'Name of this custom thread field.';
$l['threadfields_desc'] = 'Description';
$l['threadfields_desc_desc'] = 'A short description of this field which is placed under the name in the input field for newthread/editpost pages.';
$l['threadfields_inputtype'] = 'Input Field Type';
$l['threadfields_inputtype_desc'] = 'What the user is presented with when they wish to edit the data of this custom field.  Tip: you may be able to use more exotic input arragements via template edits.';
$l['threadfields_editable'] = 'Editable by';
$l['threadfields_editable_desc'] = 'Specify who is allowed to modify the value of this field.  Can also be used to set whether this field is required to be filled out or not.  Note that a required field implies that everyone can edit this field.';
$l['threadfields_editable_gids'] = 'Editable by Usergroups';
$l['threadfields_editable_gids_desc'] = 'Specify which usergroups are allowed to edit this field.';
$l['threadfields_forums'] = 'Applicable Forums';
$l['threadfields_forums_desc'] = 'Select the forums where this custom thread field will be used.  Leave blank (select none) to make this field apply to all forums.';
$l['threadfields_sanitize'] = 'Display Parsing';
$l['threadfields_sanitize_desc'] = 'How the value is parsed when displayed, eg allow MyCode, HTML or just plain text.';
$l['threadfields_sanitize_parser'] = 'MyBB Parser Options';
$l['threadfields_sanitize_parser_desc'] = 'These options only apply if you have selected to parse this field with the MyBB parser.';
$l['threadfields_disporder'] = 'Display Order';
$l['threadfields_disporder_desc'] = 'The order in which this field is displayed on newthread/editthread.';
$l['threadfields_allowfilter'] = 'Allow Filtering';
$l['threadfields_allowfilter_desc'] = 'Allows users to filter threads using this thread field in forumdisplay.  This does not affect templates, so you need to make appropriate changes to make this option useful.  The URL is based on the <code>filtertf_<em>key</em></code> variable.  For example, <code>forumdisplay.php?fid=2&amp;filtertf_status=Resolved</code> will only show threads with the thread field &quot;status&quot; having a value of &quot;Resolved&quot;.  Note, multiple filters are allowed, and you can also specify an array of values for a single field.  Also note, if this field allows multiple values, filtering can be rather slow and increase server load by a fair bit.';
$l['threadfields_blankval'] = 'Blank Replacement Value';
$l['threadfields_blankval_desc'] = 'You can specify a custom value to be displayed if the user leaves this field blank (does not enter anything).  This field is not parsed, so you can enter HTML etc here.  Note, for file inputs, this is stored in <code>{$GLOBALS[\'threadfields\'][\'<em>key</em>\'][\'value\']}</code>';
$l['threadfields_defaultval'] = 'Default Value';
$l['threadfields_defaultval_desc'] = 'The default value for this field for new threads.  For example, if the type is a textbox, this value will fill the textbox by default, or if a selection list, this will be the default item which is selected.  You can select multiple options by default for multiple-select boxes and check boxes by separating selected items with a new line.';
$l['threadfields_dispformat'] = 'Display Format';
$l['threadfields_dispformat_desc'] = 'Custom formatting applied to value.  Use {VALUE} to represet the value of this field (non-file fields only).  This is only displayed if this field has a value (otherwise the above Blank Value is used).  Like above, this field is not parsed.  For file inputs, use {FILENAME}, {FILESIZE_FRIENDLY}, {UPLOAD_DATE} etc instead.';
$l['threadfields_dispitemformat'] = 'Display Item Format';
$l['threadfields_dispitemformat_desc'] = 'Like the &quot;Display Format&quot; field, but this one will be applied to every single value for this field as opposed to being applied to the concatenated list of values.';
$l['threadfields_multival_enable'] = 'Allow multiple values for this field';
$l['threadfields_multival_enable_desc'] = 'Allow the user to specify multiple input values for this field? (eg multiple tags for a single thread)';
$l['threadfields_multival'] = 'Multiple Value Delimiter';
$l['threadfields_multival_desc'] = 'The delimiter used to separate multiple values when displayed.  This value is not parsed (i.e. you can use HTML etc here).';
$l['threadfields_textmask'] = 'Text Mask Filter';
$l['threadfields_textmask_desc'] = 'Enter a regular expression which entered text must match (evaluated with <a href="http://php.net/manual/en/function.preg-match.php" target="_blank">preg_match</a>) for it to be valid.';
$l['threadfields_maxlen'] = 'Maximum Text Length';
$l['threadfields_maxlen_desc'] = 'The maximum valid length for the entered value.  0 means no maximum, however, note that the database engine will probably enforce a maximum length.  You should assume this length does not exceed 255 characters (or 65535 characters for the multiline textbox).';
$l['threadfields_fieldwidth'] = 'Field Input Width';
$l['threadfields_fieldwidth_desc'] = 'Width of the textbox/selectbox/whatever.';
$l['threadfields_fieldheight'] = 'Field Input Height';
$l['threadfields_fieldheight_desc'] = 'Height of the selectbox/textarea etc.';
$l['threadfields_filemagic'] = 'Valid File Magic';
$l['threadfields_filemagic_desc'] = 'A list of valid <a href="http://en.wikipedia.org/wiki/Magic_number_(programming)#Magic_numbers_in_files" target="_blank">file magic numbers</a> for this upload.  Use the pipe (|) character to separate values.  You can use URL encoding notation to represent binary characters (eg %00).';
$l['threadfields_fileexts'] = 'Valid File Extensions';
$l['threadfields_fileexts_desc'] = 'A pipe (|) separated list of valid file extensions accepted for this upload field.';
$l['threadfields_filemaxsize'] = 'Maximum File Size';
$l['threadfields_filemaxsize_desc'] = 'The maximum allowable file size, in <strong>bytes</strong>, for files accepted in this thread field.  0 = no maximum.';
$l['threadfields_filereqimg'] = 'Only accept uploaded images';
$l['threadfields_filereqimg_desc'] = 'If yes, will require the uploaded file for this field to be an image.  If it isn\'t deemed to be a valid image (according to GD) the file will be rejected.  Note that you must enable this option to use features like thumbnail generation.';
$l['threadfields_fileimage_mindim'] = 'Minimum Image Dimensions';
$l['threadfields_fileimage_mindim_desc'] = 'Smallest acceptable image dimensions, in <em>w</em>x<em>h</em> format, eg <em>60x30</em>.';
$l['threadfields_fileimage_maxdim'] = 'Maximum Image Dimensions';
$l['threadfields_fileimage_maxdim_desc'] = 'Largest acceptable image dimensions, in <em>w</em>x<em>h</em> format, eg <em>1920x1080</em>.';
//$l['threadfields_fileimage'] = 'Image Dimension Restrictions';
//$l['threadfields_fileimage_desc'] = 'Enforce dimension limits on the uploaded image.  To just enforce a minimum dimension, use <em>[min_width]x[min_height]</em>.  To just enforce maximum dimensions, use <em>0x0|[max_width]x[max_height].  For enforcing both min/max, use <em>[min_width]x[min_height]|[max_width]x[max_height]</em>.';
$l['threadfields_fileimgthumbs'] = 'Image Thumbnail Generation';
$l['threadfields_fileimgthumbs_desc'] = 'This field only applies if the above field is filled in.  This is a pipe (|) separated list of thumbnail dimensions which will be generated.  For example, if <em>160x120|320x240</em> is entered here, a 160x120 and a 320x240 thumbnail will be generated from the uploaded image.  These thumbnails can be accessed using something like <code>{$GLOBALS[\'threadfields\'][\'<em>key</em>\'][\'url\']}/thumb160x120</code>.<br />Note, if this field is changed whilst there are already images uploaded for this field, you may need to <a href="index.php?module=tools'.XTHREADS_ADMIN_PATHSEP.'recount_rebuild#rebuild_xtathumbs" target="_blank">rebuild thumbnails</a>.';
$l['threadfields_vallist'] = 'Values List';
$l['threadfields_vallist_desc'] = 'A list of valid values which can be entered for this field.  Separate values with newlines.  HTML can be used with checkbox/radio button input.';
$l['threadfields_formatmap'] = 'Formatting Map List';
$l['threadfields_formatmap_desc'] = 'A list of formatting definitions.  Separate items with newlines, and input/output pairs with the 3 characters, <em>{|}</em>.  The format map will translate defined inputs to the defined outputs.  For example, if you specify, for this field, <em>Resolved{|}&lt;span style=&quot;color: green;&quot;&gt;Resolved&lt;/span&gt;</em>, then if the user enters/selects &quot;Resolved&quot; for this field, it will be outputted in green wherever {$GLOBALS[\'threadfields\'][...]} is used.';
$l['threadfields_formhtml'] = 'Input Field HTML';
$l['threadfields_formhtml_desc'] = 'Enter HTML code for this custom input field.  Accepts variables like MyBB\'s template system.';
$l['threadfields_order'] = 'Order';
$l['threadfields_del'] = 'Del';
$l['threadfields_delete_field'] = 'Delete thread field';
$l['threadfields_delete_field_confirm'] = 'Are you sure you wish to delete the selected custom thread fields?';
$l['no_threadfields'] = 'There are no custom thread fields set at this time.';
$l['threadfields_inputtype_text'] = 'Textbox';
$l['threadfields_inputtype_textarea'] = 'Multiline Textbox';
$l['threadfields_inputtype_select'] = 'Listbox';
$l['threadfields_inputtype_radio'] = 'Option Buttons';
$l['threadfields_inputtype_checkbox'] = 'Checkboxes';
$l['threadfields_inputtype_file'] = 'File';
$l['threadfields_inputtype_file_url'] = 'File/URL';
$l['threadfields_inputtype_custom'] = 'Custom';
$l['threadfields_editable_everyone'] = 'Everyone';
$l['threadfields_editable_requied'] = 'Everyone (required)';
$l['threadfields_editable_mod'] = 'Moderators';
$l['threadfields_editable_admin'] = 'Administrators';
$l['threadfields_editable_none'] = 'Not editable';
$l['threadfields_editable_bygroup'] = 'Custom (specify usergroups)';
$l['threadfields_sanitize_plain'] = 'Plain text';
$l['threadfields_sanitize_plain_nl'] = 'Plain text with newlines';
$l['threadfields_sanitize_mycode'] = 'Use MyBB Parser (MyCode)';
$l['threadfields_sanitize_none'] = 'No parsing (dangerous!!)';
$l['threadfields_sanitize_parser_nl2br'] = 'Allow newlines';
$l['threadfields_sanitize_parser_nobadw'] = 'Filter Badwords';
$l['threadfields_sanitize_parser_html'] = 'Allow HTML';
$l['threadfields_sanitize_parser_mycode'] = 'Allow MyCode';
$l['threadfields_sanitize_parser_mycodeimg'] = 'Allow [img] MyCode';
$l['threadfields_sanitize_parser_smilies'] = 'Parse Smilies';
$l['threadfields_for_forums'] = 'For forum(s): {1}';
$l['threadfields_for_all_forums'] = 'For all forum(s)';
$l['error_invalid_field'] = 'Nonexistent thread field';
$l['add_threadfield'] = 'Add Thread Field';
$l['edit_threadfield'] = 'Edit Thread Field';
$l['update_threadfield'] = 'Update Thread Field';
$l['threadfields_advanced_opts'] = 'Advanced Options';
$l['success_updated_threadfield'] = 'Custom thread field updated successfully';
$l['success_added_threadfield'] = 'Custom thread field added successfully';
$l['success_threadfield_inline'] = 'Changes committed successfully';

$l['error_missing_title'] = 'Missing field title.';
$l['error_missing_field'] = 'Missing field key.';
$l['error_bad_old_field'] = 'Non-existent previous field key specified.';
$l['error_invalid_inputtype'] = 'Invalid input type specified.';
$l['error_dup_formatmap'] = 'Duplicate formatting definition for value <em>{1}</em> found.';
$l['error_bad_textmask'] = 'Bad regular expression used for Text Mask. PHP returned <em>{1}</em>';
$l['error_require_multival_delimiter'] = 'No multiple value delimiter defined (tip, you can set this to be a space).';
$l['error_invalid_min_dims'] = 'Invalid minimum dimensions specified.';
$l['error_invalid_max_dims'] = 'Invalid maximum dimensions specified.';
$l['error_invalid_thumb_dims'] = 'Invalid thumbnail dimensions specified.';
$l['error_field_name_in_use'] = 'The field key you have chosen is already in use for another field.  Please choose an unused unique key.';
$l['error_field_name_tid'] = 'Key name cannot be &quot;tid&quot; - please choose a different name.';
$l['error_field_name_invalid'] = 'Key names must contain only alphanumeric, underscore and hypen characters.';
$l['error_field_name_too_long'] = 'Key names must be 50 characters long or less.';

$l['threadfields_enable_js'] = 'It appears that you have JavaScript disabled.  To make things easier for you, it is strongly recommended to enable JavaScript for this page.';
$l['commit_changes'] = 'Commit Changes';

$l['xthreads_opts'] = 'XThreads Options';
$l['xthreads_tplprefix'] = 'Template Prefix';
$l['xthreads_tplprefix_desc'] = 'A template prefix allows you to use different templates for this forum.  For example, if you choose a prefix of <em>myforum_</em>, you could make a template named <em>myforum_header</em> and it will replace the <em>header</em> template for this forum.  This effect also applies to the <em>search_results_posts_post</em> and <em>search_results_threads_thread</em> templates, as well as the various <em>forumbit_</em>* templates.';
$l['xthreads_grouping'] = 'Thread Grouping';
$l['xthreads_grouping_desc'] = 'How many threads to group together.  A value of 0 disables grouping.  If grouping is enabled, the <em>forumdisplay_group_sep</em> template is inserted every <em>X</em> threads on the forumdisplay.  This is mainly useful if you wish to display multiple threads in a single table row.  If the number of threads does not fully fill a group, the template <em>forumdisplay_thread_null</em> is appended as many times needed to completely fill the thread group.  Internal counter is reset between sticky/normal thread separators.';
$l['xthreads_firstpostattop'] = 'Show first post on every showthread page';
$l['xthreads_firstpostattop_desc'] = 'Shows the first post at the top of every page in showthread, as opposed to just the first page.  Tip: you can use the <em>postbit_first*</em> templates as opposed to the <em>postbit*</em> templates to get a different look for the first post.  On the <em>showthread</em> template, the first post is separated into the <code>{$first_post}</code> variable.  Also, the template <em>showthread_noreplies</em> is used in the <code>{$posts}</code> variable if there are no replies to the thread.';
$l['xthreads_inlinesearch'] = 'Enable XThreads\' Inline Forum Search';
$l['xthreads_inlinesearch_desc'] = 'Replaces the search box on the forumdisplay page with XThreads\' inline search system, ignoring the search permission set for this forum.  This allows the search to display threads the same way as forumdisplay does.  The downside is that this may cause additional server load.';
$l['xthreads_threadsperpage'] = 'Override Threads Per Page';
$l['xthreads_threadsperpage_desc'] = 'If non-zero, overrides the default and user chosen threads per page setting for forumdisplay.';
$l['xthreads_postsperpage'] = 'Override Posts Per Page';
$l['xthreads_postsperpage_desc'] = 'If non-zero, overrides the default and user chosen posts per page setting for showthread.';
$l['xthreads_force_postlayout'] = 'Force Postbit Layout';
$l['xthreads_force_postlayout_desc'] = 'This can be used to force a postbit layout in this forum.';
$l['xthreads_force_postlayout_none'] = 'Don\'t force layout';
$l['xthreads_force_postlayout_horizontal'] = 'Force horizontal postbit layout';
$l['xthreads_force_postlayout_classic'] = 'Force classic postbit layout';
$l['xthreads_allow_blankmsg'] = 'Allow Blank Post Message';
$l['xthreads_allow_blankmsg_desc'] = 'If ticked, new threads in this forum will not require a message to be entered.';
$l['xthreads_nostatcount'] = 'Don\'t include this forum\'s threads/posts in global forum statistics';
$l['xthreads_nostatcount_desc'] = 'If ticked, threads and posts made in this forum will not increase the forum\'s statistics on the number of threads and posts across all forums (eg at the bottom of the forum home, or stats.php).';
$l['xthreads_cust_wolstr'] = 'Custom WOL Text';
$l['xthreads_cust_wolstr_desc'] = 'You can have custom text for this forum on the Who\'s Online List.  If you enter text in the following textboxes, it will replace the default WOL text.';
$l['xthreads_wol_announcements'] = 'Announcements';
$l['xthreads_wol_forumdisplay'] = 'Forum Display';
$l['xthreads_wol_newthread'] = 'New Thread';
$l['xthreads_wol_attachment'] = 'Attachment Download';
$l['xthreads_wol_newreply'] = 'New Reply';
$l['xthreads_wol_showthread'] = 'Show Thread';
$l['xthreads_wol_xtattachment'] = 'XThreads File Download';

$l['xthreads_confirm_uninstall'] = 'Are you sure you wish to uninstall XThreads?  Uninstalling will cause all XThreads related modifications (excluding template modifications you have performed on those not added by XThreads) will be removed.<br />Well, obviously you\'re sure, cause you clicked on the link... this is just for those (like me) who accidentally click on the wrong things...';


$l['xthreads_orphancleanup_name'] = 'Prune XThreads Orphaned Attachments';
$l['xthreads_orphancleanup_desc'] = 'Removes orphaned XThreads attachments more than one day old.  Orphaned attachments usually arise when users upload an attachment but decide not to post the thread.  Note that this does not affect MyBB\'s attachment system in any way.';





// since it's small, include it anyway (mainly to stop duplication of task language variables)
include_once dirname(__FILE__).'/../xthreads.lang.php';
