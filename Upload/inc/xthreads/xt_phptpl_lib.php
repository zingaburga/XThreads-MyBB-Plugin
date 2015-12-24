<?php

// various functions from Template Conditionals and PHP in Templates plugin

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

if(function_exists('preg_replace_callback_array')) {
	// PHP >= 7
	// note, this will break parsers from PHP < 5.3
	function xthreads_phptpl_parsetpl(&$ourtpl, $fields=array(), $evalvarname=null)
	{
		$GLOBALS['__phptpl_if'] = array();
		$repl = array(
			'#\<((?:else)?if\s+(.*?)\s+then|else\s*/?|/if)\>#si' => function($m) use($fields) {
				return xthreads_phptpl_if($m[1], _xthreads_phptpl_expr_parse2($m[2], $fields));
			},
			'#\<func (htmlspecialchars|htmlspecialchars_uni|intval|floatval|urlencode|rawurlencode|addslashes|stripslashes|trim|crc32|ltrim|rtrim|chop|md5|nl2br|sha1|strrev|strtoupper|strtolower|my_strtoupper|my_strtolower|alt_trow|get_friendly_size|filesize|strlen|my_strlen|my_wordwrap|random_str|unicode_chr|bin2hex|str_rot13|str_shuffle|strip_tags|ucfirst|ucwords|basename|dirname|unhtmlentities)\>#i' => function($m) {
				return '".'.$m[1].'("';
			},
			'#\</func\>#i' => function() {
				return '")."';
			},
			//'#\<template\s+([a-z0-9_ \-+!(),.]+)(\s*/)?\>#i' => function($m) {return $GLOBALS['templates']->get($m[1]);},
			'#\<\?=(.*?)\?\>#s' => function($m) use($fields) {
				return '".strval('._xthreads_phptpl_expr_parse2($m[1], $fields).')."';
			},
			'#\<setvar\s+([a-z0-9_\-+!(),.]+)\>(.*?)\</setvar\>#i' => function($m) use($fields) {
				return '".(($GLOBALS["tplvars"][\''.$m[1].'\'] = ('._xthreads_phptpl_expr_parse2($m[2], $fields).'))?"":"")."';
			},
		);
		
		if($evalvarname) {
			$repl['#\<while\s+(.*?)\s+do\>#si'] = function($m) use($fields, $evalvarname) {
				return '"; while('._xthreads_phptpl_expr_parse2($m[1], $fields).') { $'.$evalvarname.'.="';
			};
			$repl['#\<foreach\s+(.*?)\s+do\>#si'] = function($m) use($fields, $evalvarname) {
				return '"; foreach('._xthreads_phptpl_expr_parse2($m[1], $fields).' as $__key => $__value) { $'.$evalvarname.'.="';
			};
			$repl['#\<repeat\s+(.*?)\s+do\>#si'] = function($m) use($fields, $evalvarname) {
				return '"; for($__iter=0; $__iter < '._xthreads_phptpl_expr_parse2($m[1], $fields).'; ++$__iter) { $'.$evalvarname.'.="';
			};
			$repl['#\</(while|foreach|repeat)\>#i'] = function($m) use($evalvarname) {
				return '"; } $'.$evalvarname.'.="';
			};
		}
		
		if(xthreads_allow_php()) {
			$repl['#\<\?(?:php|\s).+?(\?\>)#s'] = function($m) use($fields) {
				return xthreads_phptpl_evalphp(_xthreads_phptpl_expr_parse2($m[0], $fields), $m[1]);
			};
		}
		$ourtpl = preg_replace_callback_array($repl, $ourtpl);
	}
} else {
	function xthreads_phptpl_parsetpl(&$ourtpl, $fields=array(), $evalvarname=null)
	{
		$GLOBALS['__phptpl_if'] = array();
		if(defined('HHVM_VERSION'))
			$fields_var = var_export($fields, true);
		else
			$fields_var = '$fields';
		$find = array(
			'#\<((?:else)?if\s+(.*?)\s+then|else\s*/?|/if)\>#sie', // note that this relies on preg_replace working in a forward order
			'#\<func (htmlspecialchars|htmlspecialchars_uni|intval|floatval|urlencode|rawurlencode|addslashes|stripslashes|trim|crc32|ltrim|rtrim|chop|md5|nl2br|sha1|strrev|strtoupper|strtolower|my_strtoupper|my_strtolower|alt_trow|get_friendly_size|filesize|strlen|my_strlen|my_wordwrap|random_str|unicode_chr|bin2hex|str_rot13|str_shuffle|strip_tags|ucfirst|ucwords|basename|dirname|unhtmlentities)\>#i',
			'#\</func\>#i',
			//'#\<template\s+([a-z0-9_ \-+!(),.]+)(\s*/)?\>#i',
			'#\<\?=(.*?)\?\>#se',
			'#\<setvar\s+([a-z0-9_\-+!(),.]+)\>(.*?)\</setvar\>#ie',
		);
		$repl = array(
			'xthreads_phptpl_if(\'$1\', _xthreads_phptpl_expr_parse(\'$2\', '.$fields_var.'))',
			'".$1("',
			'")."',
			//'".eval("return \"".$GLOBALS[\'templates\']->get(\'$1\')."\";")."',
			'\'".strval(\'._xthreads_phptpl_expr_parse(\'$1\', '.$fields_var.').\')."\'',
			'\'".(($GLOBALS["tplvars"]["$1"] = (\'._xthreads_phptpl_expr_parse(\'$2\', '.$fields_var.').\'))?"":"")."\'',
		);
		
		if($evalvarname) {
			$find[] = '#\<while\s+(.*?)\s+do\>#sie';
			$repl[] = '\'"; while(\'._xthreads_phptpl_expr_parse(\'$1\', '.$fields_var.').\') { $'.$evalvarname.'.="\'';
			
			$find[] = '#\<foreach\s+(.*?)\s+do\>#sie';
			$repl[] = '\'"; foreach(\'._xthreads_phptpl_expr_parse(\'$1\', '.$fields_var.').\' as $__key => $__value) { $'.$evalvarname.'.="\'';
			
			$find[] = '#\<repeat\s+(.*?)\s+do\>#sie';
			$repl[] = '\'"; for($__iter=0; $__iter < \'._xthreads_phptpl_expr_parse(\'$1\', '.$fields_var.').\'; ++$__iter) { $'.$evalvarname.'.="\'';
			
			$find[] = '#\</(while|foreach|repeat)\>#i';
			$repl[] = '"; } $'.$evalvarname.'.="';
		}
		
		if(xthreads_allow_php()) {
			$find[] = '#\<\?(?:php|\s).+?(\?\>)#se';
			$repl[] = 'xthreads_phptpl_evalphp(_xthreads_phptpl_expr_parse(\'$0\', '.$fields_var.'), \'$1\')';
		}
		$ourtpl = preg_replace($find, $repl, $ourtpl);
	}
	
}


function xthreads_phptpl_if($s, $e)
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
			return '".(('.$e.')?"';
		} elseif($s == 'elseif') {
			$last = array_pop($GLOBALS['__phptpl_if']);
			$last = 'i'.((int)substr($last, 1) + 1);
			$GLOBALS['__phptpl_if'][] = $last;
			return '":(('.$e.')?"';
		} else {
			$last = array_pop($GLOBALS['__phptpl_if']);
			$last[0] = 'e';
			$GLOBALS['__phptpl_if'][] = $last;
			return '":"';
		}
	}
}

function _xthreads_phptpl_expr_parse($str, $fields=array()) {
	if(!$str && $str !== '0') return '';
	
	// unescapes the slashes added by xthreads_sanitize_eval, plus addslashes() (double quote only) during preg_replace()
	$str = strtr($str, array('\\$' => '$', '\\\\"' => '"', '\\\\' => '\\'));
	
	return xthreads_phptpl_expr_parse($str, $fields);
}
// for non-eval escaped stuff
function _xthreads_phptpl_expr_parse2($str, $fields=array()) {
	if(!$str && $str !== '0') return '';
	
	// unescapes the slashes added by xthreads_sanitize_eval
	$str = strtr($str, array('\\$' => '$', '\\"' => '"', '\\\\' => '\\'));
	
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
	if(xthreads_allow_php()) {
		$str = preg_replace_callback(array($strpreg, "~\<\<\<([a-zA-Z_][a-zA-Z_0-9]*)\r?\n.*?\r?\n\\1;?\r?\n~s"), function($match) {
			return preg_replace('~(?<!\{)\$GLOBALS\\[\'([a-zA-Z_][a-zA-Z_0-9]*)\'\\](((?:-\\>|\\:\\:)[a-zA-Z_][a-zA-Z_0-9]*|\\[\s*([\'"])?[ a-zA-Z_ 0-9]+\\4\s*\\])*)~', '{$0}', $match[0]);
		}, $str);
	} else {
		$str = preg_replace_callback($strpreg, function($match) {
			return preg_replace('~\$GLOBALS\\[\\s*\'([a-zA-Z_][a-zA-Z_0-9]*)\'\\s*\\]~', '$GLOBALS[$1]', $match[0]);
		}, $str);
	}
	
	if(!empty($fields))
		// we need to parse {VALUE} tokens here, as they need to be parsed a bit differently, and so that they're checked for safe expressions
		$str = xthreads_phptpl_parse_fields($str, $fields, false);
	
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
	
	// block new, exit/die, include/require + constants
	if(preg_match('~(?<![a-z0-9_$])(?:new|exit|die|eval|include|include_once|require|require_once|__file__|__dir__|__line__|__function__|__class__|__method__|php_version|php_os|php_sapi|default_include_path|pear_install_dir|pear_extension_dir|php_extension_dir|php_prefix|php_bindir|php_libdir|php_datadir|php_sysconfdir|php_localstatedir|php_config_file_path|php_config_file_scan_dir|php_shlib_suffix|mybb_root)(?![a-z0-9_$])~i', $check)) return false;
	
	
	// block all array index calls and $a{0}() type calls
	if(preg_match('~[\]}]\s*\(~', $check)) return false; // note that this expression may block "{[statement]} ([statement])" type structures; this shouldn't be an issue with template conditionals, but I guess a workaround, if it does eventually be an issue, is to insert something like a "0+" before the bracket to trick the parser
	
	// check functions (implicitly blocks variable functions)
	preg_match_all('~((\$|-\>\s*|[\\\\a-zA-Z0-9_]+\s*\:\:\s*)?[\\\\a-zA-Z0-9_]+)\s*\(~', $check, $matches);
	$allowed_funcs = xthreads_phptpl_get_allowed_funcs();
	foreach($matches[1] as &$func) {
		if(!isset($allowed_funcs[strtr($func, array(' '=>'',"\n"=>'',"\r"=>'',"\t"=>''))])) return false;
	}
	
	return true;
}

function &xthreads_phptpl_get_allowed_funcs()
{
	static $allowed_funcs = null;
	if(!isset($allowed_funcs)) {
		$allowed_funcs = array_flip(explode("\n", str_replace("\r", '', @file_get_contents(MYBB_ROOT.'inc/xthreads/phptpl_allowed_funcs.txt'))));
	}
	// hack to allow us to dynamically add more allowable functions (for image thumbnail processing)
	if(!empty($GLOBALS['phptpl_additional_functions']))
		return array_merge($allowed_funcs, array_flip($GLOBALS['phptpl_additional_functions']));
	else
		return $allowed_funcs;
}

function xthreads_phptpl_evalphp($str, $end)
{
	return '".eval(\'ob_start(); ?>'
		.strtr($str, array('\'' => '\\\'', '\\' => '\\\\'))
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



function xthreads_phptpl_parse_fields($s, $fields, $in_string) {
	if(!empty($fields)) {
		$tr = $ptr = array();
		$do_value_repl = false;
		foreach($fields as $f => $r) {
			if(isset($r)) {
				$tr['{'.$f.'}'] = ($in_string ? $r : '("'.$r.'")');
			} else {
				$ptr[] = '~\\{('.preg_quote($f, '~').')((?:-\>|\[)[^}]+?)?\\}~';
			}
			if($f == 'RAWVALUE') $do_value_repl = true;
		}
		$str_start = ($in_string?'{':'("".');
		$str_end = ($in_string?'}':'."")');
		if($do_value_repl) $s = preg_replace('~\{((?:RAW)?VALUE)\\\\?\$(\d+)\}~', $str_start.'$vars[\'$1$\'][$2]'.$str_end, $s);
		if(!empty($tr))  $s = strtr($s, $tr);
		if(!empty($ptr)) {
			$s = preg_replace_callback($ptr, function($match) use($in_string) {
				if($in_string)
					return '{$vars[\''.$m[1].'\']'._xthreads_phptpl_expr_parse2($m[2]).'}';
				else
					return '("".$vars[\''.$m[1].'\']'._xthreads_phptpl_expr_parse2($m[2]).'."")';
			}, $s);
		}
		// careful with _xthreads_phptpl_expr_parse() call above - we avoid infinite looping by not supplying $fields
		// although _xthreads_phptpl_expr_parse should always be called outside string context, the above is safe because the user cannot put in a '}' character at all - that is, achieving something like {$vars['VAL'][0]}".whatever."{$var} should be impossible
		// an issue with the above, is that it's impossible to do something like {VAR[{VALUE}]} because variables are all auto-global'd... (but even if not, the above isn't guaranteed to work anyway, since the end token '}' might match early, eg {VAR[)
	}
	return $s;
}


// sanitises string $s so that we can directly eval it during "run-time" rather than performing sanitisation there
function xthreads_sanitize_eval(&$s, $fields=array(), $evalvarname=null) {
	if(xthreads_empty($s)) {
		$s = '';
		return;
	}
	// the following won't work properly with array indexes which have non-alphanumeric and underscore chars; also, it won't do ${var} syntax
	// also, damn PHP's magic quotes for preg_replace - but it does assist with backslash fun!!!
	$s = preg_replace_callback('~\\{\\\\\\$([a-zA-Z_][a-zA-Z_0-9]*)((?:-\>|\[)[^}]+?)?\\}~', function($m) {
		return '{$GLOBALS[\''.$m[1].'\']'._xthreads_phptpl_expr_parse2($m[2]).'}';
	}, preg_replace(
		array(
			'~\{\\\\\$forumurl\\\\\$\}~i',
			'~\{\\\\\$forumurl\?\}~i',
			'~\{\\\\\$threadurl\\\\\$\}~i',
			'~\{\\\\\$threadurl\?\}~i'
		), array(
			'{$GLOBALS[\'forumurl\']}',
			'{$GLOBALS[\'forumurl_q\']}',
			'{$GLOBALS[\'threadurl\']}',
			'{$GLOBALS[\'threadurl_q\']}',
		), strtr($s, array('\\' => '\\\\', '$' => '\\$', '"' => '\\"'))
	));
	
	// replace conditionals
	xthreads_phptpl_parsetpl($s, $fields, $evalvarname);
	
	// replace value tokens at the end
	if(!empty($fields))
		$s = xthreads_phptpl_parse_fields($s, $fields, true);
}

