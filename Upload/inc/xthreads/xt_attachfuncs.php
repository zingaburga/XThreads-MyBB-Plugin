<?php
defined('MYBB_ROOT') or die('This file cannot be accessed directly.');

// saves us from including inc/functions.php, and gets around quirky MyBB IP functions
// returns it as an 'iplong'
function xthreads_get_ip() {
	// okay, we have an implicit level of trust on this, but ideally, those proxying connections really should be setting REMOTE_ADDR
	foreach(array('REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_REAL_IP') as $ipfield) {
		if(!isset($_SERVER[$ipfield])) continue;
		$ip = ip2long($_SERVER[$ipfield]);
		// check for proxy addresses
		if($ip && !xthreads_ip_is_internal($ip))
			return $ip;
	}
	return ip2long($_SERVER['REMOTE_ADDR']); // fallback
}

function xthreads_ip_is_internal($ip) {
	static $internal_masks = null;
	if(!isset($internal_masks)) $internal_masks = array(
		// address => subnet mask; from http://en.wikipedia.org/wiki/Reserved_IP_addresses
		ip2long('0.0.0.0') => 8,
		ip2long('10.0.0.0') => 8,
		ip2long('127.0.0.0') => 8,
		ip2long('169.254.0.0') => 16,
		ip2long('172.16.0.0') => 12,
		ip2long('192.168.0.0') => 16,
	);
	foreach($internal_masks as $ipmask => $subnet) {
		if(($ip & (0xFFFFFFFF << (32-$subnet))) == $ipmask)
			return true;
	}
	return false;
}

// generate secret hash to mask random file hash with
// @param: time period offset, we store whether the number was odd or even, and adapt accordingly
function xthreads_attach_hash(&$odd=false) {
	static $secret=null;
	if(!isset($secret)) {
		if(isset($GLOBALS['mybb']->config['database']['password']))
			$config =& $GLOBALS['mybb']->config;
		else {
			@include MYBB_ROOT.'inc/config.php';
		}
		$secret = md5(substr(md5($config['database']['database'].','.$config['database']['password']), 0, 12).__FILE__);
		unset($config);
	}
	$key = $secret;
	if(defined('XTHREADS_EXPIRE_ATTACH_LINK') && XTHREADS_EXPIRE_ATTACH_LINK) {
		$time = floor(time() / XTHREADS_EXPIRE_ATTACH_LINK);
		if($odd !== false) {
			if($time % 2 != $odd) --$time;
		} else
			$odd = $time % 2;
		$key .= '|'.$time;
	}
	if(defined('XTHREADS_ATTACH_LINK_IPMASK') && XTHREADS_ATTACH_LINK_IPMASK)
		$key .= '|'.(xthreads_get_ip() & (0xFFFFFFFF << (32-XTHREADS_ATTACH_LINK_IPMASK))); // because PHP doesn't like ~(0xffffffff >> x)
	return crc32(md5(gzdeflate($key)));
}

// these two functions assume input is hex encoded
function xthreads_attach_encode_hash($hash) {
	$hash = hexdec($hash);
	$odd = false;
	$hash ^= xthreads_attach_hash($odd);
	$hash = str_pad(dechex($hash), 8, '0', STR_PAD_LEFT);
	if(defined('XTHREADS_EXPIRE_ATTACH_LINK') && XTHREADS_EXPIRE_ATTACH_LINK)
		if($odd) $hash .= '0';
	return $hash;
}
function xthreads_attach_decode_hash($hash) {
	$odd = strlen($hash) - 8;
	$hash = hexdec(substr($hash, 0, 8));
	$hash ^= xthreads_attach_hash($odd);
	return str_pad(dechex($hash), 8, '0', STR_PAD_LEFT);
}
