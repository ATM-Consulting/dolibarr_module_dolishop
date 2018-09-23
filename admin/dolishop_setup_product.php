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
	
	if (
		in_array($code, array('DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR', 'DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE')) 
		&& is_array($value)
	) $value = implode(',', $value);
	
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

/******/
//$dolishop = new Dolishop\Dolishop($db);
//$xml = $dolishop->getAll('order_states', array());
//foreach ($xml->children() as $order_state) var_dump($order_state->name->language[0]);
//$dolishop->debugXml($xml->children()->children()->name->language[0]);
//var_dump($dolishop->errors);
//exit;
/******/

$dolishop = new \Dolishop\Webservice($db);


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
    'products',
    $langs->trans("Module104071Name"),
    0,
    "dolishop@dolishop"
);

$img_warning = img_warning().' ';

// Setup page goes here
$form=new Form($db);

print '<table class="noborder" width="100%">';


print '<tr class="liste_titre">';
print '<td width="65%">'.$langs->trans('Parameters').'</td>'."\n";
print '<td align="center" width="1%">&nbsp;</td>';
print '<td align="center"></td>'."\n";
print '</tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCTS_DESC').'</small>';
if (!empty($conf->variants->enabled)) print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCTS_COMBINATIONS_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS">';
print ajax_constantonoff('DOLISHOP_SYNC_PRODUCTS');
print '</form></div>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT">';
print '<input type="text" size="5" name="DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT" value="'.$conf->global->DOLISHOP_TRUNC_PS_DESCRIPTION_SHORT.'" />';
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR');
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR">';
$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', 'parent', 64, 0, 1);
print $form->multiselectarray('DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR', $cate_arbo, explode(',',$conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR), '', 0, '', 0, '75%');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE">';
$TProductCatWebsite = $dolishop->WsGetAllProductsCategories();
print $form->multiselectarray('DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE', $TProductCatWebsite, explode(',',$conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE), '', 0, '', 0, '75%');
print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCTS_IMAGES');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCTS_IMAGES_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCTS_IMAGES">';
print ajax_constantonoff('DOLISHOP_SYNC_PRODUCTS_IMAGES');
print '</form></div>';
print '</td></tr>';


print '</table>';

llxFooter();

$db->close();
