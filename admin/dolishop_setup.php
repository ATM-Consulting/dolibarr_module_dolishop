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
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';

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

$dolishop = new Dolishop\Webservice($db);


if ($action == 'save_mesuring_units')
{
	Dolishop\Webservice::$ps_configuration['MESURING_UNITS'] = array(
		'WEIGHT_UNIT' => GETPOST('WEIGHT_UNIT')
		,'DIMENSION_UNIT' => GETPOST('DIMENSION_UNIT')
	);
	
	if (dolibarr_set_const($db, 'DOLISHOP_PS_CONFIGURATION', json_encode(Dolishop\Webservice::$ps_configuration), 'chaine', 0, '', $conf->entity) > 0)
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
else if ($action == 'save_countries')
{
	Dolishop\Webservice::$ps_configuration['COUNTRIES_ID'] = GETPOST('TCountry', 'array');
	
	if (dolibarr_set_const($db, 'DOLISHOP_PS_CONFIGURATION', json_encode(Dolishop\Webservice::$ps_configuration), 'chaine', 0, '', $conf->entity) > 0)
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

/******/
//$dolishop = new Dolishop\Webservice($db);
//$xml = $dolishop->getAll('weight_ranges', array());
//$xml = $dolishop->getAll('products', array('filter[id]'=>'[3]'));
//$xml = $dolishop->getAll('stock_availables', array('filter[id]'=>'[32]'));
//var_dump($xml->products[0]->product->associations->stock_availables);
//$dolishop->debugXml($xml);
//exit;
/******/

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
    -1,
    "dolishop@dolishop"
);

$img_warning = img_warning().' ';

// Setup page goes here
$form=new Form($db);
$formproduct = new FormProduct($db);

//print load_fiche_titre($langs->trans("DolishopMainOptions"),'','');

print '<table class="noborder" width="100%">';


print '<tr class="liste_titre">';
print '<td width="65%">'.$langs->trans('Parameters').'</td>'."\n";
print '<td align="center" width="1%">&nbsp;</td>';
print '<td align="center"></td>'."\n";
print '</tr>';

$var=true;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_API_NAME');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_API_NAME">';
print Form::selectarray('DOLISHOP_API_NAME', array('prestashop' => 'Prestashop', 'magento' => 'Magento'), $conf->global->DOLISHOP_API_NAME, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_STORE_PATH', ucfirst($dolishop->api_name)).'</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_STORE_PATH">';
print '<input type="text" name="DOLISHOP_STORE_PATH" size="40" placeholder="https://www.example.com" value="'.$conf->global->DOLISHOP_STORE_PATH.'" />';
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

if ($dolishop->api_name == 'prestashop')
{
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
}
else if ($dolishop->api_name == 'magento')
{
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISHOP_MAGENTO_USERNAME');
	print '<br /><small>'.$langs->trans('DOLISHOP_MAGENTO_USERNAME_desc').'</small>';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOLISHOP_MAGENTO_USERNAME">';
	print '<input type="text" name="DOLISHOP_MAGENTO_USERNAME" size="40" value="'.$conf->global->DOLISHOP_MAGENTO_USERNAME.'" />';
	print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISHOP_MAGENTO_PASSWORD');
	print '<br /><small>'.$langs->trans('DOLISHOP_MAGENTO_PASSWORD_desc').'</small>';
	print '</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOLISHOP_MAGENTO_PASSWORD">';
	print '<input type="password" name="DOLISHOP_MAGENTO_PASSWORD" size="40" value="'.$conf->global->DOLISHOP_MAGENTO_PASSWORD.'" />';
	print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
}


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_STORE_WS_DEBUG').'</td>';
print '<td>&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_STORE_WS_DEBUG">';
print ajax_constantonoff('DOLISHOP_STORE_WS_DEBUG');
print '</form></div>';
print '</td></tr>';


if ($dolishop->isConfigured())
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

	if ($dolishop->api_name == 'prestashop')
	{
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td>'.$langs->trans('DOLISHOP_SYNC_PS_CONF');
		print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PS_CONF_DESC').'</small>';
		print '</td>';
		print '<td align="center">&nbsp;</td>';
		print '<td align="right">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="syncPsConf">';
		$conf_str = $dolishop->getFormatedStringTConf();
		if (!empty($conf_str)) print $form->textwithpicto('', $conf_str, 1, 'help', '', 0, 2, 1);
		print '<input type="submit" class="butAction" value="'.$langs->trans("DolishopSyncPsConf").'">';
		if (empty(Dolishop\Webservice::$ps_configuration['PS_LANGUAGES']) || empty(Dolishop\Webservice::$ps_configuration['PS_TAXES']) || empty(Dolishop\Webservice::$ps_configuration['PS_IMAGES_MIME_TYPES']))
			print img_error($langs->trans("DolishopSyncPsConfNeeded"));
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
		$TShop = $dolishop->WsGetAllShops();
		print Form::selectarray('DOLISHOP_SYNC_PS_SHOP_ID', $TShop, $conf->global->DOLISHOP_SYNC_PS_SHOP_ID, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);
		print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
		print '</form>';
		print '</td></tr>';
	}
	else if ($dolishop->api_name == 'magento')
	{
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td>'.$langs->trans('DOLISHOP_SYNC_MAGENTO_STORE_CODE');
		print '</td>';
		print '<td align="center">&nbsp;</td>';
		print '<td align="right">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_MAGENTO_STORE_CODE">';
		$TShop = $dolishop->WsGetAllShops();
		print Form::selectarray('DOLISHOP_SYNC_MAGENTO_STORE_CODE', $TShop, $conf->global->DOLISHOP_SYNC_MAGENTO_STORE_CODE, 0, 0, 0, '', 1, 0, 0, '', 'minwidth200', 1);
		print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
		print '</form>';
		print '</td></tr>';
	}


}



print '</table>';

dol_fiche_end();


if ($dolishop->api_name == 'prestashop')
{
	print '<div class="fichehalfleft">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="save_mesuring_units" />';
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td></td>'."\n";
	print '<td>'.$langs->trans('DolibarrMesuringUnits').'</td>'."\n";
	print '</tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('dolishop_WEIGHT_UNIT').'</td>';
	print '<td>';
	$selected = 0;
	if (!empty(Dolishop\Webservice::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'])) $selected = Dolishop\Webservice::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'];
	print $formproduct->load_measuring_units('WEIGHT_UNIT', 'weight', $selected);
	if (!isset(Dolishop\Webservice::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'])) print ' '.img_error($langs->trans("DolishopConfNotSaveYet"));
	print '</td>';
	print '</tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('dolishop_DIMENSION_UNIT').'</td>';
	print '<td>';
	$selected = 0;
	if (!empty(Dolishop\Webservice::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT'])) $selected = Dolishop\Webservice::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT'];
	print $formproduct->load_measuring_units('DIMENSION_UNIT', 'size', $selected);
	if (!isset(Dolishop\Webservice::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT'])) print ' '.img_error($langs->trans("DolishopConfNotSaveYet"));
	print '</td>';
	print '</tr>';

	print '</table>';
	print '<div class="center"><input class="button" value="'.$langs->trans('Save').'" type="submit"></div>';
	print '</form>';
	print '</div>'; // fichehalfleft


	print '<div class="fichehalfright">';
	print '<div class="ficheaddleft">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="save_countries" />';
	print '<table class="noborder" width="100%" style="margin-bottom:5px;">';
	print '<tr class="liste_titre">';
	print '<td colspan="2">'.$langs->trans('WebCountriesAssociations').'</td>'."\n";
	print '<td>'.$langs->trans('DolibarrCountries').'</td>'."\n";
	print '</tr>';

	$TCountry = $dolishop->WsGetAllCountries();
	if (!empty($TCountry))
	{
		foreach ($TCountry as $id_country => $name)
		{
			$var=!$var;
			print '<tr '.$bc[$var].'>';
			print '<td width="2%" align="center">'.$id_country.'</td>';
			print '<td>'.$name.'</td>';
			print '<td>';
			$selected = '';
			if (!empty(Dolishop\Webservice::$ps_configuration['COUNTRIES_ID'][$id_country])) $selected = Dolishop\Webservice::$ps_configuration['COUNTRIES_ID'][$id_country];
			print $form->select_country($selected, 'TCountry['.$id_country.']', '', 0, 'minwidth200 maxwidth300');
			print '</td>';
			print '</tr>';
		}
	}
	else
	{
		print '<td colspan="3" align="center">'.$langs->trans('WebCountriesAssociationsHelp').'</td>';
	}

	print '</table>';
	print '<div class="center"><input class="button" value="'.$langs->trans('Save').'" type="submit"></div>';
	print '</form>';
	print '</div>'; // ficheaddleft
	print '</div>'; // fichehalfright


	print '<div style="clear:both;"></div>';
}
else if ($dolishop->api_name == 'magento')
{

}

llxFooter();

$db->close();
