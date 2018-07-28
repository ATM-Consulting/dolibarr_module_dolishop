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

/******/
//$dolishop = new Dolishop\Webservice($db);
//$xml = $dolishop->getAll('shops', array());
//var_dump($xml);
//exit;
/******/

$dolishop = new \Dolishop\Webservice($db);
if ($action == 'testConnection')
{
	$shopName = $dolishop->testConnection();
	if ($shopName === false)
	{
		setEventMessage($langs->trans('DolishopTestConnectionFail'), 'errors');
		if (!empty($dolishop->error)) setEventMessage($dolishop->error, 'errors');
	}
	else setEventMessage($langs->trans('DolishopTestConnectionSuccess', $shopName));
}
elseif ($action == 'syncPsConf')
{
	$res = $dolishop->syncConf();
	if (!$res) setEventMessages('', $dolishop->errors, 'errors');
	else setEventMessage($langs->trans('DolishopSyncPsConfSuccess'));
}


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
    'settings',
    $langs->trans("Module104071Name"),
    0,
    "dolishop@dolishop"
);

$img_warning = img_warning().' ';

// Setup page goes here
$form=new Form($db);

print '<table class="noborder" width="100%">';


_print_title("Parameters");

$var=true;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_PS_SHOP_PATH').'</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_PS_SHOP_PATH">';
print '<input type="text" name="DOLISHOP_PS_SHOP_PATH" size="40" placeholder="https://www.example.com" value="'.$conf->global->DOLISHOP_PS_SHOP_PATH.'" />';
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_PS_WS_AUTH_KEY');
print '<br /><small>'.$langs->trans('DOLISHOP_PS_WS_AUTH_KEY_desc').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_PS_WS_AUTH_KEY">';
print '<input type="text" name="DOLISHOP_PS_WS_AUTH_KEY" size="40" value="'.$conf->global->DOLISHOP_PS_WS_AUTH_KEY.'" />';
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_PS_WS_DEBUG').'</td>';
print '<td>&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_PS_WS_DEBUG">';
print ajax_constantonoff('DOLISHOP_PS_WS_DEBUG');
print '</form></div>';
print '</td></tr>';


if (!empty($conf->global->DOLISHOP_PS_SHOP_PATH) && !empty($conf->global->DOLISHOP_PS_WS_AUTH_KEY))
{
	$var=!$var;
	print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans('DOLISHOP_TEST_CONNECTION');
    print '</td>';
    print '<td align="center">&nbsp;</td>';
    print '<td align="right">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="testConnection">';
    print '<input type="submit" class="butAction" value="'.$langs->trans("DolishopTestConnection").'">';
    print '</form>';
    print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans('DOLISHOP_SYNC_PS_CONF');
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PS_CONF_LANGUAGES_DESC').'</small>';
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PS_CONF_TAXES_DESC').'</small>';
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PS_CONF_IMAGES_DESC').'</small>';
    print '</td>';
    print '<td align="center">&nbsp;</td>';
    print '<td align="right">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="syncPsConf">';
	$conf_str = $dolishop->getFormatedStringTConf();
	if (!empty($conf_str)) print $form->textwithpicto('', $conf_str, 1, 'help', '', 0, 2, 1);
    print '<input type="submit" class="butAction" value="'.$langs->trans("DolishopSyncPsConf").'">';
	if (empty($conf->global->DOLISHOP_PS_CONFIGURATION)) print img_error($langs->trans("DolishopSyncPsConfNeeded"));
    print '</form>';
    print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans('DOLISHOP_SYNC_PS_SHOP_ID');
	print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PS_SHOP_ID_DESC').'</small>';
	print '</td>';
    print '<td align="center">&nbsp;</td>';
    print '<td align="right">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PS_SHOP_ID">';
	$TShop = array();
	$ps_shops = $dolishop->getAll('shops', array());
	if ($ps_shops)
	{
		foreach ($ps_shops->children() as $ps_shop)
		{
			$TShop[(int) $ps_shop->id] = $ps_shop->name->__toString();
		}
	}
	print $form->selectarray('DOLISHOP_SYNC_PS_SHOP_ID', $TShop, $conf->global->DOLISHOP_SYNC_PS_SHOP_ID, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
    print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
    print '</form>';
    print '</td></tr>';
}


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