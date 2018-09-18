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

/**
 * 	\file		admin/dolishop.php
 * 	\ingroup	dolishop
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}


require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
dol_include_once('/dolishop/lib/dolishop.lib.php');
dol_include_once('/dolishop/class/webservice.class.php');

// Translations
$langs->load('admin');
$langs->load('dolishop@dolishop');

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	$value=GETPOST($code);
	
	if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0)
	{
		setEventMessage('SetupSaved');
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}
	
if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		setEventMessage('SetupSaved');
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if ($action == 'DOLISHOP_AUTO_PARAM_MODULE_STOCK')
{
	// Règle de gestion des décrémentations automatiques de stock (la décrémentation manuelle est toujours possible, même si une décrémentation automatique est activée)
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_BILL', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_VALIDATE_ORDER', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_SHIPMENT', 1, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_SHIPMENT_CLOSE', 0, 'chaine', 0, '', $conf->entity);
	
	// Règle de gestion des incrémentations de stock (l'incrémentation manuelle est toujours possible, même si une incrémentation automatique est activée)
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_SUPPLIER_BILL', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER', 1, 'chaine', 0, '', $conf->entity);
	
	// Règles d'exigence sur les stocks
//	dolibarr_set_const($db, 'STOCK_ALLOW_NEGATIVE_TRANSFER', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_MUST_BE_ENOUGH_FOR_INVOICE', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_MUST_BE_ENOUGH_FOR_ORDER', 1, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT', 0, 'chaine', 0, '', $conf->entity);
	
	// Règle de gestion du réapprovisionnement des stocks
//	dolibarr_set_const($db, 'STOCK_USE_VIRTUAL_STOCK', 0, 'chaine', 0, '', $conf->entity);
	
	// Autre
	dolibarr_set_const($db, 'STOCK_USERSTOCK_AUTOCREATE', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_SUPPORTS_SERVICES', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE', 0, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SUPPLIER_ORDER_USE_DISPATCH_STATUS', 0, 'chaine', 0, '', $conf->entity);
	
	setEventMessage('SetupSaved');
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

/*****/
// Voir avec la resource "stock_availables" de comment je peux m'en sortir pour update les quantités
// table : ps_stock_available [quantity] [physical_quantity, reserved_quantity]
//$dolishop = new Dolishop\Webservice($db);
//$xml = $dolishop->getAll('stock_availables', array('filter[id]' => 19), false);
//$xml = $dolishop->getOne('stock_availables', 19, array(), false);
//$dolishop->debugXml($xml);exit;
/*****/

/*
 * View
 */
$page_name = "dolishopSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = dolishopAdminPrepareHead();
dol_fiche_head(
    $head,
    'stocks',
    $langs->trans("Module104071Name"),
    0,
    "dolishop@dolishop"
);

$img_warning = img_warning().' ';

// Setup page goes here
$form=new Form($db);

print '<table class="noborder" width="100%">';


_print_title("Parameters");

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_AUTO_PARAM_MODULE_STOCK');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="DOLISHOP_AUTO_PARAM_MODULE_STOCK">';
print '<input type="submit" class="butAction" value="'.$langs->trans("ModuleStockAutoConfig").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_STOCK');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_STOCK_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_STOCK">';
print ajax_constantonoff('DOLISHOP_SYNC_STOCK');
print '</form></div>';
print '</td></tr>';

print '</table>';

llxFooter();

$db->close();



function _print_title($title="")
{
    global $langs;
    print '<tr class="liste_titre">';
    print '<td width="65%">'.$langs->trans($title).'</td>'."\n";
    print '<td align="center" width="1%">&nbsp;</td>';
    print '<td align="center"></td>'."\n";
    print '</tr>';
}