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
 * \file    class/actions_dolishop.class.php
 * \ingroup dolishop
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsdolishop
 */
class Actionsdolishop
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		return 0;
	}
	
	/**
	 * Overloading the deleteFile function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function deleteFile($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$conf;
		
		$TContext = explode(':', $parameters['context']);
		if (in_array('productdocuments', $TContext) && !empty($conf->global->DOLISHOP_SYNC_PRODUCTS_IMAGES))
		{
			if (is_object($object) && empty($object->array_options)) $object->fetch_optionals();
			if (!empty($object->array_options['options_ps_id_product']))
			{
				dol_include_once('/dolishop/class/dolishop.class.php');
				
				$explode = explode('/', $parameters['file']);
				$filename = $explode[count($explode)-1];
				
				$dolishop = new \Dolishop\Dolishop($this->db);
				$res = $dolishop->deletePsProductImages($object, array($filename));
				if ($res >= 1) setEventMessage($langs->trans('DolishopDeletePsProductImagesSuccess'));
				else if ($res <= -1) setEventMessage($langs->trans('DolishopDeletePsProductImagesWarning'), 'warnings');
				
				if (!empty($dolishop->error)) setEventMessage($dolishop->error, 'errors');
			}
		}
		
		return 0;
	}
	
	/**
	 * Overloading the formattachOptions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function formattachOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$conf;
		
		$TContext = explode(':', $parameters['context']);
		if (in_array('productdocuments', $TContext))
		{
			// Obligé de gérer ça ici, pas de doActions :'(
			// Vivement que ce soit gérable via la class EcmFiles avec des triggers du style : CREATE_ECM_FILE / MODIFY_ECM_FILE / DELETE_ECM_FILE
			if (!empty($conf->global->DOLISHOP_SYNC_PRODUCTS_IMAGES) && GETPOST('sendit'))
			{
				global $upload_dir, $upload_dirold;
				
				$dir = !empty($upload_dirold) ? $upload_dirold : $upload_dir;
				$TFileName = $_FILES['userfile']['name'];
				if (!is_array($TFileName)) $TFileName = array($TFileName);
				
				dol_include_once('/dolishop/class/dolishop.class.php');
				$dolishop = new \Dolishop\Dolishop($this->db);
				$dolishop->addPsProductImages($object, $TFileName, $dir);
				if (!empty($dolishop->error)) setEventMessage($dolishop->error, 'errors');
				else setEventMessage($langs->trans('DolishopAddPsProductImagesSuccess'));
			}
			
		}
		
		return 0;
	}
}