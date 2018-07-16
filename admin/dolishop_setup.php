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
require_once '../lib/dolishop.lib.php';
dol_include_once('/dolishop/class/dolishop.class.php');

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
//$dolishop = new Dolishop\Dolishop($db);
//$xml = $dolishop->getAll('stock_availables', array('filter[id_product]' => '[1]'));
//$dolishop->debugXml($xml);
//exit;
/******/

$dolishop = new \Dolishop\Dolishop($db);
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
	$res = $dolishop->syncPsConf();
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
$var=false;
print '<table class="noborder" width="100%">';


_print_title("Parameters");

// Example with a yes / no select
//_print_on_off('CONSTNAME', 'ParamLabel' , 'ParamDesc');

// Example with imput
_print_input_form_part('DOLISHOP_PS_SHOP_PATH', $langs->trans('DOLISHOP_PS_SHOP_PATH'), '', array('placeholder'=>'https://www.example.com'));
_print_input_form_part('DOLISHOP_PS_WS_AUTH_KEY', $langs->trans('DOLISHOP_PS_WS_AUTH_KEY'), $langs->trans('DOLISHOP_PS_WS_AUTH_KEY_desc'));
_print_on_off('DOLISHOP_PS_WS_DEBUG', $langs->trans('DOLISHOP_PS_WS_DEBUG'));

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCTS_DESC').'</small>';
print '</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS">';
print ajax_constantonoff('DOLISHOP_SYNC_PRODUCTS');
print '</form>';
print '</td></tr>';

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT');
print '</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT">';
print '<input type="text" size="5" name="DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT" value="'.$conf->global->DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT.'" />';
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';


print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS_CATEGORIES');
print '</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS_CATEGORIES">';
$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', 'parent', 64, 0, 1);
print $form->multiselectarray('DOLISHOP_SYNC_PRODUCTS_CATEGORIES', $cate_arbo, explode(',',$conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES), '', 0, '', 0, '100%');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';
$var=!$var;


print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS_IMAGES');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCTS_IMAGES_DESC').'</small>';
print '</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS_IMAGES">';
print ajax_constantonoff('DOLISHOP_SYNC_PRODUCTS_IMAGES');
print '</form>';
print '</td></tr>';


// Example with color
//_print_input_form_part('CONSTNAME', 'ParamLabel', 'ParamDesc', array('type'=>'color'),'input','ParamHelp');

// Example with placeholder
//_print_input_form_part('CONSTNAME','ParamLabel','ParamDesc',array('placeholder'=>'http://'),'input','ParamHelp');

// Example with textarea
//_print_input_form_part('CONSTNAME','ParamLabel','ParamDesc',array(),'textarea');

if (!empty($conf->global->DOLISHOP_PS_SHOP_PATH) && !empty($conf->global->DOLISHOP_PS_WS_AUTH_KEY))
{
	_print_title("Prestashop");
	
	print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans('DOLISHOP_TEST_CONNECTION');
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
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
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
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
}


print '</table>';

llxFooter();

$db->close();



function _print_title($title="")
{
    global $langs;
    print '<tr class="liste_titre">';
    print '<td width="75%">'.$langs->trans($title).'</td>'."\n";
    print '<td align="center" width="1%">&nbsp;</td>';
    print '<td align="center" ></td>'."\n";
    print '</tr>';
}

function _print_on_off($confkey, $title = false, $TDesc ='')
{
    global $var, $bc, $langs, $conf;
    $var=!$var;
    
    print '<tr '.$bc[$var].'>';
    print '<td>'.($title?$title:$langs->trans($confkey));
    if(!empty($TDesc))
    {
		if (!is_array($TDesc)) $TDesc = array($TDesc);
		foreach ($TDesc as $desc) print '<br><small>'.$langs->trans($desc).'</small>';
    }
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="center" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';
    print ajax_constantonoff($confkey);
    print '</form>';
    print '</td></tr>';
}

function _print_input_form_part($confkey, $title = false, $desc ='', $metas = array(), $type='input', $help = false)
{
    global $var, $bc, $langs, $conf, $db;
    $var=!$var;
    
    $form=new Form($db);
    
    $defaultMetas = array(
        'name' => $confkey
    );
    
    if($type!='textarea'){
        $defaultMetas['type']   = 'text';
        $defaultMetas['value']  = $conf->global->{$confkey};
    }
    
    
    $metas = array_merge ($defaultMetas, $metas);
    $metascompil = '';
    foreach ($metas as $key => $values)
    {
        $metascompil .= ' '.$key.'="'.$values.'" ';
    }
    
    print '<tr '.$bc[$var].'>';
    print '<td>';
    
    if(!empty($help)){
        print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
    }
    else {
        print $title?$title:$langs->trans($confkey);
    }
    
    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';
    if($type=='textarea'){
        print '<textarea '.$metascompil.'  >'.dol_htmlentities($conf->global->{$confkey}).'</textarea>';
    }
    else {
        print '<input '.$metascompil.'  />';
    }
    
    print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
    print '</form>';
    print '</td></tr>';
}