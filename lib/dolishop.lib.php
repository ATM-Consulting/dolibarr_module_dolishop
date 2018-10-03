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
 *	\file		lib/dolishop.lib.php
 *	\ingroup	dolishop
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function dolishopAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("dolishop@dolishop");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/dolishop/admin/dolishop_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/dolishop/admin/dolishop_setup_product.php", 1);
    $head[$h][1] = $langs->trans("PsProducts");
    $head[$h][2] = 'products';
    $h++;
    $head[$h][0] = dol_buildpath("/dolishop/admin/dolishop_setup_order.php", 1);
    $head[$h][1] = $langs->trans("PsOrders");
    $head[$h][2] = 'orders';
    $h++;
    $head[$h][0] = dol_buildpath("/dolishop/admin/dolishop_setup_stock.php", 1);
    $head[$h][1] = $langs->trans("PsStocks");
    $head[$h][2] = 'stocks';
    $h++;
//    $head[$h][0] = dol_buildpath("/dolishop/admin/dolishop_setup_stock.php", 1);
//    $head[$h][1] = $langs->trans("Parameters");
//    $head[$h][2] = 'stocks';
//    $h++;
    $head[$h][0] = dol_buildpath("/dolishop/admin/dolishop_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@dolishop:/dolishop/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@dolishop:/dolishop/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolishop');

    return $head;
}

function dolishop_get_tree($arbo, $level=0, $default_bg='#AAA')
{
	$html = '<ul class="'.($level == 0 ? 'treeview' : '').'">'.PHP_EOL;

	$last_k = count($arbo) - 1;
	foreach ($arbo as $k => $cat)
	{
		$html.= '<li class="liuseractive '.($k == $last_k ? 'last' : '').'">'.PHP_EOL;
		$html.= '<span class="noborderoncategories classfortooltip categtextwhite" style="background: '.(empty($cat['need_to_create']) ? $default_bg : '#7f0000').';">'.$cat['label'].'</span>'.PHP_EOL;
		
		if (!empty($cat['children'])) $html.= dolishop_get_tree ($cat['children'], ++$level, empty($cat['need_to_create']) ? $default_bg : '#7f0000');
		
		$html.= '</li>'.PHP_EOL;
	}

	$html.= '</ul>'.PHP_EOL;
	return $html;
}