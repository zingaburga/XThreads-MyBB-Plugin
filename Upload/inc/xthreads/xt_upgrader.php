<?php

if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');

if(!is_array($info)) return false;

// even if there are no upgrade actions to be run for a particular upgrade, we'll get the user into the habbit of running the upgrader


if($info['version'] < 1.0) {
	//do stuff?
}

return true;
