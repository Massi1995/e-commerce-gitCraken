<?php

if (!defined('_PS_VERSION_'))
	exit;

function upgrade_module_4_2_2($object)
{
	return $object->installDb();
}


