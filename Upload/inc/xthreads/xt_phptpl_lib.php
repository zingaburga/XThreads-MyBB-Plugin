<?php

// various functions from Template Conditionals and PHP in Templates plugin

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

function xthreads_phptpl_parsetpl(&$ourtpl, $fields=array(), $evalvarname=null)
{
	$GLOBALS['__phptpl_if'] = array();
	$find = array(
		'#\<((?:else)?if\s+(.*?)\s+then|else\s*/?|/if)\>#sie', // note that this relies on preg_replace working in a forward order
		'#\<func (htmlspecialchars|htmlspecialchars_uni|intval|floatval|urlencode|rawurlencode|addslashes|stripslashes|trim|crc32|ltrim|rtrim|chop|md5|nl2br|sha1|strrev|strtoupper|strtolower|my_strtoupper|my_strtolower|alt_trow|get_friendly_size|filesize|strlen|my_strlen|my_wordwrap|random_str|unicode_chr|bin2hex|str_rot13|str_shuffle|strip_tags|ucfirst|ucwords|basename|dirname|unhtmlentities)\>#i',
		'#\</func\>#i',
		//'#\<template\s+([a-z0-9_ \-+!(),.]+)(\s*/)?\>#i',
		'#\<\?=(.*?)\?\>#sie',
		'#\<setvar\s+([a-z0-9_\-+!(),.]+)\>(.*?)\</setvar\>#ie',
	);
	$repl = array(
		'xthreads_phptpl_if(\'$1\', \'$2\', $fields)',
		'".$1("',
		'")."',
		//'".eval("return \"".$GLOBALS[\'templates\']->get(\'$1\')."\";")."',
		'\'".strval(\'._xthreads_phptpl_expr_parse(\'$1\', $fields).\')."\'',
		'\'".(($GLOBALS["tplvars"]["$1"] = (\'._xthreads_phptpl_expr_parse(\'$2\', $fields).\'))?"":"")."\'',
	);
	
	if($evalvarname) {
		$find[] = '#\<while\s+(.*?)\s+do\>#sie';
		$find[] = '#\</while\>#i';
		$repl[] = '\'"; while(\'._xthreads_phptpl_expr_parse(\'$1\', $fields).\') { $'.$evalvarname.'.="\'';
		$repl[] = '"; } $'.$evalvarname.'.="';
		
		$find[] = '#\<foreach\s+(.*?)\s+do\>#sie';
		$find[] = '#\</foreach\>#i';
		$repl[] = '\'"; foreach(\'._xthreads_phptpl_expr_parse(\'$1\', $fields).\' as $__key => $__value) { $'.$evalvarname.'.="\'';
		$repl[] = '"; } $'.$evalvarname.'.="';
		
		$find[] = '#\<repeat\s+(.*?)\s+do\>#sie';
		$find[] = '#\</repeat\>#i';
		$repl[] = '\'"; for($__iter=0; $__iter < \'._xthreads_phptpl_expr_parse(\'$1\', $fields).\'; ++$__iter) { $'.$evalvarname.'.="\'';
		$repl[] = '"; } $'.$evalvarname.'.="';
	}
	
	if(xthreads_allow_php()) {
		$find[] = '#\<\?(?:php|\s).+?(\?\>)#se';
		$repl[] = 'xthreads_phptpl_evalphp(\'$0\', \'$1\', $fields)';
	}
	$ourtpl = preg_replace($find, $repl, $ourtpl);
}

function xthreads_phptpl_if($s, $e, $fields)
{
	if($s[0] == '/') {
		// end if tag
		$last = array_pop($GLOBALS['__phptpl_if']);
		$suf = str_repeat(')', (int)substr($last, 1));
		if($last[0] == 'i')
			$suf = ':""'.$suf;
		return '"'.$suf.')."';
	} else {
		$s = strtolower(substr($s, 0, strpos($s, ' ')));
		if($s == 'if') {
			$GLOBALS['__phptpl_if'][] = 'i0';
			return '".(('._xthreads_phptpl_expr_parse($e, $fields).')?"';
		} elseif($s == 'elseif') {
			$last = array_pop($GLOBALS['__phptpl_if']);
			$last = 'i'.((int)substr($last, 1) + 1);
			$GLOBALS['__phptpl_if'][] = $last;
			return '":(('._xthreads_phptpl_expr_parse($e, $fields).')?"';
		} else {
			$last = array_pop($GLOBALS['__phptpl_if']);
			$last[0] = 'e';
			$GLOBALS['__phptpl_if'][] = $last;
			return '":"';
		}
	}
}

function xthreads_phptpl_expr_parse_fixstr_simple($match) {
	return preg_replace('~\$GLOBALS\\[\\s*\'([a-zA-Z_][a-zA-Z_0-9]*)\'\\s*\\]~', '$GLOBALS[$1]', $match[0]);
}
function xthreads_phptpl_expr_parse_fixstr_complex($match) {
	return preg_replace('~(?<!\{)\$GLOBALS\\[\'([a-zA-Z_][a-zA-Z_0-9]*)\'\\](((?:-\\>|\\:\\:)[a-zA-Z_][a-zA-Z_0-9]*|\\[\s*([\'"])?[ a-zA-Z_ 0-9]+\\4\s*\\])*)~', '{$0}', $match[0]);
}

function _xthreads_phptpl_expr_parse($str, $fields=array()) {
	// unescapes the slashes added by xthreads_sanitize_eval, plus addslashes() (double quote only) during preg_replace()
	$str = strtr($str, array('\\$' => '$', '\\\\"' => '"', '\\\\' => '\\'));
	
	return xthreads_phptpl_expr_parse($str, $fields);
}

function xthreads_phptpl_expr_parse($str, $fields=array())
{
	// remove all single quote strings - they mess up all our plans...
	$strpreg = '~\'(|\\\\\\\\|.*?([^\\\\]|[^\\\\](\\\\\\\\)+))\'~s';
	if(preg_match_all($strpreg, $str, $squotstr)) {
		$token = '\'__PHPTPL_PLACEHOLDER_'.md5(mt_rand()).'__\'';
		$str = preg_replace($strpreg, $token, $str);
		$squotstr = $squotstr[0];
	}
	
	// globalise all variables; conveniently will filter out stuff like {VALUE$1}
	$str = preg_replace('~\$([a-zA-Z_][a-zA-Z_0-9]*)~', '$GLOBALS[\'$1\']', $str);
	// won't pick up double variable syntax, eg $$var, or complex variable syntax, eg ${$var}
	
	// fix variables in double-quote and heredoc strings
	$strpreg = '~(")(|\\\\\\\\|.*?([^\\\\]|[^\\\\](\\\\\\\\)+))\\1~s';
	if(xthreads_allow_php())
		$str = preg_replace_callback(array($strpreg, "~\<\<\<([a-zA-Z_][a-zA-Z_0-9]*)\r?\n.*?\r?\n\\1;?\r?\n~s"), 'xthreads_phptpl_expr_parse_fixstr_complex', $str);
	else
		$str = preg_replace_callback($strpreg, 'xthreads_phptpl_expr_parse_fixstr_simple', $str);
	
	// we need to parse {VALUE} tokens here, as they need to be parsed a bit differently, and so that they're checked for safe expressions
	$do_value_repl = false;
	$tr = array();
	foreach($fields as &$f) {
		$tr['{'.$f.'}'] = '"".$vars[\''.$f.'\'].""';
		
		if($f == 'RAWVALUE') $do_value_repl = true;
	}
	if($do_value_repl) $str = preg_replace('~\{((?:RAW)?VALUE)\\\\?\$(\d+)\}~', '"".$vars[\'$1$\'][$2].""', $str);
	$str = strtr($str, $tr);
	
	if(!empty($squotstr))
		$str = xthreads_our_str_replace($token, $squotstr, $str);
	
	if(xthreads_allow_php() || xthreads_phptpl_is_safe_expression($str))
		return $str;
	else
		return 'false';
}

// also disables heredoc + array/object typecasting + braces in double-quoted strings
function xthreads_phptpl_is_safe_expression($s)
{
	
	// remove all strings
	$string_preg = '~([\'"])(|\\\\\\\\|.*?([^\\\\]|[^\\\\](\\\\\\\\)+))\\1~s';
	preg_match_all($string_preg, $s, $strings, PREG_SET_ORDER);
	
	// check double-quote strings
	foreach($strings as &$strdef) {
		if($strdef[1] == '"') {
			// check $strdef[2]
			// we'll only do a simple check
			if(strpos($strdef[2], '{') !== false) return false;
		}
	}
	
	// remove safe "equal" expressions and closed comments
	// use '^' character as substitution to try to prevent possible 'some==badfunc()' type exploits
	$check = strtr(preg_replace(array($string_preg, '~/\\*.*?\\*/~s'), ' ', $s), array('>=' => '^', '<=' => '^', '=>' => '^', '===' => '^', '!==' => '^', '==' => '^', '!=' => '^'));
	
	// block certain characters + operators
	if(preg_match('~([+\-/]{2}|[`#="\']|/\*|\<{3}|\?\>|\(array\)|\(object\))~i', $check)) return false;
	// blocking hanging quotes will actually also block an exploit
	// eg $a = "".strval(1).").".whatever.(".strval(1).")."";
	
	// block exit/die, include/require + constants
	if(preg_match('~(?<![a-z0-9_$])(?:exit|die|eval|include|include_once|require|require_once|__file__|__line__|__function__|__class__|__method__|php_version|php_os|php_sapi|default_include_path|pear_install_dir|pear_extension_dir|php_extension_dir|php_prefix|php_bindir|php_libdir|php_datadir|php_sysconfdir|php_localstatedir|php_config_file_path|php_config_file_scan_dir|php_shlib_suffix|mybb_root)(?![a-z0-9_$])~i', $check)) return false;
	
	
	// check functions (implicitly blocks variable functions and method calls, as well as array index calls and $a{0}() type calls)
	preg_match_all('~((\$|-\>|\:\:)?[a-zA-Z0-9_]+[\]}]?)\(~', $check, $matches);
	$allowed_funcs = xthreads_phptpl_get_allowed_funcs();
	foreach($matches[1] as &$func) {
		if(!isset($allowed_funcs[$func])) return false;
	}
	
	return true;
}

function &xthreads_phptpl_get_allowed_funcs()
{
	static $allowed_funcs = null;
	if(!isset($allowed_funcs)) {
		$allowed_funcs = array_flip(explode("\n", str_replace("\r", '', @file_get_contents(MYBB_ROOT.'inc/xthreads/phptpl_allowed_funcs.txt'))));
	}
	return $allowed_funcs;
}

function xthreads_phptpl_evalphp($str, $end, $fields=array())
{
	return '".eval(\'ob_start(); ?>'
		.strtr(_xthreads_phptpl_expr_parse($str, $fields), array('\'' => '\\\'', '\\' => '\\\\'))
		.($end?'':'?>').'<?php return ob_get_clean();\')."';
}

// replaces a token with an array of replacements
// copied from Syntax Highlighter plugin
function xthreads_our_str_replace($find, &$replacements, $subject)
{
	$l = strlen($find);
	// allocate some memory
	$new_subject = str_repeat(' ', strlen($subject));
	$new_subject = '';
	foreach($replacements as $r)
	{
		if(($pos = strpos($subject, $find)) === false) break;
		$new_subject .= substr($subject, 0, $pos).$r;
		$subject = substr($subject, $pos+$l);
	}
	$new_subject .= $subject;
	return $new_subject;
}

function xthreads_allow_php() {
	if(defined('XTHREADS_ALLOW_PHP_THREADFIELDS_ACTIVATION'))
		return XTHREADS_ALLOW_PHP_THREADFIELDS_ACTIVATION;
	return (XTHREADS_ALLOW_PHP_THREADFIELDS==1 || (XTHREADS_ALLOW_PHP_THREADFIELDS==2 && function_exists('phptpl_evalphp')));
}






// sanitises string $s so that we can directly eval it during "run-time" rather than performing sanitisation there
function xthreads_sanitize_eval(&$s, $fields=array(), $evalvarname=null) {
	if(xthreads_empty($s)) {
		$s = '';
		return;
	}
	// the following won't work properly with array indexes which have non-alphanumeric and underscore chars; also, it won't do ${var} syntax
	// also, damn PHP's magic quotes for preg_replace - but it does assist with backslash fun!!!
	$s = preg_replace(
		array(
			'~\\{\\\\\\$([a-zA-Z_][a-zA-Z_0-9]*)(((?:-\\>|\\:\\:)[a-zA-Z_][a-zA-Z_0-9]*|\\[\s*(\'|\\\\"|)[ a-zA-Z_ 0-9]+\\4\s*\\])*)\\}~e',
			'~\{\\\\\$forumurl\\\\\$\}~i',
			'~\{\\\\\$forumurl\?\}~i',
			'~\{\\\\\$threadurl\\\\\$\}~i',
			'~\{\\\\\$threadurl\?\}~i'
		), array(
			'\'{$GLOBALS[\\\'$1\\\']\'.strtr(\'$2\', array(\'\\\\\\\\\\\'\' => \'\\\'\', \'\\\\\\\\\\\\\\\\"\' => \'\\\'\')).\'}\'', // rewrite double-quote to single quotes, cos it's faster
			'{$GLOBALS[\'forumurl\']}',
			'{$GLOBALS[\'forumurl_q\']}',
			'{$GLOBALS[\'threadurl\']}',
			'{$GLOBALS[\'threadurl_q\']}',
		), strtr($s, array('\\' => '\\\\', '$' => '\\$', '"' => '\\"'))
	);
	
	// replace conditionals
	xthreads_phptpl_parsetpl($s, $fields, $evalvarname);
	
	// replace value tokens at the end
	if(!empty($fields)) {
		$tr = array();
		$do_value_repl = false;
		foreach($fields as &$f) {
			$tr['{'.$f.'}'] = '{$vars[\''.$f.'\']}';
			
			if($f == 'RAWVALUE') $do_value_repl = true;
		}
		if($do_value_repl) $s = preg_replace('~\{((?:RAW)?VALUE)\\\\?\$(\d+)\}~', '{$vars[\'$1$\'][$2]}', $s);
		$s = strtr($s, $tr);
	}
}

