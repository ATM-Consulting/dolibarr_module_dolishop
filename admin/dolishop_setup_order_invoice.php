<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
dol_include_once('/dolishop/class/dolishop.class.php');
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

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
	
	if ($code == 'DOLISHOP_SYNC_PRODUCTS_CATEGORIES' && is_array($value)) $value = implode(',', $value);
	
	if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0)
	{
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
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}


$dolishop = new Dolishop\Dolishop($db);

if ($action == 'save_order_states')
{
	$reflection = new ReflectionClass('Commande');
	$dolishop::$ps_configuration['PS_ORDER_STATES'] = array();
	$ps_order_states = GETPOST('ps_order_states', 'array');
	foreach ($ps_order_states as $id_order_state => $order_status_cont)
	{
		if ($order_status_cont != -1 && !empty($order_status_cont))
		{
			$dolishop::$ps_configuration['PS_ORDER_STATES'][$id_order_state] = array(
				'ps_id_order_state' => $id_order_state
				,'dol_status_const' => $order_status_cont
				,'dol_status' => $reflection->getConstant($order_status_cont)
			);
		}
	}
	
	$res = dolibarr_set_const($db, 'DOLISHOP_PS_CONFIGURATION', json_encode($dolishop::$ps_configuration));
	if ($res > 0) $dolishop::$ps_configuration = json_decode($conf->global->DOLISHOP_PS_CONFIGURATION, true);
	else setEventMessage($db->lasterror(), 'errors');
}


/******/
//$xml=$dolishop->getAll('orders', array());
//$xml=$dolishop->getAll('orders', array());
//$dolishop->debugXml($xml);
//var_dump($dolishop->errors);
//exit;
/******/

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
    'orders_invoices',
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
print '<td>'.$langs->trans('DOLISHOP_SYNC_ORDER');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_ORDER_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_ORDER">';
print ajax_constantonoff('DOLISHOP_SYNC_ORDER');
print '</form></div>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE">';
$form->select_produits($conf->global->DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE, 'DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE', '', 20, 0, 1, 2, '', 1);
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form></div>';
print '</td></tr>';



print '</table>';
print '<br /><br /><br />';

$TStatus = array(
	'STATUS_DRAFT' => $langs->trans('Draft')
	,'STATUS_VALIDATED  '=> $langs->trans('Validate')
	,'STATUS_SHIPMENTONPROCESS' => $langs->trans('Accepted')
	,'STATUS_CLOSED' => $langs->trans('Closed')
	,'STATUS_CANCELED' => $langs->trans('Canceled')
);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="save_order_states" />';
print '<table class="noborder" width="100%">';

print '<tr class="liste_titre">';
print '<td colspan="2">'.$form->textwithpicto($langs->trans('PsOrderStatesAssociations'), $langs->trans('PsOrderStatesAssociationsHelp')).'</td>'."\n";
print '<td>'.$langs->trans('DolibarrStatusCommande').'</td>'."\n";
print '<td>'.$langs->trans('DolishopWorkflowCommande').'</td>'."\n";
print '<td>&nbsp;</td>'."\n";
print '</tr>';
	
$order_states = $dolishop->getAll('order_states', array());
if ($order_states)
{
	foreach ($order_states->children() as $order_state)
	{
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td width="2%" align="center">'.$order_state->id.'</td>';
		print '<td width="20%">'.$order_state->name->language[0].'</td>';
		print '<td width="20%">';
		$selected = '';
//		var_dump($dolishop::$ps_configuration['PS_ORDER_STATES']);exit;
		if (!empty($dolishop::$ps_configuration['PS_ORDER_STATES'][(int) $order_state->id])) $selected = $dolishop::$ps_configuration['PS_ORDER_STATES'][(int) $order_state->id]['dol_status_const'];
		print $form->selectarray('ps_order_states['.$order_state->id.']', $TStatus, $selected, 1);
		print '</td>';
		print '<td>'.$form->multiselectarray('workflow['.$order_state->id.']', array('CreateShipping' => 'Créer expédition', 'CreateInvoice' => 'Créer facture')).'</td>';
		print '<td>&nbsp;</td>';
		print '</tr>';
	}
}
else
{
	
}

print '</table>';
print '<div class="center"><input class="button" value="'.$langs->trans('Save').'" type="submit"></div>';
print '</form>';


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