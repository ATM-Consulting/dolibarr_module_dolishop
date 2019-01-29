<?php
/* 
 * Copyright (C) 2018		ATM Consulting			<support@atm-consulting.fr>
 * Copyright (C) 2018		Pierre-Henry Favre		<phf@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if(!defined('INC_FROM_DOLIBARR'))
{
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');
}

dol_include_once('/dolishop/class/webservice.class.php');

global $db;

$o=new Dolishop\EcmFilesDolishop($db);
$o->init_db_by_vars();


$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute ADD COLUMN ps_id_option_group integer');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute ADD COLUMN mg_eav_attribute_id integer');

$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute_value ADD COLUMN ps_id_option_value integer');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute_value ADD COLUMN ps_id_option_group integer');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute_value ADD COLUMN mg_eav_attribute_option_id integer');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute_value ADD COLUMN mg_eav_attribute_id integer');

$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute_combination ADD COLUMN ps_id_combination integer');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'product_attribute_combination ADD COLUMN mg_id_combination integer');


$o=new MgSalesOrderStatuses($db);
$o->init_db_by_vars();
