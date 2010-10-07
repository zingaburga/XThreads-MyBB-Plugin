<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

$lang->load('xthreads');

$page->add_breadcrumb_item($lang->custom_threadfields, XTHREADS_ADMIN_CONFIG_PATH.'threadfields');

$plugins->run_hooks('admin_config_threadfields_begin');

$sub_tabs['threadfields'] = array(
	'title' => $lang->custom_threadfields,
	'description' => $lang->custom_threadfields_desc,
	'link' => XTHREADS_ADMIN_CONFIG_PATH.'threadfields'
);
$sub_tabs['threadfields_add'] = array(
	'title' => $lang->add_threadfield,
	'description' => $lang->custom_threadfields_desc,
	'link' => XTHREADS_ADMIN_CONFIG_PATH.'threadfields&amp;action=add'
);


if($mybb->input['action'] == 'add')
{
	$plugins->run_hooks('admin_config_threadfields_add');
	$tf = array(
		'field' => '',
		'title' => '',
		'forums' => '',
		'editable' => XTHREADS_EDITABLE_ALL,
		'editable_gids' => '',
		'blankval' => '',
		'defaultval' => '',
		'dispformat' => '{VALUE}',
		'dispitemformat' => '{VALUE}',
		'formatmap' => '',
		'textmask' => '^.*$',
		'maxlen' => 0,
		'fieldwidth' => 40,
		'fieldheight' => 5,
		'vallist' => '',
		'multival' => '',
		'sanitize' => XTHREADS_SANITIZE_HTML | XTHREADS_SANITIZE_PARSER_NOBADW | XTHREADS_SANITIZE_PARSER_MYCODE | XTHREADS_SANITIZE_PARSER_SMILIES,
		'allowfilter' => 0,
		
		'desc' => '',
		'inputtype' => XTHREADS_INPUT_TEXT,
		'disporder' => 1,
		'formhtml' => '',
		
		'filemagic' => '',
		'fileexts' => '',
		'filemaxsize' => 0,
		'fileimage' => '',
		'fileimgthumbs' => '',
		
	);
	threadfields_add_edit_handler($tf, false);
}

if($mybb->input['action'] == 'edit')
{
	$plugins->run_hooks('admin_config_threadfields_edit');
	
	$mybb->input['field'] = trim($mybb->input['field']);
	
	$tf = $db->fetch_array($db->simple_select('threadfields', '*', 'field="'.$db->escape_string($mybb->input['field']).'"'));
	if(!$tf['field']) {
		flash_message($lang->error_invalid_field, 'error');
		admin_redirect(XTHREADS_ADMIN_CONFIG_PATH.'threadfields');
	}
	
	threadfields_add_edit_handler($tf, true);
}

if($mybb->input['action'] == 'inline')
{
	$del = $delattach = $order = array();
	$alterkeys = '';
	$query = $db->simple_select('threadfields', 'field,allowfilter,inputtype');
	while($field = $db->fetch_array($query)) {
		$efn = $db->escape_string($field['field']);
		if($mybb->input['threadfields_mark_'.$field['field']]) {
			$del[] = $efn;
			if($field['allowfilter'])
				$alterkeys .= ', DROP KEY `'.$efn.'`';
			if($field['inputtype'] == XTHREADS_INPUT_FILE || $field['inputtype'] == XTHREADS_INPUT_FILE_URL)
				$delattach[] = $efn;
		}
		elseif($mybb->input['threadfields_order_'.$field['field']]) {
			//$order[$field['field']] = intval($mybb->input['threadfields_order_'.$field['field']]);
			$db->update_query('threadfields', array('disporder' => intval($mybb->input['threadfields_order_'.$field['field']])), 'field="'.$efn.'"');
		}
	}
	$db->free_result($query);
	if(!empty($del)) {
		$db->delete_query('threadfields', 'field IN ("'.implode('","', $del).'")');
		$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields_data` DROP COLUMN `'.implode('`, DROP COLUMN `', $del).'`'.$alterkeys);
		
		// delete attachments? - might be a little slow...
		if(!empty($delattach)) {
			@ignore_user_abort(true);
			@set_time_limit(0);
			require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
			$qwhere = 'field IN ("'.implode('","', $delattach).'")';
			$query = $db->simple_select('xtattachments', '*', $qwhere);
			while($xta = $db->fetch_array($query)) {
				xthreads_rm_attach_fs($xta);
			}
			$db->free_result($query);
			$query = $db->delete_query('xtattachments', $qwhere);
		}
	}
	// Log admin action
	log_admin_action();
	xthreads_buildtfcache();
	flash_message($lang->success_threadfield_inline, 'success');
	admin_redirect(XTHREADS_ADMIN_CONFIG_PATH.'threadfields');
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks('admin_config_threadfields_start');
	
	$page->output_header($lang->custom_threadfields);
	$page->output_nav_tabs($sub_tabs, 'threadfields');

	$form = new Form(XTHREADS_ADMIN_CONFIG_PATH.'threadfields&amp;action=inline', 'post', 'inline');
	
	$table = new Table;
	$table->construct_header($lang->threadfields_title);
	$table->construct_header($lang->threadfields_name, array('width' => '25%'));
	$table->construct_header($lang->threadfields_inputtype, array('width' => '20%'));
	$table->construct_header($lang->threadfields_editable, array('width' => '15%'));
	$table->construct_header($lang->threadfields_order, array('width' => '5%'));
	$table->construct_header($lang->threadfields_del, array('width' => '2%'));

	// categorize by forums
	$forums = $cache->read('forums');
	$query = $db->query('SELECT `title`,`field`,`editable`,`editable_gids`,`disporder`,`inputtype`,`forums` FROM `'.$db->table_prefix.'threadfields` ORDER BY `forums` ASC, `disporder` ASC');
	$tf_forum = '-';
	$js_indexes = '""';
	while($tf = $db->fetch_array($query))
	{
		if($tf_forum != $tf['forums']) {
			$tf_forum = $tf['forums'];
			if(!$tf_forum)
				$celldata = $lang->threadfields_for_all_forums;
			else {
				$fids = array_unique(array_map('intval', array_map('trim', explode(',', $tf_forum))));
				$fnames = '';
				foreach($fids as &$fid)
					$fnames .= ($fnames ? ', ' : '').$forums[$fid]['name'];
				$celldata = $lang->sprintf($lang->threadfields_for_forums, $fnames);
			}
			$table->construct_cell($celldata, array('colspan' => 6, 'style' => 'padding: 2px;'));
			$table->construct_row();
		}
		$tfname = htmlspecialchars_uni($tf['field']);
		$table->construct_cell('<a href="'.XTHREADS_ADMIN_CONFIG_PATH.'threadfields&amp;action=edit&amp;field='.urlencode($tf['field']).'"><strong>'.htmlspecialchars_uni($tf['title']).'</strong></a>');
		// ... but generate_check_box doesn't have a "style" thing for the options array ... :(
		$table->construct_cell($tfname);
		$inputtype_lang = '';
		switch($tf['inputtype']) {
			case XTHREADS_INPUT_TEXT: $inputtype_lang = 'threadfields_inputtype_text'; break;
			case XTHREADS_INPUT_TEXTAREA: $inputtype_lang = 'threadfields_inputtype_textarea'; break;
			case XTHREADS_INPUT_SELECT: $inputtype_lang = 'threadfields_inputtype_select'; break;
			case XTHREADS_INPUT_RADIO: $inputtype_lang = 'threadfields_inputtype_radio'; break;
			case XTHREADS_INPUT_CHECKBOX: $inputtype_lang = 'threadfields_inputtype_checkbox'; break;
			case XTHREADS_INPUT_FILE: $inputtype_lang = 'threadfields_inputtype_file'; break;
			case XTHREADS_INPUT_FILE_URL: $inputtype_lang = 'threadfields_inputtype_file_url'; break;
			case XTHREADS_INPUT_CUSTOM: $inputtype_lang = 'threadfields_inputtype_custom'; break;
		}
		$table->construct_cell($lang->$inputtype_lang);
		if($tf['editable_gids']) {
			if(!is_array($usergroups)) $usergroups = $cache->read('usergroups');
			$ugtext = $ugcomma = '';
			foreach(explode(',', $tf['editable_gids']) as $gid) {
				$ugtext .= $ugcomma.htmlspecialchars_uni($usergroups[$gid]['title']);
				if(!$ugcomma) $ugcomma = ', ';
			}
			$table->construct_cell($ugtext);
		}
		else {
			$editable_lang = '';
			switch($tf['editable']) {
				case XTHREADS_EDITABLE_ALL: $editable_lang = 'threadfields_editable_everyone'; break;
				case XTHREADS_EDITABLE_REQ: $editable_lang = 'threadfields_editable_requied'; break;
				case XTHREADS_EDITABLE_MOD: $editable_lang = 'threadfields_editable_mod'; break;
				case XTHREADS_EDITABLE_ADMIN: $editable_lang = 'threadfields_editable_admin'; break;
				case XTHREADS_EDITABLE_NONE: $editable_lang = 'threadfields_editable_none'; break;
			}
			$table->construct_cell($lang->$editable_lang);
		}
		$table->construct_cell($form->generate_text_box('threadfields_order_'.$tfname, $tf['disporder'], array('class' => 'text_input align_center', 'style' => 'width: 80%;', 'id' => 'threadfields_order_'.$tfname)), array('class' => 'align_center'));
		$table->construct_cell($form->generate_check_box('threadfields_mark_'.$tfname.'" title="'.$lang->threadfields_delete_field, '1', '', array('style' => 'vertical-align: middle;', 'id' => 'threadfields_mark_'.$tfname)), array('class' => 'align_center'));
		$table->construct_row(array('id' => 'threadfields_row_'.$tfname));
		$js_indexes .= ',"'.$tfname.'"';
	}
	$db->free_result($query);
	
	$showsubmit = false;
	if(!$table->num_rows()) {
		$table->construct_cell($lang->no_threadfields, array('colspan' => 6));
		$table->construct_row();
	} else
		$showsubmit = true;
	
	$table->output($lang->custom_threadfields);
	
	if($js_indexes && $js_indexes != '""') {
?><script type="text/javascript">
<!--
	function xt_fields_delcheck() {
		var c=this.checked;
		var n=this.id.substr(<?php echo strlen('threadfields_mark_'); ?>);
		$('threadfields_order_'+n).disabled = c;
		
		var bg = (c ? '#fffbd9':''), fg = (c ? '#a0a0a0':'');
		var robj = $('threadfields_row_'+n);
		robj.style.backgroundColor = bg;
		robj.style.color = fg;
		var cells = robj.getElementsByTagName('td');
		for(cell in cells) {
			cells[cell].style.backgroundColor = bg;
			cells[cell].style.color = fg;
		}
	}
	var xt_fields = [<?php echo $js_indexes; ?>];
	for(i=1; i<xt_fields.length; i++) {
		var xt_o = $('threadfields_mark_'+xt_fields[i]);
		xt_o.onclick = xt_fields_delcheck;
		// weird, the apply method isn't working here...
		//xt_fields_delcheck(xt_o);
		xt_o.checked=false;
	}
	
	function xt_fields_submit() {
		for(i=1; i<xt_fields.length; i++) {
			if($('threadfields_mark_'+xt_fields[i]).checked) {
				return confirm("<?php echo strtr($lang->threadfields_delete_field_confirm, array('\\' => '\\\\', '"' => '\\"')); ?>");
			}
		}
		return true;
	}
//-->
</script><?php
	}
	
	if($showsubmit) {
		$buttons[] = $form->generate_submit_button($lang->commit_changes, array('onclick' => 'return xt_fields_submit();'));
		$form->output_submit_wrapper($buttons);
	}
	$form->end();

	$page->output_footer();
}



function threadfields_add_edit_handler(&$tf, $update) {
	global $mybb, $page, $lang, $db, $plugins, $sub_tabs;
	global $form_container, $form;
	
	if($update) $title = $lang->edit_threadfield;
		else $title = $lang->add_threadfield;
	
	if($mybb->request_method == 'post')
	{
		$mybb->input['title'] = trim($mybb->input['title']);
		$mybb->input['desc'] = trim($mybb->input['desc']);
		$mybb->input['newfield'] = trim($mybb->input['newfield']);
		$mybb->input['blankval'] = trim($mybb->input['blankval']);
		$mybb->input['defaultval'] = trim($mybb->input['defaultval']);
		$mybb->input['dispformat'] = trim($mybb->input['dispformat']);
		$mybb->input['dispitemformat'] = trim($mybb->input['dispitemformat']);
		$mybb->input['textmask'] = trim($mybb->input['textmask']);
		$mybb->input['maxlen'] = intval($mybb->input['maxlen']);
		$mybb->input['fieldwidth'] = intval($mybb->input['fieldwidth']);
		$mybb->input['fieldheight'] = intval($mybb->input['fieldheight']);
		$mybb->input['vallist'] = trim($mybb->input['vallist']);
		$mybb->input['disporder'] = intval($mybb->input['disporder']);
		$mybb->input['formhtml'] = trim($mybb->input['formhtml']);
		$mybb->input['allowfilter'] = intval($mybb->input['allowfilter']);
		
		$mybb->input['filemagic'] = trim($mybb->input['filemagic']);
		$mybb->input['fileexts'] = trim($mybb->input['fileexts']);
		$mybb->input['filemaxsize'] = intval($mybb->input['filemaxsize']);
		//$mybb->input['fileimage'] = trim($mybb->input['fileimage']);
		$mybb->input['fileimage_mindim'] = strtolower(trim($mybb->input['fileimage_mindim']));
		$mybb->input['fileimage_maxdim'] = strtolower(trim($mybb->input['fileimage_maxdim']));
		$mybb->input['fileimgthumbs'] = trim($mybb->input['fileimgthumbs']);
		
		$mybb->input['formatmap'] = trim($mybb->input['formatmap']);
		if($mybb->input['formatmap']) {
			$fm = array();
			foreach(explode("\n", str_replace("\r", '', $mybb->input['formatmap'])) as $map) {
				$p = strpos($map, '{|}');
				if(!$p) continue; // can't be zero index either
				$fmkey = substr($map, 0, $p);
				if(isset($fm[$fmkey])) {
					$errors[] = $lang->sprintf($lang->error_dup_formatmap, htmlspecialchars_uni($fmkey));
				}
				$fm[$fmkey] = substr($map, $p+3);
			}
			$mybb->input['formatmap'] = serialize($fm);
		}
		
		if(is_array($mybb->input['forums'])) {
			$mybb->input['forums'] = implode(',', array_unique(array_map('intval', array_map('trim', $mybb->input['forums']))));
			if(empty($mybb->input['forums']))
				$mybb->input['forums'] = '';
		} else {
			$mybb->input['forums'] = trim($mybb->input['forums']);
			if($mybb->input['forums'])
				$mybb->input['forums'] = implode(',', array_unique(array_map('intval', array_map('trim', explode(',',$mybb->input['forums'])))));
			if(!$mybb->input['forums']) $mybb->input['forums'] = '';
		}
		
		if($mybb->input['editable'] == '99') {
			if(is_array($mybb->input['editable_gids'])) {
				$mybb->input['editable_gids'] = implode(',', array_unique(array_map('intval', array_map('trim', $mybb->input['editable_gids']))));
				if(empty($mybb->input['editable_gids']))
					$mybb->input['editable_gids'] = '';
			} else {
				$mybb->input['editable_gids'] = trim($mybb->input['editable_gids']);
				if($mybb->input['editable_gids'])
					$mybb->input['editable_gids'] = implode(',', array_unique(array_map('intval', array_map('trim', explode(',',$mybb->input['editable_gids'])))));
				if(!$mybb->input['editable_gids']) $mybb->input['editable_gids'] = '';
			}
			if($mybb->input['editable_gids'])
				$mybb->input['editable'] = 0;
			else
				$mybb->input['editable'] = XTHREADS_EDITABLE_NONE; // no group ids selected
		} else {
			$mybb->input['editable'] = min_max(intval($mybb->input['editable']), XTHREADS_EDITABLE_ALL, XTHREADS_EDITABLE_NONE);
			$mybb->input['editable_gids'] = '';
		}
		$mybb->input['sanitize'] = min_max(intval($mybb->input['sanitize']), XTHREADS_SANITIZE_HTML, XTHREADS_SANITIZE_NONE);
		//if($mybb->input['sanitize'] == XTHREADS_SANITIZE_PARSER) {
			$parser_opts = array(
				'parser_nl2br' => XTHREADS_SANITIZE_PARSER_NL2BR,
				'parser_nobadw' => XTHREADS_SANITIZE_PARSER_NOBADW,
				'parser_html' => XTHREADS_SANITIZE_PARSER_HTML,
				'parser_mycode' => XTHREADS_SANITIZE_PARSER_MYCODE,
				'parser_mycodeimg' => XTHREADS_SANITIZE_PARSER_MYCODEIMG,
				'parser_smilies' => XTHREADS_SANITIZE_PARSER_SMILIES,
			);
			foreach($parser_opts as $opt => $n)
				if($mybb->input[$opt])
					$mybb->input['sanitize'] |= $n;
		//}
		$mybb->input['inputtype'] = min_max(intval($mybb->input['inputtype']), XTHREADS_INPUT_TEXT, XTHREADS_INPUT_CUSTOM);
		
		if(!$mybb->input['title'])		$errors[] = $lang->error_missing_title;
		if(!$mybb->input['newfield'])	$errors[] = $lang->error_missing_field;
		
		if($mybb->input['textmask']) {
			// test for bad regex
			if(function_exists('error_get_last')) {
				@preg_match('~'.str_replace('~', '\\~', $mybb->input['textmask']).'~si', 'testvalue');
				$error = error_get_last();
				$errmsg = $error['message'];
			} else {
				$te = @ini_get('track_errors');
				if(!$te)
					@ini_set('track_errors', 1);
				@preg_match('~'.str_replace('~', '\\~', $mybb->input['textmask']).'~si', 'testvalue');
				$errmsg = $php_errormsg;
				if(!$te)
					@ini_set('track_errors', $te);
			}
			
			if(substr($errmsg, 0, 13) == 'preg_match():')
				$errors[] = $lang->sprintf($lang->error_bad_textmask, trim(substr($errmsg, 13)));
		}
		
		switch($mybb->input['inputtype']) {
			case XTHREADS_INPUT_SELECT:
				$mybb->input['sanitize'] = XTHREADS_SANITIZE_HTML;
				$mybb->input['textmask'] = '';
				break;
			case XTHREADS_INPUT_RADIO:
			case XTHREADS_INPUT_CHECKBOX:
				$mybb->input['sanitize'] = XTHREADS_SANITIZE_NONE;
				$mybb->input['textmask'] = '';
				break;
			
			case XTHREADS_INPUT_TEXTAREA:
			case XTHREADS_INPUT_FILE:
			case XTHREADS_INPUT_FILE_URL:
				$mybb->input['allowfilter'] = 0;
				$mybb->input['vallist'] = '';
				break;
			case XTHREADS_INPUT_TEXT:
				$mybb->input['vallist'] = '';
		}
		
		if($mybb->input['multival_enable'] || $mybb->input['inputtype'] == XTHREADS_INPUT_CHECKBOX) {
			if(!$mybb->input['multival'])
				$errors[] = $lang->error_require_multival_delimiter;
		} else
			$mybb->input['multival'] = '';
		
		$mybb->input['fileimage'] = '';
		if($mybb->input['filereqimg']) {
			if($mybb->input['fileimage_mindim'] && !preg_match('~^[0-9]+x[0-9]+$~', $mybb->input['fileimage_mindim']))
				$errors[] = $lang->error_invalid_min_dims;
			if($mybb->input['fileimage_maxdim'] && !preg_match('~^[0-9]+x[0-9]+$~', $mybb->input['fileimage_maxdim']))
				$errors[] = $lang->error_invalid_max_dims;
			
			if($mybb->input['fileimage_mindim'])
				$mybb->input['fileimage'] = $mybb->input['fileimage_mindim'];
			else
				$mybb->input['fileimage'] = '0x0';
			if($mybb->input['fileimage_maxdim'])
				$mybb->input['fileimage'] .= '|'.$mybb->input['fileimage_maxdim'];
		}
		if($mybb->input['fileimgthumbs']) {
			// verify format
			if(!preg_match('~^[0-9]+x[0-9]+(\\|[0-9]+x[0-9]+)*$~', $mybb->input['fileimgthumbs']))
				$errors[] = $lang->error_invalid_thumb_dims;
		}
		
		if($update) {
			// check that sent field name is valid
			// and whilst we're here, check for bad conversions (eg file -> textbox)
			$oldfield = $db->fetch_array($db->simple_select('threadfields', '*', 'field="'.$db->escape_string($mybb->input['field']).'"'));
			if(empty($oldfield))
				$errors[] = $lang->error_bad_old_field;
			else {
				switch($oldfield['inputtype']) {
					case XTHREADS_INPUT_FILE:
					case XTHREADS_INPUT_FILE_URL:
						if($oldfield['inputtype'] != $mybb->input['inputtype'])
							$errors['error_invalid_inputtype'] = $lang->error_invalid_inputtype;
						break;
					default:
						if($mybb->input['inputtype'] == XTHREADS_INPUT_FILE || $mybb->input['inputtype'] == XTHREADS_INPUT_FILE_URL)
							$errors['error_invalid_inputtype'] = $lang->error_invalid_inputtype;
				}
			}
		}
		
		if($mybb->input['newfield']) {
			if($mybb->input['newfield'] == 'tid') {
				$errors[] = $lang->error_field_name_tid;
			}
			elseif(strlen($mybb->input['newfield']) > 50) {
				$errors[] = $lang->error_field_name_too_long;
			}
			elseif(!preg_match('~^[a-z0-9_\\-]+$~i', $mybb->input['newfield'])) {
				$errors[] = $lang->error_field_name_invalid;
			}
			// field name in use?
			elseif(!$update || $mybb->input['field'] != $mybb->input['newfield']) {
				$ftest = $db->fetch_field($db->simple_select('threadfields', 'field', 'field="'.$db->escape_string($mybb->input['newfield']).'"'), 'field');
				if($ftest) $errors[] = $lang->error_field_name_in_use;
			}
		}

		if(!$errors)
		{
			$new_tf = array(
				'title' => $db->escape_string($mybb->input['title']),
				'desc' => $db->escape_string($mybb->input['desc']),
				'field' => $db->escape_string($mybb->input['newfield']),
				'forums' => $db->escape_string($mybb->input['forums']),
				'disporder' => $db->escape_string($mybb->input['disporder']),
				'editable' => $db->escape_string($mybb->input['editable']),
				'editable_gids' => $db->escape_string($mybb->input['editable_gids']),
				'inputtype' => $db->escape_string($mybb->input['inputtype']),
				'blankval' => $db->escape_string($mybb->input['blankval']),
				'defaultval' => $db->escape_string($mybb->input['defaultval']),
				'dispformat' => $db->escape_string($mybb->input['dispformat']),
				'dispitemformat' => $db->escape_string($mybb->input['dispitemformat']),
				'formatmap' => $db->escape_string($mybb->input['formatmap']),
				'formhtml' => $db->escape_string($mybb->input['formhtml']),
				'multival' => $db->escape_string($mybb->input['multival']),
				'textmask' => $db->escape_string($mybb->input['textmask']),
				'maxlen' => $db->escape_string($mybb->input['maxlen']),
				'fieldwidth' => $db->escape_string($mybb->input['fieldwidth']),
				'fieldheight' => $db->escape_string($mybb->input['fieldheight']),
				'vallist' => $db->escape_string($mybb->input['vallist']),
				'sanitize' => $db->escape_string($mybb->input['sanitize']),
				'allowfilter' => $db->escape_string($mybb->input['allowfilter']),
				'filemagic' => $db->escape_string($mybb->input['filemagic']),
				'fileexts' => $db->escape_string($mybb->input['fileexts']),
				'filemaxsize' => $db->escape_string($mybb->input['filemaxsize']),
				'fileimage' => $db->escape_string($mybb->input['fileimage']),
				'fileimgthumbs' => $db->escape_string($mybb->input['fileimgthumbs']),
			);

			switch($mybb->input['inputtype']) {
				case XTHREADS_INPUT_FILE:
					$fieldtype = 'int(10) unsigned not null default 0';
					break;
				case XTHREADS_INPUT_TEXTAREA:
					$fieldtype = 'text not null';
					break;
				case XTHREADS_INPUT_FILE_URL:
					$fieldtype = 'varchar(255) not null default ""';
					break;
				default:
					$fieldtype = 'varchar(255) not null default "'.$new_tf['defaultval'].'"';
			}
			if($update) {
				$plugins->run_hooks('admin_config_threadfields_edit_commit');
				$db->update_query('threadfields', $new_tf, 'field="'.$db->escape_string($mybb->input['field']).'"');
				
				$alterations = array();
				// TODO: perhaps only run this query if necessary
				//if($mybb->input['field'] != $mybb->input['newfield'])
				$alterations[] = 'CHANGE `'.$db->escape_string($mybb->input['field']).'` `'.$new_tf['field'].'` '.$fieldtype;
				
				if($new_tf['allowfilter'] != $oldfield['allowfilter']) {
					if($new_tf['allowfilter'])
						$alterations[] = 'ADD KEY `'.$new_tf['field'].'` (`'.$new_tf['field'].'`)';
					else
						$alterations[] = 'DROP KEY `'.$db->escape_string($mybb->input['field']).'`';
				}
				elseif($new_tf['allowfilter'] && $mybb->input['field'] != $mybb->input['newfield']) {
					// change key name - only way to do this in MySQL appears to be recreating the key...
					$alterations[] = 'DROP KEY `'.$db->escape_string($mybb->input['field']).'`';
					$alterations[] = 'ADD KEY `'.$new_tf['field'].'` (`'.$new_tf['field'].'`)';
				}
				if(!empty($alterations)) {
					$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields_data` '.implode(', ', $alterations));
					if($mybb->input['field'] != $mybb->input['newfield'] && ($new_tf['inputtype'] == XTHREADS_INPUT_FILE || $new_tf['inputtype'] == XTHREADS_INPUT_FILE_URL)) {
						// need to update xtattachments table too!
						$db->update_query('xtattachments', array('field' => $new_tf['field']), 'field="'.$db->escape_string($mybb->input['field']).'"');
					}
				}
			}
			else {
				$plugins->run_hooks('admin_config_threadfields_add_commit');
				$db->insert_query('threadfields', $new_tf);
				
				if($new_tf['allowfilter'])
					$fieldtype .= ', ADD KEY (`'.$new_tf['field'].'`)';
				
				$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields_data` ADD COLUMN `'.$new_tf['field'].'` '.$fieldtype);
			}

			// Log admin action
			log_admin_action($new_tf['field'], $mybb->input['title']);

			xthreads_buildtfcache();

			if($update)
				flash_message($lang->success_updated_threadfield, 'success');
			else
				flash_message($lang->success_added_threadfield, 'success');
			admin_redirect(XTHREADS_ADMIN_CONFIG_PATH.'threadfields');
		}
	}

	$page->add_breadcrumb_item($title);
	$page->output_header($lang->custom_threadfields.' - '.$title);
	
	echo '<noscript>';
	$page->output_alert($lang->threadfields_enable_js);
	echo '</noscript>';
	
	if(!$update)
		$page->output_nav_tabs($sub_tabs, 'threadfields_add');
	
	if($update)
		$form = new Form(XTHREADS_ADMIN_CONFIG_PATH.'threadfields&amp;action=edit&amp;field='.urlencode($tf['field']), 'post');
	else
		$form = new Form(XTHREADS_ADMIN_CONFIG_PATH.'threadfields&amp;action=add', 'post');

	if($errors) {
		$page->output_inline_error($errors);
		$GLOBALS['data'] =& $mybb->input;
	}
	else {
		$GLOBALS['data'] =& $tf;
	}
	global $data;

	$form_container = new FormContainer($title);
	$form_container->output_row($lang->threadfields_title.' <em>*</em>', $lang->threadfields_title_desc, $form->generate_text_box('title', $data['title'], array('id' => 'title')), 'title');
	if(isset($data['newfield']))
		$key =& $data['newfield'];
	else
		$key =& $data['field'];
	$form_container->output_row($lang->threadfields_name.' <em>*</em>', $lang->threadfields_name_desc, $form->generate_text_box('newfield', $key, array('id' => 'newfield')), 'newfield');
	make_form_row('desc', 'text_box');
	if($data['forums'] && !is_array($data['forums']))
		$data['forums'] = array_map('intval',array_map('trim',explode(',', $data['forums'])));
	$form_container->output_row($lang->threadfields_forums, $lang->threadfields_forums_desc, $form->generate_forum_select('forums[]', $data['forums'], array('multiple' => true, 'size' => 5)), 'forums');
	
	$inputtypes = array(
		XTHREADS_INPUT_TEXT => $lang->threadfields_inputtype_text,
		XTHREADS_INPUT_TEXTAREA => $lang->threadfields_inputtype_textarea,
		XTHREADS_INPUT_SELECT => $lang->threadfields_inputtype_select,
		XTHREADS_INPUT_RADIO => $lang->threadfields_inputtype_radio,
		XTHREADS_INPUT_CHECKBOX => $lang->threadfields_inputtype_checkbox,
		XTHREADS_INPUT_FILE => $lang->threadfields_inputtype_file,
		//XTHREADS_INPUT_FILE_URL => $lang->threadfields_inputtype_file_url,
		XTHREADS_INPUT_CUSTOM => $lang->threadfields_inputtype_custom,
	);
	if($update) { // disable some conversions as they are not possible
		if(isset($errors['error_invalid_inputtype'])) { // but if invalid type is supplied, don't lock the user in either
			$inputtype = $oldfield['inputtype'];
		} else {
			$inputtype = $data['inputtype'];
		}
		if($inputtype == XTHREADS_INPUT_FILE || $inputtype == XTHREADS_INPUT_FILE_URL) {
			foreach($inputtypes as $k => &$v)
				if($k != $inputtype)
					unset($inputtypes[$k]);
		}
		else {
			unset($inputtypes[XTHREADS_INPUT_FILE], $inputtypes[XTHREADS_INPUT_FILE_URL]);
		}
	}
	// TODO: weird issue where inputtype isn't being set...
	make_form_row('inputtype', 'select_box', $inputtypes, '<div id="inputtype_file_explain" style="font-size: 0.95em; margin-top: 1em;">'.$lang->threadfields_file_name_info.'</div>');
	make_form_row('maxlen', 'text_box');
	make_form_row('fieldwidth', 'text_box');
	make_form_row('fieldheight', 'text_box');
	make_form_row('vallist', 'text_area');
	make_form_row('formhtml', 'text_area');
	make_form_row('fileexts', 'text_box');
	make_form_row('filemaxsize', 'text_box');
	if($data['editable_gids'] && !is_array($data['editable_gids']))
		$data['editable_gids'] = array_map('intval',array_map('trim',explode(',', $data['editable_gids'])));
	if(!empty($data['editable_gids']))
		$data['editable'] = 99;
	make_form_row('editable', 'select_box', array(
		XTHREADS_EDITABLE_ALL => $lang->threadfields_editable_everyone,
		XTHREADS_EDITABLE_REQ => $lang->threadfields_editable_requied,
		XTHREADS_EDITABLE_MOD => $lang->threadfields_editable_mod,
		XTHREADS_EDITABLE_ADMIN => $lang->threadfields_editable_admin,
		XTHREADS_EDITABLE_NONE => $lang->threadfields_editable_none,
		99 => $lang->threadfields_editable_bygroup,
	));
	$form_container->output_row($lang->threadfields_editable_gids, $lang->threadfields_editable_gids_desc, xt_generate_group_select('editable_gids[]', $data['editable_gids'], array('multiple' => true, 'size' => 5)), 'editable_gids', array(), array('id' => 'row_editable_gids'));
	make_form_row('sanitize', 'select_box', array(
		XTHREADS_SANITIZE_HTML => $lang->threadfields_sanitize_plain,
		XTHREADS_SANITIZE_HTML_NL => $lang->threadfields_sanitize_plain_nl,
		XTHREADS_SANITIZE_PARSER => $lang->threadfields_sanitize_mycode,
		XTHREADS_SANITIZE_NONE => $lang->threadfields_sanitize_none,
	));
	$parser_opts = array(
		'parser_nl2br' => $data['sanitize'] & XTHREADS_SANITIZE_PARSER_NL2BR,
		'parser_nobadw' => $data['sanitize'] & XTHREADS_SANITIZE_PARSER_NOBADW,
		'parser_html' => $data['sanitize'] & XTHREADS_SANITIZE_PARSER_HTML,
		'parser_mycode' => $data['sanitize'] & XTHREADS_SANITIZE_PARSER_MYCODE,
		'parser_mycodeimg' => $data['sanitize'] & XTHREADS_SANITIZE_PARSER_MYCODEIMG,
		'parser_smilies' => $data['sanitize'] & XTHREADS_SANITIZE_PARSER_SMILIES,
	);
	$parser_opts_str = '';
	foreach($parser_opts as $opt => $checked) {
		$langstr = 'threadfields_sanitize_'.$opt;
		$parser_opts_str .= '<div style="display: block;">'.$form->generate_check_box($opt, 1, $lang->$langstr, array('checked' => ($checked ? 1:0))).'</div>';
	}
	$form_container->output_row($lang->threadfields_sanitize_parser, $lang->threadfields_sanitize_parser_desc, $parser_opts_str, 'sanitize_parser', array(), array('id' => 'parser_opts'));
	
	make_form_row('disporder', 'text_box');
	$form_container->end();
	unset($GLOBALS['form_container']);
	
	global $form_container;
	$form_container = new FormContainer($lang->threadfields_advanced_opts);
	make_form_row('allowfilter', 'yes_no_radio');
	make_form_row('filemagic', 'text_box');
	$data['filereqimg'] = ($data['fileimage'] ? 1:0);
	make_form_row('filereqimg', 'yes_no_radio');
	unset($data['filereqimg']);
	$data['fileimage_mindim'] = $data['fileimage_maxdim'] = '';
	if($data['fileimage']) {
		list($min, $max) = explode('|', $data['fileimage']);
		if($min == '0x0') $min = '';
		$data['fileimage_mindim'] = $min;
		$data['fileimage_maxdim'] = $max;
	}
	make_form_row('fileimage_mindim', 'text_box');
	make_form_row('fileimage_maxdim', 'text_box');
	unset($data['fileimage_mindim'], $data['fileimage_maxdim']);
	make_form_row('fileimgthumbs', 'text_box');
	make_form_row('blankval', 'text_area');
	make_form_row('defaultval', 'text_area');
	make_form_row('dispformat', 'text_area');
	$data['multival_enable'] = ($data['multival'] ? 1:0);
	make_form_row('multival_enable', 'yes_no_radio');
	unset($data['multival_enable']);
	$lang->threadfields_multival .= ' <em>*</em>';
	make_form_row('multival', 'text_box');
	$lang->threadfields_multival = substr($lang->threadfields_multival, 0, -11);
	make_form_row('dispitemformat', 'text_area');
	make_form_row('textmask', 'text_box');
	
	if(!is_array($data['formatmap'])) {
		$fm = @unserialize($data['formatmap']);
		if(is_array($fm))
			$data['formatmap'] =& $fm;
	}
	if(is_array($data['formatmap'])) {
		$fmtxt = '';
		foreach($data['formatmap'] as $k => &$v)
			// don't need to htmlspecialchar - it'll be done for us
			$fmtxt .= $k.'{|}'.$v."\n";
		$data['formatmap'] =& $fmtxt;
	}
	make_form_row('formatmap', 'text_area');
	$form_container->end();
	if($update)
		$buttons[] = $form->generate_submit_button($lang->update_threadfield);
	else
		$buttons[] = $form->generate_submit_button($lang->add_threadfield);
	$form->output_submit_wrapper($buttons);
	$form->end();
	
?><script type="text/javascript">
<!--
	function xt_visi(o,v) {
		$(o).style.display = (v ? '':'none');
	}
	$('sanitize').onchange = function() {
		xt_visi('parser_opts', this.options[this.selectedIndex].value == "<?php echo XTHREADS_SANITIZE_PARSER; ?>" && $('row_sanitize').style.display != 'none');
	};
	
	function xt_multival_enable() {
		var si = parseInt($('inputtype').options[$('inputtype').selectedIndex].value);
		var e = (si == <?php echo XTHREADS_INPUT_CHECKBOX; ?>); // forced
		if(!e) e = ($('multival_enable_yes').checked && $('row_multival_enable').style.display != 'none');
		xt_visi('row_multival', e);
		xt_visi('row_dispitemformat', e);
	}
	$('multival_enable_yes').onclick = xt_multival_enable;
	$('multival_enable_no').onclick = xt_multival_enable;
	
	function xt_filereqimg() {
		var e = ($('filereqimg_yes').checked && $('row_filereqimg').style.display != 'none');
		xt_visi('row_fileimage_mindim', e);
		xt_visi('row_fileimage_maxdim', e);
		xt_visi('row_fileimgthumbs', e);
	}
	$('filereqimg_yes').onclick = xt_filereqimg;
	$('filereqimg_no').onclick = xt_filereqimg;
	
	
	($('inputtype').onchange = function() {
		var si = parseInt(this.options[this.selectedIndex].value);
		
		var pureFileIn = (si == <?php echo XTHREADS_INPUT_FILE; ?>);
		var fileIn = (pureFileIn || si == <?php echo XTHREADS_INPUT_FILE_URL; ?>);
		var radioIn = (si == <?php echo XTHREADS_INPUT_RADIO; ?>);
		var checkboxIn = (si == <?php echo XTHREADS_INPUT_CHECKBOX; ?>);
		var selectBoxIn = (si == <?php echo XTHREADS_INPUT_SELECT; ?>);
		var selectIn = (selectBoxIn || radioIn || checkboxIn);
		var textAreaIn = (si == <?php echo XTHREADS_INPUT_TEXTAREA; ?>);
		var textIn = (textAreaIn || si == <?php echo XTHREADS_INPUT_TEXT; ?>);
		xt_visi('row_sanitize', !fileIn && !selectIn);
		$('sanitize').onchange();
		
		xt_visi('inputtype_file_explain', pureFileIn);
		
		xt_visi('row_allowfilter', !fileIn && !textAreaIn);
		xt_visi('row_formatmap', !fileIn);
		xt_visi('row_defaultval', !pureFileIn);
		
		xt_visi('row_textmask', textIn || si == <?php echo XTHREADS_INPUT_CUSTOM; ?>);
		xt_visi('row_maxlen', textIn);
		xt_visi('row_fieldwidth', textIn || fileIn || selectBoxIn);
		xt_visi('row_fieldheight', textAreaIn || selectBoxIn);
		
		xt_visi('row_vallist', selectIn);
		xt_visi('row_formhtml', si == <?php echo XTHREADS_INPUT_CUSTOM; ?>);
		
		xt_visi('row_multival_enable', !checkboxIn && !radioIn && !fileIn);
		xt_multival_enable();
		
		xt_visi('row_filemagic', pureFileIn);
		xt_visi('row_fileexts', pureFileIn);
		xt_visi('row_filemaxsize', pureFileIn);
		xt_visi('row_filereqimg', pureFileIn);
		xt_filereqimg();
	}).apply($('inputtype'));
	
	($('editable').onchange = function() {
		xt_visi('row_editable_gids', this.options[this.selectedIndex].value == "99");
	}).apply($('editable'));
//-->
</script><?php
	
	$page->output_footer();
}
// returns a number within the range min/max
function min_max($val, $min, $max) {
	return min(max($val, $min), $max);
}
function make_form_row($n, $it, $opts=array(), $html_append='') {
	global $form_container, $form, $lang, $data;
	$lang_n = 'threadfields_'.$n;
	$lang_d = 'threadfields_'.$n.'_desc';
	$it = 'generate_'.$it;
	if($it == 'generate_yes_no_radio')
		$html = $form->$it($n, ($data[$n] ? '1':'0'), true, array('id' => $n.'_yes'), array('id' => $n.'_no'));
	elseif($it == 'generate_select_box') {
		if(count($opts) == 1)
			$html = $form->$it($n, $opts, $data[$n], array('id' => $n)); //.'" disabled="disabled'
		else
			$html = $form->$it($n, $opts, $data[$n], array('id' => $n));
	}
	else
		$html = $form->$it($n, $data[$n], array('id' => $n));
	
	$form_container->output_row($lang->$lang_n, $lang->$lang_d, $html.$html_append, $n, array(), array('id' => 'row_'.$n));

}

// method copied from MyBB 1.6
//  + bugfix in HTML output
function xt_generate_group_select($name, $selected=array(), $options=array())
{
	global $cache, $form;
	if(method_exists($form, 'generate_group_select')) {
		return $form->generate_group_select($name, $selected, $options);
	}
	
	$select = "<select name=\"{$name}\"";
	
	if(isset($options['multiple']))
	{
		$select .= " multiple=\"multiple\"";
	}
	
	if(isset($options['class']))
	{
		$select .= " class=\"{$options['class']}\"";
	}
	
	if(isset($options['id']))
	{
		$select .= " id=\"{$options['id']}\"";
	}
	
	if(isset($options['size']))
	{
		$select .= " size=\"{$options['size']}\"";
	}
	
	$select .= ">\n";
	
	$groups_cache = $cache->read('usergroups');
	foreach($groups_cache as $group)
	{
		$selected_add = "";
		if(is_array($selected))
		{
			if(in_array($group['gid'], $selected))
			{
				$selected_add = " selected=\"selected\"";
			}
		}
		
		$select .= "<option value=\"{$group['gid']}\"{$selected_add}>".htmlspecialchars_uni(strip_tags($group['title']))."</option>";
	}
	
	$select .= "</select>";
	
	return $select;
}

?>