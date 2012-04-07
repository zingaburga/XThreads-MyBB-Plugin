<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

$lang->load('xthreads');

$page->add_breadcrumb_item($lang->custom_threadfields, xthreads_admin_url('config', 'threadfields'));

$plugins->run_hooks('admin_config_threadfields_begin');

$sub_tabs['threadfields'] = array(
	'title' => $lang->custom_threadfields,
	'description' => $lang->custom_threadfields_desc,
	'link' => xthreads_admin_url('config', 'threadfields')
);
$sub_tabs['threadfields_add'] = array(
	'title' => $lang->add_threadfield,
	'description' => $lang->custom_threadfields_desc,
	'link' => xthreads_admin_url('config', 'threadfields&amp;action=add')
);


if($mybb->input['action'] == 'add')
{
	$plugins->run_hooks('admin_config_threadfields_add');
	$tf = array();
	foreach(xthreads_threadfields_props() as $field => $prop) {
		if($prop['datatype'] == 'boolean')
			$tf[$field] = ($prop['default'] ? 1:0);
		else
			$tf[$field] = $prop['default'];
	}
	/*
	$tf = array(
		'field' => '',
		'title' => '',
		'forums' => '',
		'editable' => XTHREADS_EDITABLE_ALL,
		'editable_gids' => '',
		'viewable_gids' => '',
		'unviewableval' => '',
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
		'sanitize' => XTHREADS_SANITIZE_HTML | XTHREADS_SANITIZE_PARSER_NOBADW | XTHREADS_SANITIZE_PARSER_MYCODE | XTHREADS_SANITIZE_PARSER_SMILIES | XTHREADS_SANITIZE_PARSER_VIDEOCODE,
		'allowfilter' => 0,
		
		'desc' => '',
		'inputtype' => XTHREADS_INPUT_TEXT,
		'disporder' => 1,
		'tabstop' => 1,
		'hideedit' => 0,
		'formhtml' => '',
		
		'filemagic' => '',
		'fileexts' => '',
		'filemaxsize' => 0,
		'fileimage' => '',
		'fileimgthumbs' => '',
		
	);
	*/
	threadfields_add_edit_handler($tf, false);
}

if($mybb->input['action'] == 'edit')
{
	$plugins->run_hooks('admin_config_threadfields_edit');
	
	$mybb->input['field'] = trim($mybb->input['field']);
	
	$tf = $db->fetch_array($db->simple_select('threadfields', '*', 'field="'.$db->escape_string($mybb->input['field']).'"'));
	if(xthreads_empty($tf['field'])) {
		flash_message($lang->error_invalid_field, 'error');
		admin_redirect(xthreads_admin_url('config', 'threadfields'));
	}
	
	threadfields_add_edit_handler($tf, true);
}

if($mybb->input['action'] == 'inline')
{
	$plugins->run_hooks('admin_config_threadfields_inline');
	$del = $delattach = $order = array();
	$alterkeys = '';
	$query = $db->simple_select('threadfields', 'field,allowfilter,inputtype,disporder');
	while($field = $db->fetch_array($query)) {
		$efn = $db->escape_string($field['field']); //paranoia
		if($mybb->input['threadfields_mark_'.$field['field']]) {
			$del[] = $efn;
			if($field['allowfilter'])
				$alterkeys .= ', DROP KEY `'.$efn.'`';
			if($field['inputtype'] == XTHREADS_INPUT_FILE || $field['inputtype'] == XTHREADS_INPUT_FILE_URL)
				$delattach[] = $efn;
		}
		elseif(!xthreads_empty($mybb->input['threadfields_order_'.$field['field']])) {
			$new_order = (int)($mybb->input['threadfields_order_'.$field['field']]);
			if($field['disporder'] != $new_order) {
				$order[] = $efn;
				$db->update_query('threadfields', array('disporder' => $new_order), 'field="'.$efn.'"');
			}
		}
	}
	$db->free_result($query);
	if(!empty($del)) {
		$plugins->run_hooks('admin_config_threadfields_inline_delete');
		$db->delete_query('threadfields', 'field IN ("'.implode('","', $del).'")');
		$db->write_query('ALTER TABLE `'.$db->table_prefix.'threadfields_data` DROP COLUMN `'.implode('`, DROP COLUMN `', $del).'`'.$alterkeys);
		
		// delete attachments? - might be a little slow...
		if(!empty($delattach)) {
			@ignore_user_abort(true);
			@set_time_limit(0);
			require_once MYBB_ROOT.'inc/xthreads/xt_updatehooks.php';
			xthreads_rm_attach_query('field IN ("'.implode('","', $delattach).'")');
		}
	}
	
	$plugins->run_hooks('admin_config_threadfields_inline_end');
	if(empty($order) && empty($del)) {
		// nothing updated
		flash_message($lang->failed_threadfield_inline, 'error');
		admin_redirect(xthreads_admin_url('config', 'threadfields'));
	} else {
		// Log admin action
		log_admin_action(implode(', ', $order), implode(', ', $del));
		xthreads_buildtfcache();
		flash_message($lang->success_threadfield_inline, 'success');
		admin_redirect(xthreads_admin_url('config', 'threadfields'));
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks('admin_config_threadfields_start');
	
	$page->output_header($lang->custom_threadfields);
	$page->output_nav_tabs($sub_tabs, 'threadfields');

	$form = new Form(xthreads_admin_url('config', 'threadfields&amp;action=inline'), 'post', 'inline');
	
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
				foreach($fids as &$fid) {
					if(!isset($forums[$fid]['name']))
						// forum deleted
						$fname = '<em>'.$lang->sprintf($lang->threadfields_deleted_forum_id, $fid).'</em>';
					else
						$fname = $forums[$fid]['name'];
					$fnames .= ($fnames ? ', ' : '').$fname;
				}
				$celldata = $lang->sprintf($lang->threadfields_for_forums, $fnames);
			}
			$table->construct_cell($celldata, array('colspan' => 6, 'style' => 'padding: 2px;'));
			$table->construct_row();
		}
		$tfname = htmlspecialchars_uni($tf['field']);
		$table->construct_cell('<a href="'.xthreads_admin_url('config', 'threadfields').'&amp;action=edit&amp;field='.urlencode($tf['field']).'"><strong>'.htmlspecialchars_uni($tf['title']).'</strong></a>');
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
				return confirm("<?php echo xt_js_str_escape($lang->threadfields_delete_field_confirm); ?>");
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
	
	$props = xthreads_threadfields_props();
	if($mybb->request_method == 'post')
	{
		foreach($props as $field => &$prop) {
			if($field == 'field') $field = 'newfield';
			// cause you can't "continue" in a switch statement, lol...
			if($field == 'forums' || $field == 'editable_gids' || $field == 'viewable_gids' || $field == 'filemaxsize') continue;
			if($prop['datatype'] == 'string')
				$mybb->input[$field] = trim($mybb->input[$field]);
			else
				$mybb->input[$field] = (int)$mybb->input[$field];
		}
		$mybb->input['textmask'] = str_replace("\x0", '', $mybb->input['textmask']);
		$mybb->input['filemaxsize'] = xthreads_size_to_bytes($mybb->input['filemaxsize']);
		$mybb->input['fileimage_mindim'] = strtolower($mybb->input['fileimage_mindim']);
		$mybb->input['fileimage_maxdim'] = strtolower($mybb->input['fileimage_maxdim']);
		
		$mybb->input['fileimage_mindim'] = strtolower(trim($mybb->input['fileimage_mindim']));
		$mybb->input['fileimage_maxdim'] = strtolower(trim($mybb->input['fileimage_maxdim']));
		if(!xthreads_empty($mybb->input['formatmap'])) {
			$fm = array();
			$fms = str_replace("{\n}", "\r", str_replace("\r", '', $mybb->input['formatmap']));
			foreach(explode("\n", $fms) as $map) {
				$map = str_replace("\r", "\n", $map);
				$p = strpos($map, '{|}');
				if(!$p) continue; // can't be zero index either - blank display format used for that
				$fmkey = substr($map, 0, $p);
				if(isset($fm[$fmkey])) {
					$errors[] = $lang->sprintf($lang->error_dup_formatmap, htmlspecialchars_uni($fmkey));
					unset($fm);
					break;
				}
				$fm[$fmkey] = substr($map, $p+3);
			}
			if(isset($fm))
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
			$mybb->input['editable'] = min_max((int)$mybb->input['editable'], XTHREADS_EDITABLE_ALL, XTHREADS_EDITABLE_NONE);
			$mybb->input['editable_gids'] = '';
		}
		
		if(!xthreads_empty($mybb->input['editable_values'])) {
			$ev = array();
			$evs = str_replace("{\n}", "\r", str_replace("\r", '', $mybb->input['editable_values']));
			foreach(explode("\n", $evs) as $editable_value) {
				$editable_value = str_replace("\r", "\n", $editable_value);
				$p = strpos($editable_value, '{|}');
				if($p === false) continue;
				$evkey = substr($editable_value, 0, $p);
				if(isset($ev[$evkey])) {
					$errors[] = $lang->sprintf($lang->error_dup_editable_value, htmlspecialchars_uni($evkey));
					unset($ev);
					break;
				}
				$ev[$evkey] = array_unique(array_map('intval', explode(',', substr($editable_value, $p+3))));
				// remove '0' element
				if(($zerorm = array_search(0, $ev[$evkey])) !== false)
					unset($ev[$evkey][$zerorm]);
			}
			if(isset($ev))
				$mybb->input['editable_values'] = serialize($ev);
		}
		
		if(is_array($mybb->input['viewable_gids'])) {
			$mybb->input['viewable_gids'] = implode(',', array_unique(array_map('intval', array_map('trim', $mybb->input['viewable_gids']))));
			if(empty($mybb->input['viewable_gids']))
				$mybb->input['viewable_gids'] = '';
		} else {
			$mybb->input['viewable_gids'] = trim($mybb->input['viewable_gids']);
			if($mybb->input['viewable_gids'])
				$mybb->input['viewable_gids'] = implode(',', array_unique(array_map('intval', array_map('trim', explode(',',$mybb->input['viewable_gids'])))));
			if(!$mybb->input['viewable_gids']) $mybb->input['viewable_gids'] = '';
		}
		
		$mybb->input['sanitize'] = min_max((int)$mybb->input['sanitize'], XTHREADS_SANITIZE_HTML, XTHREADS_SANITIZE_NONE);
		//if($mybb->input['sanitize'] == XTHREADS_SANITIZE_PARSER) {
			$parser_opts = array(
				'parser_nl2br' => XTHREADS_SANITIZE_PARSER_NL2BR,
				'parser_nobadw' => XTHREADS_SANITIZE_PARSER_NOBADW,
				'parser_html' => XTHREADS_SANITIZE_PARSER_HTML,
				'parser_mycode' => XTHREADS_SANITIZE_PARSER_MYCODE,
				'parser_mycodeimg' => XTHREADS_SANITIZE_PARSER_MYCODEIMG,
				'parser_mycodevid' => XTHREADS_SANITIZE_PARSER_VIDEOCODE,
				'parser_smilies' => XTHREADS_SANITIZE_PARSER_SMILIES,
			);
			foreach($parser_opts as $opt => $n)
				if($mybb->input[$opt])
					$mybb->input['sanitize'] |= $n;
		//}
		$mybb->input['inputtype'] = min_max((int)$mybb->input['inputtype'], XTHREADS_INPUT_TEXT, XTHREADS_INPUT_CUSTOM);
		
		if(xthreads_empty($mybb->input['title']))		$errors[] = $lang->error_missing_title;
		if(xthreads_empty($mybb->input['newfield']))	$errors[] = $lang->error_missing_field;
		
		if(!xthreads_empty($mybb->input['textmask'])) {
			// test for bad regex
			xthreads_catch_errorhandler();
			@preg_match('~'.str_replace('~', '\\~', $mybb->input['textmask']).'~si', 'testvalue');
			restore_error_handler();
			if(!empty($GLOBALS['_previous_error'])) {
				$errmsg =& $GLOBALS['_previous_error'][1];
				if(substr($errmsg, 0, 12) == 'preg_match()') {
					$p = strpos($errmsg, ':', 12);
					if($p)
						$errmsg = trim(substr($errmsg, $p+1));
					else
						$errmsg = trim(substr($errmsg, 12));
					$errors[] = $lang->sprintf($lang->error_bad_textmask, $errmsg);
				}
			}
		}
		
		switch($mybb->input['inputtype']) {
			case XTHREADS_INPUT_SELECT:
			case XTHREADS_INPUT_RADIO:
			case XTHREADS_INPUT_CHECKBOX:
				$mybb->input['sanitize'] = ($mybb->input['inputtype'] == XTHREADS_INPUT_SELECT ? XTHREADS_SANITIZE_HTML : XTHREADS_SANITIZE_NONE);
				$mybb->input['textmask'] = '';
				
				// must have value defined
				if(xthreads_empty($mybb->input['vallist']))
					$errors[] = $lang->error_require_valllist;
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
			if(xthreads_empty($mybb->input['multival']))
				$errors[] = $lang->error_require_multival_delimiter;
			// force textual datatype
			if($mybb->input['datatype'] !== XTHREADS_DATATYPE_TEXT)
				$mybb->input['datatype'] = XTHREADS_DATATYPE_TEXT;
		} else
			$mybb->input['multival'] = '';
		
		if($mybb->input['use_formhtml']) {
			if(xthreads_empty($mybb->input['formhtml']))
				$errors[] = $lang->error_require_formhtml;
		} else
			$mybb->input['formhtml'] = '';
		
		if($mybb->input['datatype'] !== XTHREADS_DATATYPE_TEXT) {
			// verify value list if applicable
			/* if($mybb->input['inputtype'] == XTHREADS_INPUT_SELECT || $mybb->input['inputtype'] == XTHREADS_INPUT_RADIO) {
				// maybe we won't do this...
			} */
			$mybb->input['datatype'] = min_max($mybb->input['datatype'], XTHREADS_DATATYPE_TEXT, XTHREADS_DATATYPE_FLOAT);
		}
		
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
		
		if(!xthreads_empty($mybb->input['newfield'])) {
			if($mybb->input['newfield'] == 'tid') {
				$errors[] = $lang->error_field_name_tid;
			}
			elseif(strlen($mybb->input['newfield']) > 50) {
				$errors[] = $lang->error_field_name_too_long;
			}
			elseif(!preg_match('~^[a-z0-9_\\-]+$~i', $mybb->input['newfield'])) {
				$errors[] = $lang->error_field_name_invalid;
			}
			elseif(isset($mybb->input['newfield']{2}) && $mybb->input['newfield']{0} == '_' && $mybb->input['newfield']{1} == '_') {
				// don't allow fields starting with "__" (reserved for special use)
				// in hindsight, special uses (eg filters) really should've used something like '~' so we don't need to do this, but it's too late now
				$errors[] = $lang->error_field_name_reserved;
			}
			// field name in use?
			elseif(!$update || $mybb->input['field'] != $mybb->input['newfield']) {
				$ftest = $db->fetch_field($db->simple_select('threadfields', 'field', 'field="'.$db->escape_string($mybb->input['newfield']).'"'), 'field');
				if(!xthreads_empty($ftest)) $errors[] = $lang->error_field_name_in_use;
			}
		}
		
		
		// check for syntax errors in conditionals
		// this is a bit tricky because we need the cache function to build the conditional for checking
		if($update)
			$test_tf = array_merge($oldfield, $mybb->input);
		else
			$test_tf = $mybb->input;
		xthreads_buildtfcache_parseitem($test_tf);
		// test for bad conditional syntax
		foreach(array(
			'defaultval', 'blankval',
			'dispformat', 'dispitemformat',
			'unviewableval',
		) as $condcheck) {
			if($test_tf[$condcheck] && !xthreads_check_evalstr($test_tf[$condcheck])) {
				$tflangkey = 'threadfields_'.$condcheck;
				$errors[] = $lang->sprintf($lang->error_bad_conditional, $lang->$tflangkey);
			}
		}
		if(!xthreads_empty($test_tf['formatmap'])) foreach($test_tf['formatmap'] as &$fm) {
			if($fm && !xthreads_check_evalstr($fm)) {
				$errors[] = $lang->sprintf($lang->error_bad_conditional, $lang->threadfields_formatmap);
				break;
			}
		}

		if(!$errors)
		{
			$new_tf = array();
			foreach(array_keys($props) as $field) {
				if($field == 'field')
					$new_tf[$field] = $db->escape_string($mybb->input['newfield']);
				else
					$new_tf[$field] = $db->escape_string($mybb->input[$field]);
			}
			
			switch($mybb->input['inputtype']) {
				case XTHREADS_INPUT_FILE:
					$fieldtype = xthreads_db_fielddef('int', null, true).' not null default 0';
					break;
				case XTHREADS_INPUT_FILE_URL:
					$fieldtype = 'varchar(255) not null default ""';
					//$using_long_varchar = true;
					break;
				case XTHREADS_INPUT_SELECT:
				case XTHREADS_INPUT_RADIO:
				//case XTHREADS_INPUT_CHECKBOX:
					if(($new_tf['multival'] === '' || $mybb->input['inputtype'] == XTHREADS_INPUT_RADIO) && $new_tf['datatype'] != XTHREADS_DATATYPE_TEXT) {
						$fieldtype = 'varchar(255) not null default ""';
						break;
					}
					
				default:
					switch($new_tf['datatype']) {
						case XTHREADS_DATATYPE_INT:
						case XTHREADS_DATATYPE_UINT:
							$fieldtype = xthreads_db_fielddef('int', null, $new_tf['datatype']==XTHREADS_DATATYPE_UINT).' default null';
							break;
						case XTHREADS_DATATYPE_BIGINT:
						case XTHREADS_DATATYPE_BIGUINT:
							$fieldtype = xthreads_db_fielddef('bigint', null, $new_tf['datatype']==XTHREADS_DATATYPE_BIGUINT).' default null';
							break;
						case XTHREADS_DATATYPE_FLOAT:
							$fieldtype = 'double default null';
							break;
						default:
							if($new_tf['allowfilter'] && $mybb->input['inputtype'] != XTHREADS_INPUT_TEXTAREA) {
								// initially, try 1024 chars
								$fieldtype = 'varchar(1024) not null default ""';
								$using_long_varchar = true;
							} else {
								$fieldtype = 'text not null';
							}
					}
			}
			if($update) {
				$plugins->run_hooks('admin_config_threadfields_edit_commit');
				$db->update_query('threadfields', $new_tf, 'field="'.$db->escape_string($mybb->input['field']).'"');
				
				$alterations = array();
				// TODO: perhaps only run this query if necessary
				//if($mybb->input['field'] != $mybb->input['newfield'])
				$alterfield_base = 'CHANGE `'.$db->escape_string($mybb->input['field']).'` `'.$new_tf['field'].'` ';
				$alterations['field'] = $alterfield_base.$fieldtype;
				
				if($new_tf['allowfilter'] != $oldfield['allowfilter']) {
					if($new_tf['allowfilter'])
						$alterations['addkey'] = 'ADD KEY `'.$new_tf['field'].'` (`'.$new_tf['field'].'`)';
					else
						$alterations['dropkey'] = 'DROP KEY `'.$db->escape_string($mybb->input['field']).'`';
				}
				elseif($new_tf['allowfilter'] && $mybb->input['field'] != $mybb->input['newfield']) {
					// change key name - only way to do this in MySQL appears to be recreating the key...
					$alterations['dropkey'] = 'DROP KEY `'.$db->escape_string($mybb->input['field']).'`';
					$alterations['addkey'] = 'ADD KEY `'.$new_tf['field'].'` (`'.$new_tf['field'].'`)';
				}
				if(!empty($alterations)) {
					$qry_base = 'ALTER TABLE `'.$db->table_prefix.'threadfields_data` ';
					if($using_long_varchar) {
						if(!$db->write_query($qry_base.implode(', ', $alterations), true)) {
							$alterations['field'] = $alterfield_base.str_replace('varchar(1024)', 'varchar(255)', $fieldtype);
							$db->write_query($qry_base.implode(', ', $alterations));
						}
					} else
						$db->write_query($qry_base.implode(', ', $alterations));
					if($mybb->input['field'] != $mybb->input['newfield'] && ($new_tf['inputtype'] == XTHREADS_INPUT_FILE || $new_tf['inputtype'] == XTHREADS_INPUT_FILE_URL)) {
						// need to update xtattachments table too!
						$db->update_query('xtattachments', array('field' => $new_tf['field']), 'field="'.$db->escape_string($mybb->input['field']).'"');
					}
				}
			}
			else {
				$plugins->run_hooks('admin_config_threadfields_add_commit');
				$db->insert_query('threadfields', $new_tf);
				
				$addkey = '';
				if($new_tf['allowfilter'])
					$addkey .= ', ADD KEY (`'.$new_tf['field'].'`)';
				
				$qry_base = 'ALTER TABLE `'.$db->table_prefix.'threadfields_data` ADD COLUMN `'.$new_tf['field'].'` ';
				if($using_long_varchar) {
					if(!$db->write_query($qry_base.$fieldtype.$addkey, true)) {
						$db->write_query($qry_base.str_replace('varchar(1024)', 'varchar(255)', $fieldtype).$addkey);
					}
				}
				else
					$db->write_query($qry_base.$fieldtype.$addkey);
			}
			
			// Log admin action
			log_admin_action($new_tf['field'], htmlspecialchars_uni($mybb->input['title']));

			xthreads_buildtfcache();

			if($update)
				flash_message($lang->success_updated_threadfield, 'success');
			else
				flash_message($lang->success_added_threadfield, 'success');
			admin_redirect(xthreads_admin_url('config', 'threadfields'));
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
		$form = new Form(xthreads_admin_url('config', 'threadfields').'&amp;action=edit&amp;field='.urlencode($tf['field']), 'post');
	else
		$form = new Form(xthreads_admin_url('config', 'threadfields&amp;action=add'), 'post');

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
		//XTHREADS_INPUT_CUSTOM => $lang->threadfields_inputtype_custom,
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
	if(!ini_get('file_uploads'))
		$lang->threadfields_file_name_info .= '<div style="color: red; font-style: italic;">'.$lang->threadfields_file_upload_disabled_warning.'</div>';
	make_form_row('inputtype', 'select_box', $inputtypes, '<div id="inputtype_file_explain" style="font-size: 0.95em; margin-top: 1em;">'.$lang->threadfields_file_name_info.'</div>');
	make_form_row('maxlen', 'text_box');
	make_form_row('fieldwidth', 'text_box');
	make_form_row('fieldheight', 'text_box');
	make_form_row('vallist', 'text_area');
	make_form_row('fileexts', 'text_box');
	
	if(!is_int(2147483648)) // detect 32-bit PHP
		$lang->threadfields_filemaxsize_desc .= $lang->threadfields_filemaxsize_desc_2gbwarn;
	// PHP upload limits
	$upload_max_filesize = @ini_get('upload_max_filesize');
	$post_max_size = @ini_get('post_max_size');
	// TODO: maybe also pull in [ file_uploads, max_file_uploads, max_input_time ] ?
	if($upload_max_filesize || $post_max_size) {
		$lang->threadfields_filemaxsize_desc .= '<br /><br />'.$lang->threadfields_filemaxsize_desc_phplimit;
		if(!$lang->limit_upload_max_filesize)
			$lang->load('config_attachment_types');
		if($upload_max_filesize)
			$lang->threadfields_filemaxsize_desc .= '<br />'.$lang->sprintf($lang->limit_upload_max_filesize, $upload_max_filesize);
		if($post_max_size)
			$lang->threadfields_filemaxsize_desc .= '<br />'.$lang->sprintf($lang->limit_post_max_size, $post_max_size);
	}
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
	$sanitize = $data['sanitize'];
	$data['sanitize'] &= XTHREADS_SANITIZE_MASK;
	make_form_row('sanitize', 'select_box', array(
		XTHREADS_SANITIZE_HTML => $lang->threadfields_sanitize_plain,
		XTHREADS_SANITIZE_HTML_NL => $lang->threadfields_sanitize_plain_nl,
		XTHREADS_SANITIZE_PARSER => $lang->threadfields_sanitize_mycode,
		XTHREADS_SANITIZE_NONE => $lang->threadfields_sanitize_none,
	));
	$parser_opts = array(
		'parser_nl2br' => $sanitize & XTHREADS_SANITIZE_PARSER_NL2BR,
		'parser_nobadw' => $sanitize & XTHREADS_SANITIZE_PARSER_NOBADW,
		'parser_html' => $sanitize & XTHREADS_SANITIZE_PARSER_HTML,
		'parser_mycode' => $sanitize & XTHREADS_SANITIZE_PARSER_MYCODE,
		'parser_mycodeimg' => $sanitize & XTHREADS_SANITIZE_PARSER_MYCODEIMG,
		'parser_mycodevid' => $sanitize & XTHREADS_SANITIZE_PARSER_VIDEOCODE,
		'parser_smilies' => $sanitize & XTHREADS_SANITIZE_PARSER_SMILIES,
	);
	if($mybb->version_code < 1600) unset($parser_opts['parser_mycodevid']);
	$parser_opts_str = '';
	foreach($parser_opts as $opt => $checked) {
		$langstr = 'threadfields_sanitize_'.$opt;
		$parser_opts_str .= '<div style="display: block;">'.$form->generate_check_box($opt, 1, $lang->$langstr, array('checked' => ($checked ? 1:0))).'</div>';
	}
	$form_container->output_row($lang->threadfields_sanitize_parser, $lang->threadfields_sanitize_parser_desc, $parser_opts_str, 'sanitize_parser', array(), array('id' => 'parser_opts'));
	
	make_form_row('disporder', 'text_box');
	make_form_row('tabstop', 'yes_no_radio');
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
	make_form_row('blankval', 'text_area', array('style' => 'font-family: monospace'));
	make_form_row('defaultval', 'text_area', array('style' => 'font-family: monospace'));
	make_form_row('dispformat', 'text_area', array('style' => 'font-family: monospace'));
	make_form_row('datatype', 'select_box', array(
		XTHREADS_DATATYPE_TEXT => $lang->threadfields_datatype_text,
		XTHREADS_DATATYPE_INT => $lang->threadfields_datatype_int,
		XTHREADS_DATATYPE_UINT => $lang->threadfields_datatype_uint,
		XTHREADS_DATATYPE_BIGINT => $lang->threadfields_datatype_bigint,
		XTHREADS_DATATYPE_BIGUINT => $lang->threadfields_datatype_biguint,
		XTHREADS_DATATYPE_FLOAT => $lang->threadfields_datatype_float,
	));
	$data['multival_enable'] = ($data['multival'] !== '' ? 1:0);
	make_form_row('multival_enable', 'yes_no_radio');
	unset($data['multival_enable']);
	$lang->threadfields_multival .= ' <em>*</em>';
	make_form_row('multival', 'text_box');
	$lang->threadfields_multival = substr($lang->threadfields_multival, 0, -11);
	make_form_row('dispitemformat', 'text_area', array('style' => 'font-family: monospace'));
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
			$fmtxt .= str_replace("\n", "{\n}", $k.'{|}'.$v)."\n";
		$data['formatmap'] =& $fmtxt;
	}
	make_form_row('formatmap', 'text_area', array('style' => 'font-family: monospace'));
	if(!is_array($data['editable_values'])) {
		$ev = @unserialize($data['editable_values']);
		if(is_array($ev))
			$data['editable_values'] =& $ev;
	}
	if(is_array($data['editable_values'])) {
		$evtxt = '';
		foreach($data['editable_values'] as $k => &$v)
			// don't need to htmlspecialchar - it'll be done for us
			$evtxt .= str_replace("\n", "{\n}", $k).'{|}'.implode(',', $v)."\n";
		$data['editable_values'] =& $evtxt;
	}
	make_form_row('editable_values', 'text_area', array('style' => 'font-family: monospace'));
	if($data['viewable_gids'] && !is_array($data['viewable_gids']))
		$data['viewable_gids'] = array_map('intval',array_map('trim',explode(',', $data['viewable_gids'])));
	$form_container->output_row($lang->threadfields_viewable_gids, $lang->threadfields_viewable_gids_desc, xt_generate_group_select('viewable_gids[]', $data['viewable_gids'], array('multiple' => true, 'size' => 5, 'id' => 'viewable_gids')), 'viewable_gids', array(), array('id' => 'row_viewable_gids'));
	make_form_row('unviewableval', 'text_area', array('style' => 'font-family: monospace'));
	make_form_row('hideedit', 'yes_no_radio');
	$data['use_formhtml'] = ($data['formhtml'] !== '' ? 1:0);
	make_form_row('use_formhtml', 'yes_no_radio');
	unset($data['use_formhtml']);
	$lang->threadfields_formhtml .= ' <em>*</em>';
	make_form_row('formhtml', 'text_area', array('style' => 'font-family: monospace'));
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
		var checkboxIn = (si == <?php echo XTHREADS_INPUT_CHECKBOX; ?>);
		var fileIn = (si == <?php echo XTHREADS_INPUT_FILE; ?> || si == <?php echo XTHREADS_INPUT_FILE_URL; ?>);
		e = checkboxIn; // forced
		
		var datatypeText = ($('datatype').options[$('datatype').selectedIndex].value == "<?php echo XTHREADS_DATATYPE_TEXT; ?>");
		xt_visi('row_multival_enable', checkboxIn || ((
			(si != <?php echo XTHREADS_INPUT_RADIO; ?> && !fileIn)
			&& datatypeText)
		));
		
		if(!e) e = ($('multival_enable_yes').checked && $('row_multival_enable').style.display != 'none');
		xt_visi('row_multival', e);
		xt_visi('row_dispitemformat', e);
		datatypeVisible = (!e && !checkboxIn && !fileIn);
		xt_visi('row_datatype', datatypeVisible);
		
		// hide some sanitise options (if browser supports it)
		var sanitizeOptShow = ((datatypeVisible && !datatypeText) ? 'none' : '');
		for(i in $('sanitize').options) {
			var optItem = $('sanitize').options[i];
			if(!optItem) continue; // fix IE6 bug
			if(optItem.value == "<?php echo XTHREADS_SANITIZE_HTML_NL; ?>" || optItem.value == "<?php echo XTHREADS_SANITIZE_NONE; ?>") {
				// our target
				if(sanitizeOptShow == 'none' && $('sanitize').selectedIndex == i)
					$('sanitize').selectedIndex = 0;
				optItem.style.display = sanitizeOptShow;
			}
		}
	}
	$('multival_enable_yes').onclick = xt_multival_enable;
	$('multival_enable_no').onclick = xt_multival_enable;
	
	($('use_formhtml_yes').onclick = $('use_formhtml_no').onclick = xt_use_formhtml = function() {
		xt_visi('row_formhtml', $('use_formhtml_yes').checked);
		xt_visi('formhtml_desc_js', true);
	})();
	
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
		xt_visi('row_editable_values', !fileIn);
		xt_visi('row_defaultval', !pureFileIn);
		
		xt_visi('row_textmask', textIn || si == <?php echo XTHREADS_INPUT_CUSTOM; ?>);
		xt_visi('row_maxlen', textIn);
		xt_visi('row_fieldwidth', textIn || fileIn || selectBoxIn);
		xt_visi('row_fieldheight', textAreaIn || selectBoxIn);
		
		xt_visi('row_vallist', selectIn);
		xt_visi('row_formhtml', si == <?php echo XTHREADS_INPUT_CUSTOM; ?>);
		
		//xt_visi('row_datatype', !checkboxIn && !fileIn);
		//xt_visi('row_multival_enable', !checkboxIn && !radioIn && !fileIn);
		xt_multival_enable();
		
		xt_visi('row_filemagic', pureFileIn);
		xt_visi('row_fileexts', pureFileIn);
		xt_visi('row_filemaxsize', pureFileIn);
		xt_visi('row_filereqimg', pureFileIn);
		xt_filereqimg();
		
		dispfmt_obj = $('dispformat');
		fileVal = "<a href=\"{URL}\">{FILENAME}</a>";
		if(pureFileIn) {
			if(dispfmt_obj.value == "{VALUE}")
				dispfmt_obj.value = fileVal;
		} else {
			if(dispfmt_obj.value == fileVal)
				dispfmt_obj.value = "{VALUE}";
		}
		
		if(textAreaIn) {
			if($('sanitize').options[$('sanitize').selectedIndex].value == "<?php echo XTHREADS_SANITIZE_HTML; ?>")
				$('sanitize').selectedIndex++;
		} else if(textIn) {
			if($('sanitize').options[$('sanitize').selectedIndex].value == "<?php echo XTHREADS_SANITIZE_HTML_NL; ?>")
				$('sanitize').selectedIndex--;
		}
		
		var setFormhtml = true;
		if($('use_formhtml_yes').checked) {
			setFormhtml = confirm("<?php echo xt_js_str_escape($lang->threadfields_formhtml_js_reset_warning); ?>");
			if(setFormhtml) {
				$('use_formhtml_no').checked = true;
			}
			xt_use_formhtml();
		}
		switch(si) {
			<?php foreach(array(XTHREADS_INPUT_TEXTAREA,XTHREADS_INPUT_SELECT,XTHREADS_INPUT_CHECKBOX,XTHREADS_INPUT_RADIO,XTHREADS_INPUT_FILE,XTHREADS_INPUT_TEXT) as $inputtype) {
				$formhtml_info = xthreads_default_threadfields_formhtml($inputtype);
				$formhtml_desc = '';
				foreach($formhtml_info[1] as $fhvar) {
					$langvar = 'threadfields_formhtml_desc_'.strtolower($fhvar);
					$formhtml_desc .= '<li><code>{'.$fhvar.'}</code>: '.$lang->$langvar.'</li>';
				}
				echo '
				case '.$inputtype.':
					if(setFormhtml) $("formhtml").value = "'.xt_js_str_escape($formhtml_info[0]).'";
					$("formhtml_desc_ul_js").innerHTML = "'.xt_js_str_escape($formhtml_desc).'";
					break;';
			} ?>
		}
	}).apply($('inputtype'));
	
	($('datatype').onchange = function() {
		//var isText = this.options[this.selectedIndex].value == "<?php echo XTHREADS_DATATYPE_TEXT; ?>";
		//xt_visi('row_multival_enable', isText);
		xt_multival_enable();
	}).apply($('datatype'));
	
	($('editable').onchange = function() {
		xt_visi('row_editable_gids', this.options[this.selectedIndex].value == "99");
	}).apply($('editable'));
	
	($('viewable_gids').onchange = function() {
		var e=false;
		var o=$('viewable_gids').options;
		for(i=0; i<o.length; i++)
			if(e = o[i].selected) // no, I do mean =, not ==
				break;
		xt_visi('row_unviewableval', e);
	}).apply($('viewable_gids'));
	
	<?php
		$textmask_types = array(
			'anything' => '^.*$',
			'digit' => '^\\d+$',
			'alphadigit' => '^[a-z0-9]+$',
			'number' => '^(-?)([0-9]*)(?:\\.(\\d*))?(?:e([+-]?\\d*))?$',
			'date' => '^(0?[1-9]|[12]\\d|3[01])/(0?[1-9]|1[012])/((?:19|20)\\d\\d)$',
			'date_us' => '^(0?[1-9]|1[012])/(0?[1-9]|[12]\\d|3[01])/((?:19|20)\\d\\d)$',
			'uri' => '^([a-z0-9]+)\\:(.+)$',
			'url' => '^([a-z0-9]+)\\://([a-z0-9.\\-_]+)(/[^\\r\\n"<>&]*)?$',
			'httpurl' => '^(https?)\\://([a-z0-9.\\-_]+)(/[^\\r\\n"<>&]*)?$',
			'email' => '^([a-z0-9_.\\-]+)@([a-z0-9_.\\-]+)$',
			'css' => '^[a-z0-9_\\- ]+$',
			'color' => '^[a-z\\-]+|#?[0-9a-f]{6}$'
		);
	?>
	$('textmask').parentNode.innerHTML =
			'<select name="textmask_select" id="textmask_select">' +
<?php
	foreach($textmask_types as $type => &$mask) {
		$langvar = 'threadfields_textmask_'.$type;
		echo '			\'<option value="', $type,'">', $lang->$langvar, '</option>\' +
';
	}
?>
			'<option value="custom">'+<?php echo "'",$lang->threadfields_textmask_custom,"'"; ?>+'</option>' +
			'</select> ' + $('textmask').parentNode.innerHTML + '<div id="textmask_select_descriptions" style="font-size: smaller; padding-top: 0.5em;">' +
<?php
	foreach($textmask_types as $type => &$mask) {
		$langvar = 'threadfields_textmask_'.$type.'_desc';
		if(property_exists($lang, $langvar))
			echo '			\'<div id="textmask_selector_desc_', $type, '" style="display: none;">', xt_js_str_escape($lang->$langvar), '</div>\' +
';
	}
?>
			'</div>';
	var textmaskMapping = {
<?php
	$comma = '';
	foreach($textmask_types as $type => &$mask) {
		echo $comma, '		', $type, ': "', xt_js_str_escape($mask), '"';
		if(!$comma) $comma = ',
';
	}
?>

	};
	// determine which option to be selected by default
	(function() {
		// we can only index by number, and as we're a little lazy, create a name -> index map
		var textmaskSelectOpts = $('textmask_select').options;
		var textmaskSelectMap = {};
		for(i=0; i<textmaskSelectOpts.length; i++) {
			textmaskSelectMap[textmaskSelectOpts[i].value] = i;
		}
		
		var mask = $('textmask').value;
		for(var maskName in textmaskMapping) {
			if(mask == textmaskMapping[maskName]) {
				$('textmask_select').selectedIndex = textmaskSelectMap[maskName];
				textmaskSelectUpdated();
				return;
			}
		}
		$('textmask_select').selectedIndex = textmaskSelectMap["custom"];
	})();
	$('textmask_select').onchange = function() {
		var maskName = this.options[this.selectedIndex].value;
		if(textmaskMapping[maskName])
			$('textmask').value = textmaskMapping[maskName];
		textmaskSelectUpdated();
	};
	$('textmask_select').onkeypress = $('textmask_select').onkeydown = $('textmask_select').onkeyup = function(e) {
		$('textmask_select').onchange();
		return true;
	};
	function textmaskSelectUpdated() {
		var maskName = $('textmask_select').options[$('textmask_select').selectedIndex].value;
		var d = (maskName != "custom");
		$('textmask').readOnly = d;
		$('textmask').tabIndex = (d?'-1':''); // note, this is non-standard
		$('textmask').style.background = (d ? "#F0F0F0":"");
		$('textmask').style.color = (d ? "#808080":"");
		
		var o = $('textmask_select_descriptions').childNodes;
		for(i=0; i<o.length; i++) {
			if(o[i].id == "textmask_selector_desc_"+maskName)
				o[i].style.display = "";
			else
				o[i].style.display = "none";
		}
	}
	$('textmask').onfocus = function() {
		if(this.readOnly)
			$('textmask_select').focus();
	};
	
//-->
</script>
<script type="text/javascript" src="jscripts/xtofedit.js?xtver=<?php echo XTHREADS_VERSION; ?>"></script>
<script type="text/javascript">
<!--
xtOFEditorLang.confirmFormSubmit = "<?php echo $lang->xthreads_js_confirm_form_submit; ?>";
xtOFEditorLang.windowTitle = "<?php echo $lang->xthreads_js_edit_value; ?>";
xtOFEditorLang.saveButton = "<?php echo $lang->xthreads_js_save_changes; ?>";
xtOFEditorLang.closeSaveChanges = "<?php echo $lang->xthreads_js_close_save_changes; ?>";

var fmtMapEditor = new xtOFEditor();
fmtMapEditor.src = $('formatmap');
fmtMapEditor.loadFunc = function(s) {
	var a = s.replace(/\r/g, "").replace(/\{\n\}/g, "\r").split("\n");
	var data = [];
	for(var i=0; i<a.length; i++) {
		a[i] = a[i].replace(/\r/g, "\n");
		var p = a[i].indexOf("{|}");
		if(p < 0) continue;
		data.push([ a[i].substring(0, p), a[i].substring(p+3) ]);
	}
	return data;
};
fmtMapEditor.saveFunc = function(a) {
	var ret = "";
	for(var i=0; i<a.length; i++) {
		ret += a[i].join("{|}").replace(/\n/g, "{\n}") + "\n";
	}
	return ret;
};
fmtMapEditor.fields = [
	{title: "<?php echo $lang->xthreads_js_formatmap_from; ?>", width: '45%', elemFunc: fmtMapEditor.textAreaFunc},
	{title: "<?php echo $lang->xthreads_js_formatmap_to; ?>", width: '55%', elemFunc: fmtMapEditor.textAreaFunc}
];

fmtMapEditor.copyStyles=true;
fmtMapEditor.init();

var editValEditor = new xtOFEditor();
editValEditor.src = $('editable_values');
editValEditor.loadFunc = function(s) {
	var a = s.replace(/\r/g, "").replace(/\{\n\}/g, "\r").split("\n");
	var data = [];
	for(var i=0; i<a.length; i++) {
		a[i] = a[i].replace(/\r/g, "\n");
		var p = a[i].indexOf("{|}");
		if(p < 0) continue;
		data.push([ a[i].substring(0, p), a[i].substring(p+3).split(",") ]);
	}
	return data;
};
editValEditor.saveFunc = function(a) {
	var ret = "";
	for(var i=0; i<a.length; i++) {
		ret += a[i][0].replace(/\n/g, "{\n}") + "{|}" + a[i][1].join(",") + "\n";
	}
	return ret;
};
editValEditor.fields = [
	{title: "<?php echo $lang->xthreads_js_formatmap_from; ?>", width: '50%', elemFunc: editValEditor.textAreaFunc},
	{title: "<?php echo $lang->xthreads_js_editable_values_groups; ?>", width: '50%', elemFunc: function(c) {
		var o = appendNewChild(c, "select");
		o.multiple = true;
		o.size = 3;
		o.style.width = '100%';
		o.innerHTML = '<?php
			foreach($GLOBALS['cache']->read('usergroups') as $group) {
				echo '<option value="'.$group['gid'].'">'.xt_js_str_escape(htmlspecialchars_uni(strip_tags($group['title']))).'</option>';
			}
		?>';
		return o;
	}}
];

editValEditor.copyStyles=true;
editValEditor.init();

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
	else {
		// reuse our handy $opts array :P
		$opts['id'] = $n;
		$html = $form->$it($n, $data[$n], $opts);
	}
	
	$form_container->output_row($lang->$lang_n, $lang->$lang_d, $html.$html_append, $n, array(), array('id' => 'row_'.$n));

}

// escape text to put into a Javascript string; only needs to handle common stuffs
function xt_js_str_escape($s) {
	return strtr($s, array('\\'=>'\\\\','"'=>'\\"','\''=>'\\\'',
		"\n"=>'\\n',"\r"=>'\\r',"\t"=>'\\t','<'=>'\\x3C','>'=>'\\x3E'));
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
