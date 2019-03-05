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
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';


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
	
	if ($code == 'DOLISHOP_SYNC_WEB_ORDER_STATES' && is_array($value)) $value = implode('|', $value);
	
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

$dolishop = new Dolishop\Webservice($db);

if ($action == 'save_carriers')
{
	$TCarrierAssociation = GETPOST('TCarrierAssociation', 'array');
	if ($dolishop->setCarriersAssociation($TCarrierAssociation)) setEventMessage('SetupSaved');
}


$TOrderState = $dolishop->WsGetAllOrderStates();


//require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
//$exp = new \Expedition($db);
//$exp->fetch(11);
//
//var_dump($exp);exit;
/******/
//$xml=$dolishop->getAll('order_carriers', array('filter[id_order]' => '[6]'));
//$schema_order_carrier = $dolishop->getSchema('order_carriers', 'blank');
//$xml=$dolishop->getOne('orders', 6);
//$xml=$dolishop->getOne('order_carriers', 2);
//$dolishop->debugXml($xml);exit;
//$xml=$dolishop->getOne('customers', 1);
//$dolishop->debugXml($xml);exit;
//$xml=$dolishop->getOne('addresses', 1);
//$dolishop->debugXml($xml->address);exit;
//$r=$dolishop->createDolCustomer(1, 1, 1);
//$dolishop->debugXml($xml);exit;
//var_dump($xml);
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
    'orders',
    $langs->trans("Module104071Name"),
    -1,
    "dolishop@dolishop"
);

$img_warning = img_warning().' ';

// Setup page goes here
$form=new Form($db);
$formproduct = new FormProduct($db);
$formcompany= new FormCompany($db);

print '<table class="noborder" width="100%">';


print '<tr class="liste_titre">';
print '<td width="65%">'.$langs->trans('Parameters').'</td>'."\n";
print '<td align="center" width="1%">&nbsp;</td>';
print '<td align="center"></td>'."\n";
print '</tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_ORDERS', ucfirst($dolishop->api_name));
if ($dolishop->api_name == 'prestashop')
{
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_ORDERS_DESC').'</small>';
}
else if ($dolishop->api_name == 'magento')
{

}
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_ORDERS">';
print ajax_constantonoff('DOLISHOP_SYNC_ORDERS');
print '</form></div>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_WEB_ORDER_STATES');
if ($dolishop->api_name == 'magento')
{
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_WEB_ORDER_STATES_DESC_MG', $langs->trans('MgSalesOrderStatusesTable')).'</small>';
}
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_WEB_ORDER_STATES">';
print Form::multiselectarray('DOLISHOP_SYNC_WEB_ORDER_STATES', $TOrderState, explode('|', $conf->global->DOLISHOP_SYNC_WEB_ORDER_STATES), 0, 0, 'minwidth200 maxwidth300');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';
print '</td></tr>';

$tmpobject=new Commande($db);
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY">';
$formcompany->selectTypeContact($tmpobject, $conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY, 'DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY', 'external', 'position', 1, 'minwidth200 maxwidth300');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING">';
$formcompany->selectTypeContact($tmpobject, $conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING, 'DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING', 'external', 'position', 1, 'minwidth200 maxwidth300');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE">';
$form->select_produits($conf->global->DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE, 'DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE', '', 20, 0, 1, 2, '', 1);
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_WEB_PRODUCT_IF_NOT_EXISTS');
if ($dolishop->api_name == 'prestashop')
{
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_WEB_PRODUCT_IF_NOT_EXISTS_DESC').'</small>';
}
else if ($dolishop->api_name == 'magento')
{

}
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_WEB_PRODUCT_IF_NOT_EXISTS">';
print ajax_constantonoff('DOLISHOP_SYNC_WEB_PRODUCT_IF_NOT_EXISTS');
print '</form></div>';
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_DEFAULT_WAREHOUSE_ID');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_DEFAULT_WAREHOUSE_ID">';
print $formproduct->selectWarehouses($conf->global->DOLISHOP_DEFAULT_WAREHOUSE_ID, 'DOLISHOP_DEFAULT_WAREHOUSE_ID', '', 1, 0, 0, '', 0, 0, array(), 'minwidth200 maxwidth300');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_STATUS_TO_CREATE_INVOICE');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_STATUS_TO_CREATE_INVOICE_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_STATUS_TO_CREATE_INVOICE">';
print Form::selectarray('DOLISHOP_STATUS_TO_CREATE_INVOICE', array(Facture::STATUS_DRAFT => $langs->trans('DolishopDraft'), Facture::STATUS_VALIDATED => $langs->trans('DolishopValidate')), $conf->global->DOLISHOP_STATUS_TO_CREATE_INVOICE, 1, 0, 0, 'minwidth200 maxwidth300', 0, 0, array(), '', 'minwidth200 maxwidth300', 1);
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_DEFAULT_ACCOUNT_ID');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_DEFAULT_ACCOUNT_ID_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_DEFAULT_ACCOUNT_ID">';
$form->select_comptes($conf->global->DOLISHOP_DEFAULT_ACCOUNT_ID,'DOLISHOP_DEFAULT_ACCOUNT_ID',0,'',2);
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';


if ($dolishop->api_name == 'prestashop')
{
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING');
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING_DESC').'</small>';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING">';
	print Form::selectarray('DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING', $TOrderState, $conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 1);
	print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED');
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED_DESC').'</small>';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED">';
	print Form::selectarray('DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED', $TOrderState, $conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 1);
	print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
}
else if ($dolishop->api_name == 'magento')
{
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISHOP_CREATE_WEB_SHIPPING');
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_CREATE_WEB_SHIPPING_DESC').'</small>';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOLISHOP_CREATE_WEB_SHIPPING">';
	print ajax_constantonoff('DOLISHOP_CREATE_WEB_SHIPPING');
	print '</form></div>';
	print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISHOP_CREATE_WEB_INVOICE');
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_CREATE_WEB_INVOICE_DESC').'</small>';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOLISHOP_CREATE_WEB_INVOICE">';
	print ajax_constantonoff('DOLISHOP_CREATE_WEB_INVOICE');
	print '</form></div>';
	print '</td></tr>';
}

print '</table>';

dol_fiche_end();


print '<div class="fichehalfleft">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="save_carriers" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans('WebCarriersAssociations').'</td>'."\n";
print '<td>'.$langs->trans('DolibarrShippingMethod').'</td>'."\n";
print '</tr>';

if ($dolishop->api_name == 'prestashop')
{
	$TShippingMethod = $dolishop->WsGetAllShippingMethods();
}

if ($TShippingMethod)
{
	foreach ($TShippingMethod as $TInfo)
	{
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td width="2%" align="center">'.$TInfo['id'].'</td>';
		print '<td width="20%">'.$TInfo['label'].'</td>';
		print '<td width="20%">';
		$form->selectShippingMethod($TInfo['selected'], 'TCarrierAssociation['.$TInfo['id'].']', '', 1);
		print '</td>';
		print '</tr>';
	}
}
else
{
	print '<tr>';
	print '<td colspan="3" align="center">';
	if ($dolishop->api_name == 'prestashop') print $langs->trans('WebCarriersAssociationsHelp');
	else if ($dolishop->api_name == 'magento') print $langs->trans('WebCarriersAssociationsHelpMg', $langs->transnoentities('DictionarySendingMethods'));
	print '</td>';
	print '</tr>';

}

print '</table>';
print '<div class="center"><input class="button" value="'.$langs->trans('Save').'" type="submit"></div>';
print '</form>';
print '</div>';

/* TODO à voir comment faire le matching avec le mode de paiement (si c'est utile car 99% du temps ça sera Carte bancaire / paypal)
print '<div class="fichehalfright">';
print '<div class="ficheaddleft">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="save_carriers" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans('WebPaymentsAssociations').'</td>'."\n";
print '<td>'.$langs->trans('DolibarrPaymentMethod').'</td>'."\n";
print '</tr>';

$payments = $dolishop->getAll('order_payments', array());
if ($payments)
{
	foreach ($payments->children() as $payment)
	{
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td width="2%" align="center">'.$payment->id.'</td>';
		print '<td width="20%">'.$payment->name.'</td>';
		print '<td width="20%">';
		$selected = '';
		if (!empty($dolishop::$ps_configuration['WEB_PAYMENTS_ASSOC'][(int) $payment->id])) $selected = $dolishop::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $payment->id];
		$form->select_types_paiements($selected, 'TPaymentAssociation['.$payment->id.']', '', 0, 1);
		print '</td>';
		print '</tr>';
	}
}
else
{
	print '<td colspan="3" align="center">'.$langs->trans('WebPaymentsAssociationsHelp').'</td>';
}
print '</table>';
print '<div class="center"><input class="button" value="'.$langs->trans('Save').'" type="submit"></div>';
print '</form>';

print '</div>'; // ficheaddleft
print '</div>'; // fichehalfright
*/

print '<div style="clear:both;"></div>';



llxFooter();
$db->close();
