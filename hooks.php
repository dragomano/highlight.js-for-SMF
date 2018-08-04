<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	exit('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

if (!empty($context['uninstalling']))
	$smcFunc['db_query']('', "DELETE FROM {db_prefix}settings WHERE variable LIKE 'ch_%' ");

$hooks = array(
	'integrate_pre_include' => '$sourcedir/Class-Highlighting.php',
	'integrate_pre_load'    => 'Code_Highlighting::hooks'
);

if (!empty($context['uninstalling']))
	$call = 'remove_integration_function';
else
	$call = 'add_integration_function';

foreach ($hooks as $hook => $function)
	$call($hook, $function);
