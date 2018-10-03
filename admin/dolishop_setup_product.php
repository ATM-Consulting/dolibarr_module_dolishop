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

$dolishop = new \Dolishop\Webservice($db);

if ($action == 'CompareCategoriesD2W')
{
	$dol_fullarbo = $dolishop->getCategoriesFullArboFromDol();
	$web_fullarbo = $dolishop->getCategoriesFullArboFromWeb();
	$dolishop->syncCategoriesD2W_checker($dol_fullarbo, $web_fullarbo);
}
else if ($action == 'SyncCategoriesD2W')
{
	// TODO déclancher la synchro Dolibarr vers Presta
//	set_time_limit(0);
//	$dolishop->syncCategoriesD2W();
//	exit;
}
else if ($action == 'SyncCategoriesW2D')
{
	// TODO déclancher la synchro Presta vers Dolibarr auqnd la méthode sera écrite
//	set_time_limit(0);
//	$dolishop->syncCategoriesW2D;
//	exit;
}

/******/
//$dolishop = new Dolishop\Dolishop($db);
//$xml = $dolishop->getAll('order_states', array());
//foreach ($xml->children() as $order_state) var_dump($order_state->name->language[0]);
//$dolishop->debugXml($xml->children()->children()->name->language[0]);
//var_dump($dolishop->errors);
//exit;
/******/


/*
 * View
 */
$arrayofcss=array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.css');
$page_name = "dolishopSetup";
llxHeader('', $langs->trans($page_name),'','',0,0,array(),$arrayofcss);

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

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCT_CATEG_D2W');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCT_CATEG_D2W_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCT_CATEG_D2W">';
print ajax_constantonoff('DOLISHOP_SYNC_PRODUCT_CATEG_D2W');
print '</form></div>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISHOP_SYNC_PRODUCT_CATEG_W2D');
print '<br><small>'.$img_warning.$langs->trans('DOLISHOP_SYNC_PRODUCT_CATEG_W2D_DESC').'</small>';
print '</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISHOP_SYNC_PRODUCT_CATEG_W2D">';
print ajax_constantonoff('DOLISHOP_SYNC_PRODUCT_CATEG_W2D');
print '</form></div>';
print '</td></tr>';

print '</table>';


dol_fiche_end();


print '<div class="fichehalfleft">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="50%">'.$form->textwithpicto($langs->trans('DolishopTreeDolibarrCategories'), $langs->trans('DolishopTreeDolibarrCategories_tip')).'</td>'."\n";
print '<td width="50%">'.$langs->trans('DolishopTreeWebCategories').'</td>'."\n";
print '</tr>';

if ($action == 'CompareCategoriesD2W')
{
	print '<tr class="oddeven">';
	print '<td>'.dolishop_get_tree($dol_fullarbo).'</td>';
	if ($web_fullarbo !== false) print '<td>'.dolishop_get_tree($web_fullarbo).'</td>';
	else print '<td align="center">'.$langs->trans('WebCategoriesHelp').'</td>';
	print '</tr>';
}

print '</table>';
print '<div class="center">';

print '<form id="sync_categories" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
if ($action == 'CompareCategoriesD2W')
{
	print '<input type="hidden" name="action" value="" />';
	
	print '<input id="sync_categories_d2w" data-action="SyncCategoriesD2W" class="button" value="'.$langs->trans('SyncCategoriesD2W').'" type="submit">';
	print '<input id="sync_categories_w2d" data-action="SyncCategoriesW2D" class="button" value="'.$langs->trans('SyncCategoriesW2D').'" type="submit">';
	print '
		<script type="text/javascript">
			$(function() {
				$("#sync_categories").submit(function(event) {
				console.log("cal");
					if (confirm("'.dol_escape_js($langs->transnoentities('DolishopConfirmSyncCategories')).'") === true) {
						$("#sync_categories_d2w, #sync_categories_w2d").prop("disabled", true);

						var action = event.originalEvent.explicitOriginalTarget.dataset.action;
						$(this).children("input[name=action]").val(action);
					} else {
						event.preventDefault();
					}
				});
			});
		</script>
	';
}
else
{
	print '<input type="hidden" name="action" value="CompareCategoriesD2W" />';
	print '<input class="button" value="'.$langs->trans('Compare').'" type="submit">';
}



print '</div>';
print '</form>';
print '</div>'; // fichehalfleft

/*
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


print '</table>';
print '<div class="center"><input class="button" value="'.$langs->trans('Save').'" type="submit"></div>';
print '</form>';
print '</div>'; // ficheaddleft
print '</div>'; // fichehalfright
*/

llxFooter();

$db->close();
