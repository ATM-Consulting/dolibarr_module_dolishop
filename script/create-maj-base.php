<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */

namespace Dolishop;

if(!defined('INC_FROM_DOLIBARR'))
{
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');
}

dol_include_once('/dolishop/class/dolishop.class.php');

global $db;

$o=new \Dolishop\EcmFilesDolishop($db);
$o->init_db_by_vars();
