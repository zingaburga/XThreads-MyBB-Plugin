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
	<li><em>md5hash</em> - MD5 hash of file (note: not guaranteed to be set for larger files)</li>
	<li><em>upload_time</em> - time when file was initially uploaded</li>
	<li><em>upload_date</em> - date when file was initially uploaded</li>
	<li><em>update_time</em> - time when file was last updated (will be upload_time if never updated)</li>
	<li><em>update_date</em> - date when file was last updated (will be upload_date if never updated)</li>
	<li><em>icon</em> - MyBB\'s attachment file type icon</li>
	<li><em>value</em> - if no file is uploaded, will be Blank Value (see below), otherwise, will be Display Format</li>
	<li><em>dims</em> - an array containing width/height of uploaded image if the option to require image uploads is chosen.  For example <code>{$GLOBALS[\'threadfields\'][\'myimage\'][\'dims\'][\'w\']}</code> would get the width of the uploaded image.</li>
	<li><em>thumbs</em> - an array containing width/height of thumbnails (if used).  For example <code>{$GLOBALS[\'threadfields\'][\'myimage\'][\'thumbs\'][\'320x240\'][\'w\']}</code> would get the real image width of the 320x240 thumbnail.</li>
</ul>';
$l['threadfields_file_upload_disabled_warning'] = 'It appears that file uploading has been disabled on this server.  XThreads assumes that it is enabled, so if a user tries to upload something, it will fail unless you enable file uploads (URL fetching will still work if enabled).  Please set the <em>file_uploads</em> php.ini value to \'1\'.';
$l['threadfields_title'] = 'Title';
$l['threadfields_title_desc'] = 'Name of this custom thread field.';
$l['threadfields_desc'] = 'Description';
$l['threadfields_desc_desc'] = 'A short description of this field which is placed under the name in the input field for newthread/editpost pages.';
$l['threadfields_inputtype'] = 'Input Field Type';
$l['threadfields_inputtype_desc'] = 'What the user is presented with when they wish to edit the data of this custom field.  Tip: you may be able to use more exotic input arragements via template edits.';
$l['threadfields_editable'] = 'Editable by / Required Field?';
$l['threadfields_editable_desc'] = 'Specify who is allowed to modify the value of this field.  Can also be used to set whether this field is required to be filled out or not.  Note that a required field implies that everyone can edit this field.';
$l['threadfields_editable_gids'] = 'Editable by Usergroups';
$l['threadfields_editable_gids_desc'] = 'Specify which usergroups are allowed to edit this field.';
$l['threadfields_viewable_gids'] = 'Viewable by Usergroups';
$l['threadfields_viewable_gids_desc'] = 'You can specify usergroups which can view the value of this field.  Selecting none means it is viewable to all usergroups.  Note that filtering and sorting by this thread field is affected by this setting.';
$l['threadfields_unviewableval'] = 'Unviewable Value';
$l['threadfields_unviewableval_desc'] = 'What to display to usergroups who cannot view the value of this field.  This works exactly the same as the <em>Display Format</em> above (even <code>{VALUE}</code> works, so this could also be used to display something different to select usergroups).';
$l['threadfields_forums'] = 'Applicable Forums';
$l['threadfields_forums_desc'] = 'Select the forums where this custom thread field will be used.  Leave blank (select none) to make this field apply to all forums.';
$l['threadfields_sanitize'] = 'Display Parsing';
$l['threadfields_sanitize_desc'] = 'How the value is parsed when displayed, eg allow MyCode, HTML or just plain text.';
$l['threadfields_sanitize_parser'] = 'MyBB Parser Options';
$l['threadfields_sanitize_parser_desc'] = 'These options only apply if you have selected to parse this field with the MyBB parser.';
$l['threadfields_disporder'] = 'Display Order';
$l['threadfields_disporder_desc'] = 'The order in which this field is displayed on newthread/editpost.';
$l['threadfields_tabstop'] = 'Capture Tab Key';
$l['threadfields_tabstop_desc'] = 'If Yes, this field will intercept and respond to the user pressing the Tab key, when cycling through form elements.  Tab index will depend on the order specified above; it will always be placed between the subject and message field\'s tab index.  Note that setting this to No won\'t stop this field from responding to the Tab key - it simply won\'t set a <code>tabindex</code> property for this field.';
$l['threadfields_hideedit'] = 'Hide Input Field';
$l['threadfields_hideedit_desc'] = 'If yes, will not display the input field on newthread/editpost pages through the <code>{$extra_threadfields}</code> variable.  This is useful if you want to customise the HTML for this input field or place it in a different location.  You can still use the default HTML by using <code>{$tfinputrow[\'<em>key</em>\']}</code>';
$l['threadfields_allowfilter'] = 'Allow Filtering';
$l['threadfields_allowfilter_desc'] = 'Allows users to filter threads using this thread field in forumdisplay.  This does not affect templates, so you need to make appropriate changes to make this option useful.  The URL is based on the <code>filtertf_<em>key</em></code> variable.  For example, <code>forumdisplay.php?fid=2&amp;filtertf_status=Resolved</code> will only show threads with the thread field &quot;status&quot; having a value of &quot;Resolved&quot;.  Note, multiple filters are allowed, and you can also specify an array of values for a single field, for example <code>forumdisplay.php?fid=2&amp;filtertf_status[]=Resolved&amp;filtertf_status[]=Rejected&amp;filtertf_cat=Technical</code> will display threads with the thread field &quot;cat&quot; equalling &quot;Technical&quot; <em>and</em> &quot;status&quot; being either &quot;Resolved&quot; <em>or</em> &quot;Rejected&quot;.  Also note, if this field allows multiple values (or checkbox input), filtering can be rather slow and increase server load by a fair bit.';
$l['threadfields_blankval'] = 'Blank Replacement Value';
$l['threadfields_blankval_desc'] = 'You can specify a custom value to be displayed if the user leaves this field blank (does not enter anything).  This field is not sanitised, so you can enter HTML etc here.  Some variables will work like <code>{$fid}</code>, as well as <a href="http://mybbhacks.zingaburga.com/showthread.php?tid=464">conditionals</a>.  Note, for file inputs, this is stored in <code>{$GLOBALS[\'threadfields\'][\'<em>key</em>\'][\'value\']}</code>';
$l['threadfields_defaultval'] = 'Default Value';
$l['threadfields_defaultval_desc'] = 'The default value for this field for new threads.  For example, if the type is a textbox, this value will fill the textbox by default, or if a selection list, this will be the default item which is selected.  You can select multiple options by default for multiple-select boxes and check boxes by separating selected items with a new line.  Variables and conditionals supported here - note that these are applied before the separation for multiple values are done (if necessary).';
$l['threadfields_dispformat'] = 'Display Format';
$l['threadfields_dispformat_desc'] = 'Custom formatting applied to value.  Use {VALUE} to represet the value of this field (non-file fields only).  This is only displayed if this field has a value (otherwise the above Blank Value is used).  Like above, this field is not parsed.  For file inputs, use {FILENAME}, {FILESIZE_FRIENDLY}, {UPLOAD_DATE} etc instead.<br />This field can also accept some variables, eg <code>{$fid}</code>, as well as <a href="http://mybbhacks.zingaburga.com/showthread.php?tid=464">conditionals</a> (&lt;template ...&gt; calls not supported).';
$l['threadfields_dispitemformat'] = 'Display Item Format';
$l['threadfields_dispitemformat_desc'] = 'Like the &quot;Display Format&quot; field, but this one will be applied to every single value for this field as opposed to being applied to the concatenated list of values.';
$l['threadfields_datatype'] = 'Underlying Data Type';
$l['threadfields_datatype_desc'] = 'The underlying storage data format for this field.  This should be left as the default value, <em>Text</em>, unless you have a particular reason not to do so.  Non-<em>Text</em> datatypes cannot accept multiple values.
<br /><span style="color: red;">Warning: changing this option may cause data loss to existing data stored in this thread field!</span>';
$l['threadfields_datatype_text'] = 'Text';
$l['threadfields_datatype_int'] = 'Integer (signed, usually 32-bit)';
$l['threadfields_datatype_uint'] = 'Integer (unsigned, usually 32-bit)';
$l['threadfields_datatype_bigint'] = 'Big Integer (signed, usually 64-bit)';
$l['threadfields_datatype_biguint'] = 'Big Integer (unsigned, usually 64-bit)';
$l['threadfields_datatype_float'] = 'Float (double precision)';
$l['threadfields_multival_enable'] = 'Allow multiple values for this field';
$l['threadfields_multival_enable_desc'] = 'Allow the user to specify multiple input values for this field? (eg multiple tags for a single thread)  Note, for textbox inputs, the comma (,) will be used as a delimiter, whereas for textarea, newlines will be considered as a delimiter.';
$l['threadfields_multival'] = 'Multiple Value Delimiter';
$l['threadfields_multival_desc'] = 'The delimiter used to separate multiple values when displayed.  This value is not parsed (i.e. you can use HTML etc here).';
$l['threadfields_textmask'] = 'Text Mask Filter';
$l['threadfields_textmask_desc'] = 'Enter a regular expression which entered text must match (evaluated with <a href="http://php.net/manual/en/function.preg-match.php" target="_blank">preg_match</a>, using <em>s</em> and <em>i</em> flags) for it to be valid.  Captured patterns can be used in Display Format and similar fields through <code>{VALUE$1}</code> etc';
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
$l['threadfields_filemaxsize_desc_2gbwarn'] = '  Note, you are running a 32-bit version of PHP, which may have issues with handling files larger than 2GB in size.  A 64-bit build of PHP does not have this issue.';
$l['threadfields_filemaxsize_desc_phplimit'] = 'File uploads will be constrained by the following PHP limitations (these upload limits can be changed in php.ini):';
$l['threadfields_filereqimg'] = 'Only Accept Image Files';
$l['threadfields_filereqimg_desc'] = 'If yes, will require the uploaded (or URL fetched) file for this field to be an image.  If it isn\'t deemed to be a valid image (according to GD) the file will be rejected.  Note that you must enable this option to use features like thumbnail generation.';
$l['threadfields_fileimage_mindim'] = 'Minimum Image Dimensions';
$l['threadfields_fileimage_mindim_desc'] = 'Smallest acceptable image dimensions, in <em>w</em>x<em>h</em> format, eg <em>60x30</em>.';
$l['threadfields_fileimage_maxdim'] = 'Maximum Image Dimensions';
$l['threadfields_fileimage_maxdim_desc'] = 'Largest acceptable image dimensions, in <em>w</em>x<em>h</em> format, eg <em>1920x1080</em>.';
$l['threadfields_fileimgthumbs'] = 'Image Thumbnail Generation';
$l['threadfields_fileimgthumbs_desc'] = 'This field only applies if this field only accepts images.  This is a pipe (|) separated list of thumbnail dimensions which will be generated.  For example, if <em>160x120|320x240</em> is entered here, a 160x120 and a 320x240 thumbnail will be generated from the uploaded image.  These thumbnails can be accessed using something like <code>{$GLOBALS[\'threadfields\'][\'<em>key</em>\'][\'url\']}/thumb160x120</code>.<br />Note, if this field is changed whilst there are already images uploaded for this field, you may need to <a href="'.xthreads_admin_url('tools', 'recount_rebuild').'#rebuild_xtathumbs" target="_blank">rebuild thumbnails</a>.';
$l['threadfields_vallist'] = 'Values List';
$l['threadfields_vallist_desc'] = 'A list of valid values which can be entered for this field.  Separate values with newlines.  HTML can be used with checkbox/radio button input.  It is recommended that you do not exceed 255 characters for each value/line.';
$l['threadfields_formatmap'] = 'Formatting Map List';
$l['threadfields_formatmap_desc'] = 'A list of formatting definitions.  The format map will \'translate\' defined inputs to the defined outputs.  <noscript>Separate items with newlines, and input/output pairs with the 3 characters, <em>{|}</em>.  For example, if you specify, for this field, <em>Resolved{|}&lt;span style=&quot;color: green;&quot;&gt;Resolved&lt;/span&gt;</em>, then if the user enters/selects &quot;Resolved&quot; for this field, it will be outputted in green wherever {$GLOBALS[\'threadfields\'][...]} is used.  </noscript>Some variables will work like <code>{$fid}</code>.';
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
$l['threadfields_sanitize_parser_mycodevid'] = 'Allow [video] MyCode';
$l['threadfields_sanitize_parser_smilies'] = 'Parse Smilies';
$l['threadfields_textmask_anything'] = 'Anything';
$l['threadfields_textmask_anything_desc'] = 'Allow any text - equivalent to no filtering';
$l['threadfields_textmask_digit'] = 'Digits';
$l['threadfields_textmask_digit_desc'] = 'Only accept digit (0-9) characters';
$l['threadfields_textmask_alphadigit'] = 'Alphanumeric';
$l['threadfields_textmask_alphadigit_desc'] = 'Only accept alphanumeric (0-9 and A-Z) characters';
$l['threadfields_textmask_number'] = 'Number (real)';
$l['threadfields_textmask_number_desc'] = 'Only accept a number string, including decimals, optional negative sign, and exponent (eg 1e10).  Examples of valid numbers: 4, 2.01, -5.020e5.  Negative sign can be captured through <code>{VALUE$1}</code>, numbers to the left of the decimal point through <code>{VALUE$2}</code>, numbers right of the decimal: <code>{VALUE$3}</code> and the exponent through <code>{VALUE$4}</code>';
$l['threadfields_textmask_date'] = 'Date (dd/mm/yyyy)';
$l['threadfields_textmask_date_desc'] = 'Date in non-US form (01/01/1900 - 31/12/2099).  This doesn\'t ensure an entirely valid date, however it does perform some checks.  Day can be displayed through <code>{VALUE$1}</code>, month through <code>{VALUE$2}</code> and year through <code>{VALUE$3}</code>.';
$l['threadfields_textmask_date_us'] = 'Date (mm/dd/yyyy)';
$l['threadfields_textmask_date_us_desc'] = 'Date in US form (01/01/1900 - 12/31/2099).  This doesn\'t ensure an entirely valid date, however it does perform some checks.  Month can be displayed through <code>{VALUE$1}</code>, day through <code>{VALUE$2}</code> and year through <code>{VALUE$3}</code>.';
$l['threadfields_textmask_url'] = 'URL';
$l['threadfields_textmask_url_desc'] = '(Almost) any URI.  URI scheme can be displayed through <code>{VALUE$1}</code>, URI host through <code>{VALUE$2}</code> and URI path, with preceeding forward slash, through <code>{VALUE$3}</code>';
$l['threadfields_textmask_httpurl'] = 'URL (HTTP/S)';
$l['threadfields_textmask_httpurl_desc'] = 'Any http:// or https:// style URL.  URI scheme can be displayed through <code>{VALUE$1}</code>, URI host through <code>{VALUE$2}</code> and URI path, with preceeding forward slash, through <code>{VALUE$3}</code>';
$l['threadfields_textmask_email'] = 'Email address';
$l['threadfields_textmask_email_desc'] = 'Username can be displayed via <code>{VALUE$1}</code>, host through <code>{VALUE$2}</code>';
$l['threadfields_textmask_css'] = 'CSS Value';
$l['threadfields_textmask_css_desc'] = 'Value appropriate for placing as a CSS value, for example <code>style=&quot;font-family: {VALUE};&quot;</code>.  Note brackets and quotes are not allowed, so values such as <code>url(...)</code> are not allowed.';
$l['threadfields_textmask_color'] = 'Color Value';
$l['threadfields_textmask_color_desc'] = 'Color name (eg <code>red</code>) or hexadecimal representation (eg <code>#FF0000</code>)';
$l['threadfields_textmask_custom'] = 'Custom (regex)';
$l['threadfields_for_forums'] = 'For forum(s): {1}';
$l['threadfields_for_all_forums'] = 'For all forum(s)';
$l['threadfields_deleted_forum_id'] = 'Deleted Forum #{1}';
$l['error_invalid_field'] = 'Nonexistent thread field';
$l['add_threadfield'] = 'Add Thread Field';
$l['edit_threadfield'] = 'Edit Thread Field';
$l['update_threadfield'] = 'Update Thread Field';
$l['threadfields_advanced_opts'] = 'Advanced Options';
$l['success_updated_threadfield'] = 'Custom thread field updated successfully';
$l['success_added_threadfield'] = 'Custom thread field added successfully';
$l['success_threadfield_inline'] = 'Changes committed successfully';
$l['failed_threadfield_inline'] = 'No changes were performed';

$l['error_missing_title'] = 'Missing field title.';
$l['error_missing_field'] = 'Missing field key.';
$l['error_bad_old_field'] = 'Non-existent previous field key specified.';
$l['error_invalid_inputtype'] = 'Invalid input type specified.';
$l['error_dup_formatmap'] = 'Duplicate formatting definition for value <em>{1}</em> found.';
$l['error_bad_textmask'] = 'Bad regular expression used for Text Mask. PHP returned <em>{1}</em>';
$l['error_require_valllist'] = 'Select/checkbox/radiobutton input types must have a defined (non-empty) Value List.';
$l['error_require_multival_delimiter'] = 'No multiple value delimiter defined (tip, you can set this to be a space).';
$l['error_invalid_min_dims'] = 'Invalid minimum dimensions specified.';
$l['error_invalid_max_dims'] = 'Invalid maximum dimensions specified.';
$l['error_invalid_thumb_dims'] = 'Invalid thumbnail dimensions specified.';
$l['error_field_name_in_use'] = 'The field key you have chosen is already in use for another field.  Please choose an unused unique key.';
$l['error_field_name_tid'] = 'Key name cannot be &quot;tid&quot; - please choose a different name.';
$l['error_field_name_invalid'] = 'Key names must contain only alphanumeric, underscore and hypen characters.';
$l['error_field_name_reserved'] = 'Sorry, key names cannot start with two underscore characters (__) because this is a reserved construct.';
$l['error_field_name_too_long'] = 'Key names must be 50 characters long or less.';

$l['threadfields_enable_js'] = 'It appears that you have JavaScript disabled.  To make things easier for you, it is strongly recommended to enable JavaScript for this page.';
$l['commit_changes'] = 'Commit Changes';

$l['xthreads_desc_more'] = 'Show full description...';
$l['xthreads_opts'] = 'XThreads Options <span style="font-size: smaller;">(note that these settings do not cascade down into child forums)</span>';
$l['xthreads_tplprefix'] = 'Template Prefix';
$l['xthreads_tplprefix_desc'] = 'A template prefix allows you to use different templates for this forum.  For example, if you choose a prefix of <em>myforum_</em>, you could make a template named <em>myforum_header</em> and it will replace the <em>header</em> template for this forum.
<br /><!-- more -->
This field supports variables and conditionals - do note that these are evaluated quite early in the script (for caching reasons) before many variables get set.  Multiple prefixes can be defined, separated by commas - XThreads will attempt to find a template using one of the prefixes in the order defined, <em>after</em> variables and conditionals are evaluated.
<br />This effect also applies to the <em>search_results_posts_post</em> and <em>search_results_threads_thread</em> templates, as well as the various <em>forumbit_</em>* and <em>portal_announcement</em>* templates.  Note that, for these special cases (excluding <em>forumbit_*</em> templates), multiple template prefixes will not be searched - only the first prefix will be used.';
$l['xthreads_langprefix'] = 'Language File Prefix';
$l['xthreads_langprefix_desc'] = 'This option will load additional language files based on the prefixes supplied (comma delimited if you wish to supply more than one prefix).
<br /><!-- more -->This field supports variables and conditionals, similar to how the template prefix field above works.  For example, if you specify <em>lp1_,lp2_</em> here, when MyBB tries to load, say, <em>forumdisplay.lang.php</em>, XThreads will then load (if possible) <em>lp1_forumdisplay.lang.php</em> followed by <em>lp2_forumdisplay.lang.php</em>, adding to and overwriting any language definitions defined in previously loaded files.';
$l['xthreads_grouping'] = 'Thread Grouping';
$l['xthreads_grouping_desc'] = 'How many threads to group together.  A value of 0 disables grouping.  If grouping is enabled, the <em>forumdisplay_group_sep</em> template is inserted every <em>X</em> threads on the forumdisplay.
<br /><!-- more -->This is mainly useful if you wish to display multiple threads in a single table row.  If the number of threads does not fully fill a group, the template <em>forumdisplay_thread_null</em> is appended as many times needed to completely fill the thread group.  Internal counter is reset between sticky/normal thread separators.';
$l['xthreads_firstpostattop'] = 'Show first post on every showthread page';
$l['xthreads_firstpostattop_desc'] = 'Shows the first post at the top of every page in showthread, as opposed to just the first page.
<br /><!-- more -->Tip: you can use the <em>postbit_first*</em> templates as opposed to the <em>postbit*</em> templates to get a different look for the first post.  On the <em>showthread</em> template, the first post is separated into the <code>{$first_post}</code> variable.  Also, the template <em>showthread_noreplies</em> is used in the <code>{$posts}</code> variable if there are no replies to the thread.';
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
$l['xthreads_hideforum'] = 'Hide Forum';
$l['xthreads_hideforum_desc'] = 'If yes, will hide this forum on your index and forumdisplay pages.
<br /><!-- more -->This is slightly different to disabling the Can View Forum permission in that this does not affect permissions, it just merely hides it from display (so, for example, you could put a link to it in your main menu).';
$l['xthreads_hidebreadcrumb'] = 'Hide Forum in Breadcrumb Trail';
$l['xthreads_hidebreadcrumb_desc'] = 'If yes, will hide this forum in the forum breadcrumb <strong>trail</strong> (it will still appear in the breadcrumb if it\'s the active forum).';
$l['xthreads_allow_blankmsg'] = 'Allow Blank Post Message';
$l['xthreads_allow_blankmsg_desc'] = 'If yes, new threads in this forum will not require a message to be entered.';
$l['xthreads_nostatcount'] = 'Don\'t include this forum\'s threads/posts in global forum statistics';
$l['xthreads_nostatcount_desc'] = 'If yes, threads and posts made in this forum will not increase the forum\'s statistics on the number of threads and posts across all forums (eg at the bottom of the forum home, or stats.php).';
$l['xthreads_defaultfilter'] = 'Default Thread Filter';
$l['xthreads_defaultfilter_desc'] = 'This filter is applied to forumdisplay if no filter has been specified in the URL.  Separate entries with newlines; variables/conditionals supported (in filter value only), as well as URI encoding (note, URI decoding done <em>after</em> variables and conditionals have been evaluated).
<br /><!-- more -->The default filter can also be disabled with no additional filter in use, by specifying <em>filterdisable</em> in the URL, eg <em>forumdisplay.php?fid=2&amp;filterdisable=1</em>
<br />Example value for this field:
<code style="display: block; margin-left: 2em;">myfield=something<br />__xt_uid=1<br />field2[]=value1<br />field2[]={$mybb-&gt;user[\'username\']}</code>';
/* $l['xthreads_addfiltenable'] = 'Enable Thread Filters';
$l['xthreads_addfiltenable_desc'] = 'Enable users to filter forumdisplay by certain thread attributes (eg thread starter).
<br /><!-- more -->This feature works similar to filtering forumdisplay threads by custom thread fields.  This does not affect templates, so you need to make appropriate changes to make this option useful.  If you tick any of the options below, users can filter threads displayed on forumdisplay by the relevant fields by appending the URL parameter <code>filterxt_<em>fieldname</em></code>, for example <em>forumdisplay.php?fid=2&amp;filterxt_uid=2</em> will only display threads created by the user with UID of 2.  Note, multiple filters are allowed, and you can also specify an array of values for a single field.'; */
$l['xthreads_cust_wolstr'] = 'Custom WOL Text';
$l['xthreads_cust_wolstr_desc'] = 'You can have custom text for this forum on the Who\'s Online List.
<br /><!-- more -->If you enter text in the following textboxes, it will replace the default WOL language text.  As this replaces language strings, it will accept variables in the same way.  Go to <a href="'.xthreads_admin_url('config', 'languages').'">Languages section</a> -&gt; Edit Language Variables (under Options for your selected language) -&gt; edit <em>online.lang.php</em> to see the defaults.';
$l['xthreads_afe_uid'] = 'Thread starter\'s User ID';
$l['xthreads_afe_lastposteruid'] = 'Last poster\'s User ID';
$l['xthreads_afe_prefix'] = 'Thread Prefix ID; <em>check URL (look for <strong>pid</strong>) when editing thread prefix in ACP</em>';
$l['xthreads_afe_icon'] = 'Thread Icon ID; <em>check URL (look for <strong>iid</strong>) when editing thread icon in ACP</em>';
$l['xthreads_wol_announcements'] = 'Announcements';
$l['xthreads_wol_forumdisplay'] = 'Forum Display';
$l['xthreads_wol_newthread'] = 'New Thread';
$l['xthreads_wol_attachment'] = 'Attachment Download';
$l['xthreads_wol_newreply'] = 'New Reply';
$l['xthreads_wol_showthread'] = 'Show Thread';
$l['xthreads_wol_xtattachment'] = 'XThreads File Download';

$l['xthreads_sort_threadfield_prefix'] = 'Thread Field: ';
$l['xthreads_sort_filename'] = 'file name';
$l['xthreads_sort_filesize'] = 'file size';
$l['xthreads_sort_uploadtime'] = 'upload time';
$l['xthreads_sort_updatetime'] = 'update time';
$l['xthreads_sort_downloads'] = 'no. downloads';
$l['xthreads_sort_ext_prefix'] = 'Thread Prefix (not display style)';
$l['xthreads_sort_ext_icon'] = 'Thread Icon (icon ID)';
$l['xthreads_sort_ext_lastposter'] = 'Last Poster (username)';
$l['xthreads_sort_ext_numratings'] = 'Number of Ratings';
$l['xthreads_sort_ext_attachmentcount'] = 'Number of Attachments in Thread';

$l['xthreads_modtool_edit_threadfields'] = 'Modify Custom Thread Field(s)';
$l['xthreads_modtool_edit_threadfields_desc'] = 'You can use this option to modify XThreads\' custom thread fields when this tool is executed.  Specify each thread field you wish to edit on a separate line and assign to the thread field\'s key using = (equals sign).  The current value (before setting) of the field can be denoted by <code>{VALUE}</code>.  Variables/conditionals supported.  NOTE: values here are NOT validated, and permissions are NOT checked!  Example:
<code style="display: block; margin-left: 2em;">myfield=something<br />anotherfield={VALUE},something else</code>';

$l['xthreads_js_confirm_form_submit'] = 'You have an editor window open - are you sure you wish to submit these changes without closing this window?';
$l['xthreads_js_edit_value'] = 'Edit Value';
$l['xthreads_js_save_changes'] = 'Save Changes';
$l['xthreads_js_close_save_changes'] = 'Do you wish to save changes before closing this window?';

$l['xthreads_js_formatmap_from'] = 'Value';
$l['xthreads_js_formatmap_to'] = 'Displayed Output';

$l['xthreads_confirm_uninstall'] = 'Are you sure you wish to uninstall XThreads?  Uninstalling will cause all XThreads related modifications (excluding template modifications you have performed on those not added by XThreads) will be removed.<br />Well, obviously you\'re sure, cause you clicked on the link... this is just for those (like me) who accidentally click on the wrong things...';


$l['xthreads_orphancleanup_name'] = 'Prune XThreads Orphaned Attachments';
$l['xthreads_orphancleanup_desc'] = 'Removes orphaned XThreads attachments more than one day old.  Orphaned attachments usually arise when users upload an attachment but decide not to post the thread.  Note that this does not affect MyBB\'s attachment system in any way.';

$l['admin_log_config_threadfields_add'] = 'Added thread field <em>{1}</em> ({2})';
$l['admin_log_config_threadfields_edit'] = 'Modified thread field <em>{1}</em> ({2})';
$l['admin_log_config_threadfields_inline'] = 'Deleted or changed orderings of selected thread field(s)'; // legacy note
$l['admin_log_config_threadfields_inline_del'] = 'Deleted thread field(s): {1}';
$l['admin_log_config_threadfields_inline_order'] = 'Updated display order of thread field(s): {1}';
$l['admin_log_config_threadfields_inline_delim'] = '; ';

$l['xthreads_do_upgrade'] = 'You have uploaded a newer version of XThreads, v{1}, however the version currently installed is v{2}.  You may need to perform an upgrade for your board to be functional - to perform an upgrade, please <a href="{3}">click here</a>.';
$l['xthreads_upgrade_done'] = 'XThreads successfully upgraded.';
$l['xthreads_upgrade_failed'] = 'XThreads upgraded failed.';
