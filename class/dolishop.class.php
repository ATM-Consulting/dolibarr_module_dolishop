<?php


require_once __DIR__.'/PSWebServiceLibrary.php';

class Dolishop
{
	public static $webService = null;
	public static $TLanguage = null;
	
	public $error;
	public $errors = array();
	
	public $result_xml;
	
	public $schema_products;

	public function __construct($db)
	{
		global $conf;
		
		$this->db = $db;
		
		if (is_null(self::$webService)) self::$webService = new PrestaShopWebservice($conf->global->DOLISHOP_PS_SHOP_PATH, $conf->global->DOLISHOP_PS_WS_AUTH_KEY, (bool) $conf->global->DOLISHOP_PS_WS_DEBUG);
		if (is_null(self::$TLanguage)) self::$TLanguage = json_decode($conf->global->DOLISHOP_PS_LANGUAGES);
	}
	
	
	/**
	 * Test de connectivité avec la boutique Prestashop
	 * 
	 * @global type $langs
	 * @return boolean
	 */
	public function testConnection()
	{
		global $langs;
		
		try
		{
			$opt = array('resource' => '');
			$this->result_xml = self::$webService->get($opt);
		}
		catch (PrestaShopWebserviceException $e)
		{
			$this->setError($e);
		}
		
		return !empty($this->result_xml->api);
	}
	
	/**
	 * Methode permettant de récupérer les langues de Prestashop avec leurs ID pour les stocker en conf (DOLISHOP_PS_LANGUAGES)
	 * (doit être appelée si la configuration des langues évolue sur Prestashop)
	 * 
	 * @global type $langs
	 * @return boolean
	 */
	public function syncPsLanguages()
	{
		global $langs,$conf;
		
		try
		{
			$opt = array('resource' => 'languages', 'display' => 'full');
			$this->result_xml = self::$webService->get($opt);
			$resources = $this->result_xml->children();
		}
		catch (PrestaShopWebserviceException $e)
		{
			$this->setError($e);
		}
		
		if (empty($this->errors))
		{
			$TLang = array();
			foreach ($resources->children() as $l)
			{
				$TLang[strtoupper($l->iso_code)] = array(
					'id' => (int) $l->id
					,'name' => (string) $l->name
					,'iso_code' => (string) $l->iso_code
					,'active' => (int) $l->active
				);
			}

			$res = dolibarr_set_const($this->db, 'DOLISHOP_PS_LANGUAGES', json_encode($TLang));
			if ($res > 0)
			{
				self::$TLanguage = json_decode($conf->global->DOLISHOP_PS_LANGUAGES);
				return true;
			}
			else $this->errors[] = $this->db->lasterror();
		}
		
		return false;
	}
	
	
	
	/**
	 * TODO à voir si la méthode reste encore utile
	 * @return type
	 */
	private function getMinMaxPsIdProduct()
	{
		$sql = 'SELECT MIN(pe.ps_id_product) as min_ps_id_product, MAX(pe.ps_id_product) as max_ps_id_product';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'product_extrafields pe';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'product p ON (p.rowid = pe.fk_object)';
		$sql.= ' WHERE p.entity = '.$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql) > 0)
			{
				$obj = $this->db->fetch_object($resql);
				return array($obj->min_ps_id_product, $obj->max_ps_id_product);
			}
		}
		else
		{
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
		}

		return array(null, null);
	}
	
	
	/**
	 * TODO prévoir d'ajouter une restriction du style sur un tag/categ pour éviter d'envoyer toute la base
	 * Renvoie un tableau contenant les ID produit à synchroniser
	 * 
	 * @return array
	 */
	public function getTProductIdToSync()
	{
		$TId = array();
		$sql = 'SELECT DISTINCT p.rowid FROM '.MAIN_DB_PREFIX.'product p';
//		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_product = p.rowid)' // TODO restriction par tags/categories
//		$sql.= ' ...';
		
		$resql = $this->db->query($sql);
		if ($resql)
		{
			while ($arr = $this->db->fetch_array($resql))
			{
				$TId[] = $arr['rowid'];
			}
		}
		else
		{
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
		}
		
		return $TId;
	}
	
	
	/**
	 * Permet de récupérer l'id Prestashop d'un objet Dolibarr qui correspond au(x) critère(s) de recherche
	 * Méthode utilisé par la tâche cron déclaré dans Dolibarr à l'activation du module
	 * 
	 * @global type $conf
	 */
	public function rsync()
	{
		global $conf;
		
		dol_include_once('/dolishop/class/dolishop.class.php');
		
		if (!empty($conf->global->DOLISHOP_SYNC_PRODUCTS))
		{
			$this->rsyncProducts();
			
			
		}
		
		
		exit;
		
	}
	
	
	private function rsyncProducts()
	{
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		
		$product = new Product($this->db);
		// Je récupère $optionsArray ici pour le faire qu'une seule fois, autrement la méthode "fetch_optionnals()" referait le travail à chaque itération...
		$extrafields = new ExtraFields($this->db);
		if (empty($extrafields->attributes[$product->table_element]['loaded'])) $extrafields->fetch_name_optionals_label($product->table_element);
		$optionsArray = $extrafields->attributes[$product->table_element]['label'];

		$this->getSchema('products'); // Load schema pour les add
	
		$ProductId = $this->getTProductIdToSync();
		foreach ($ProductId as $fk_product)
		{
			$product = new Product($this->db);
			if ($product->fetch($fk_product) > 0)
			{
				if (empty($product->array_options)) $product->fetch_optionals(null, $optionsArray);

				$error = false;
				try
				{
					$opt = array('resource' => 'products', 'schema' => 'synopsis');
					if (!empty($product->array_options['options_ps_id_product'])) $opt['id'] = $product->array_options['options_ps_id_product'];
					else $opt['filter[reference]'] = '['.$product->ref.']';
//var_dump($opt);
					$this->result_xml = self::$webService->get($opt);
					$resources = $this->result_xml->children()->children();
				}
				catch (PrestaShopWebserviceException $e)
				{
					$error = $this->setError($e);
				}
				
				if (!$error) // pas de test sur $this->error, car à la 1ère erreur, c'est le restant qui ne sera pas synchro
				{
					$nb_products = count($resources->product);
//					var_dump($nb_products, $resources);exit;
					if ($nb_products === 1)
					{
						// update [edit()]
					}
					else if ($nb_products === 0)
					{
						// create [add()]
						$this->addProduct($product);
exit;
					}
					else if ($nb_products > 1)
					{
						// Aie, plusieurs occurrences
					}


				}
			}


		}
		
exit;
	}
	
	
	/**
	 * TODO à voir si je renome pas ça en saveProduct (pour gérer l'update)
	 */
	private function addProduct(&$product)
	{
		global $mysoc;
		
		$schema = clone $this->schema_products;
		$ps_product = $schema->product;
//		var_dump($ps_product);exit;

		$ps_product->reference = $product->ref;
		$ps_product->price =  9.90;
		$ps_product->state =  1;

		foreach ($ps_product->name->children() as $language)
		{
			// TODO gérer ici le multi-langs
			if (self::$TLanguage->{$mysoc->country_code}->id == $language->attributes()->id)
			{
				$language[0] = $product->label;
			}
		}

		$error = false;
		try
		{
			$opt = array('resource' => 'products',  'postXml' => $schema->asXML());
//			echo '<pre>'.htmlspecialchars($schema->asXML(), ENT_QUOTES);exit;
			$this->result_xml = self::$webService->add($opt);
			$ps_product_return = $this->result_xml->children()->children();
		}
		catch (PrestaShopWebserviceException $e)
		{
			$error = $this->setError($e);
		}

		if (!$error)
		{
			$product->array_options['options_ps_id_product'] = (int) $ps_product_return->id;
			$product->insertExtraFields();
		}
	}
	
	
	
	public function getSchema($resourcename)
	{
		global $conf;
		
		$error = false;
		try
		{
			$opt = array('url' => $conf->global->DOLISHOP_PS_SHOP_PATH.'/api/'.$resourcename.'?schema=blank'); // blank, synopsis
			$this->result_xml = self::$webService->get($opt);
			$schema = $this->result_xml;
		}
		catch (PrestaShopWebserviceException $e)
		{
			$error = $this->setError($e);
		}
		
		if (!$error)
		{
			// products ...
			$this->{'schema_'.$resourcename} = $schema;
			return true;
		}
		
		return false;
	}
	
	
	/**
	 * Méthode qui valorise simplement les attributs "error" et "errors" de l'objet courant 
	 * si une erreur lors d'un appel au webservice est remontée
	 * 
	 * @global type $langs
	 * @param PrestaShopWebserviceException $e
	 */
	private function setError(PrestaShopWebserviceException $e)
	{
		global $langs;
		
		$trace = $e->getTrace();
		if ($trace[0]['args'][0] == 404) $this->error = $langs->trans('DolishopErrorWs404');
		else if ($trace[0]['args'][0] == 401) $this->error = $langs->trans('DolishopErrorWsBadAuthKey');
		else $this->error = $langs->trans('DolishopErrorWsUnknown', $e->getMessage());

		$this->errors[] = $this->error;
		
		return true;
	}
	
	
	/**
	 * Renvoie le contenu de self::TLanguage dans un format HTML pour être print
	 * 
	 * @return string
	 */
	public function getFormatedStringTLanguage()
	{
		$str = '<ul style="margin:0;padding:0;">';
		
		if (!empty(self::$TLanguage))
		{
			foreach (self::$TLanguage as $code => $Tab)
			{
				$str.= '<li>'.$code. ' : '.implode('; ', array_map(
					function ($v, $k) { return sprintf("[%s]=%s", $k, $v); }
					,(array)$Tab, array_keys((array)$Tab)
				)) . '</li>';
			}
		}
		
		$str.= '</ul>';
		return $str;
	}
	
}