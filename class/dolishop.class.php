<?php


require_once __DIR__.'/PSWebServiceLibrary.php';

class Dolishop
{
	public static $webService = null;
	public static $TLanguage = null;
	
	public $error;
	public $errors = array();
	
	public $result_xml;
	
	public $schema_products_blank;
	public $schema_products_synopsis;

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
		
		// TODO remove
//		$sql.= ' WHERE p.rowid = 4';
		
		
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
	 * Synchronise les objets vers Prestashop
	 * Méthode utilisée par la tâche cron déclarée dans Dolibarr à l'activation du module
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
		
		// TODO ... à venir
	}
	
	/**
	 * Synchronise les produits sur la boutique Prestashop
	 */
	private function rsyncProducts()
	{
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		
		$dol_product = new Product($this->db);
		// Je récupère $optionsArray ici pour le faire qu'une seule fois, autrement la méthode "fetch_optionnals()" referait le travail à chaque itération...
		$extrafields = new ExtraFields($this->db);
		if (empty($extrafields->attributes[$dol_product->table_element]['loaded'])) $extrafields->fetch_name_optionals_label($dol_product->table_element);
		$optionsArray = $extrafields->attributes[$dol_product->table_element]['label'];

		$this->getSchema('products', 'synopsis'); // Load schema pour les add / edit
	
		$ProductId = $this->getTProductIdToSync();
		foreach ($ProductId as $fk_product)
		{
			$dol_product = new Product($this->db);
			if ($dol_product->fetch($fk_product) > 0)
			{
				if (empty($dol_product->array_options)) $dol_product->fetch_optionals(null, $optionsArray);

				if (!empty($dol_product->array_options['options_ps_id_product']))
				{
					$opt = array('resource' => 'products', 'filter[id]' => '['.$dol_product->array_options['options_ps_id_product'].']');
					$alt_opt = array('resource' => 'products', 'filter[reference]' => '['.$dol_product->ref.']');
				}
				else
				{
					$opt = array('resource' => 'products', 'filter[reference]' => '['.$dol_product->ref.']');
					$alt_opt = array();
				}
				
				$xml_origin = $this->findPsProductResource($opt, $alt_opt);
				
				if ($xml_origin !== false) $this->savePsProduct($dol_product, $xml_origin);
				else $this->savePsProduct($dol_product);
			}
		}
	}
	
	/**
	 * Fait un appel au webservice Prestashop pour trouver un produit avec $opt ou $opt_alt comme critères
	 * Attention : cette méthode n'est pas prévu pour fonctionner avec l'argument $opt['id'], car si le produit n'existe pas
	 *				une erreur 404 est remontée et n'essai pas avec $opt_alt
	 * 
	 * Si occurrence trouvée == 1, alors je renvoi $resources
	 * Si occurrence trouvée == 0 OU > 1, je renvoi false
	 * 
	 * @param array $opt
	 * @param array $alt_opt
	 * @return SimpleXMLElement|boolean
	 */
	private function findPsProductResource($opt, $alt_opt=array())
	{
		$error = false;
		try
		{
			if (!isset($opt['limit'])) $opt['limit'] = 2; // ça m'évite de charger des résultats dont je n'ai pas besoin
			if (!isset($opt['display'])) $opt['display'] = 'full'; // je souhaite récupérer la totalité des champs pour un éventuel update
			$this->result_xml = self::$webService->get($opt);
			$resources = $this->result_xml->children();
		}
		catch (PrestaShopWebserviceException $e)
		{
			$error = $this->setError($e);
		}
		
		if (!$error)
		{
			// Nombre d'occurrence ("product") dans "products"
			if ($resources->children()->count() == 1) return $this->result_xml;
			else if (!empty($alt_opt)) return $this->findPsProductResource($alt_opt);
		}
		
		return false;
	}
	
	/**
	 * Méthode qui se charge de faire appel au add() ou edit() du webservice
	 * 
	 * @global type $mysoc
	 * @param type $dol_product
	 * @param type $xml_origin
	 */
	private function savePsProduct(&$dol_product, $xml_origin=false)
	{
		global $mysoc;
		
		if ($xml_origin !== false) $schema = $xml_origin->children();
		else $schema = clone $this->schema_products_synopsis;

		// Pour comprendre pourquoi tant de children(), faire un print $schema->asXML()
		$ps_product = $schema->children()->children();
		
		// unset des champs qui sont en readOnly et qu'il faut retirer du xml pour l'envoi au webservice
		foreach ($this->schema_products_synopsis->children()->children() as $nodeKey => $node)
		{
			if ( 
				isset($node->attributes()->readOnly) && $node->attributes()->readOnly == true
				|| isset($node->attributes()->read_only) && $node->attributes()->read_only == true)
			{
				unset($ps_product->$nodeKey);
			}
		}
//		var_dump($ps_product);exit;

		$ps_product->reference = $dol_product->ref;
		$ps_product->price =  $dol_product->price;
		$ps_product->state =  1;
		
		foreach ($ps_product->name->children() as $language)
		{
			// TODO gérer ici le multi-langs
			if (self::$TLanguage->{$mysoc->country_code}->id == $language->attributes()->id)
			{
				$language[0] = $dol_product->label;
			}
		}
//		var_dump($ps_product->name->asXML());exit;
		
		$error = false;
		try
		{
			if (!empty($ps_product->id))
			{
//				echo '<pre>'.htmlspecialchars($xml_origin->asXML(), ENT_QUOTES);exit;
				$opt = array('resource' => 'products',  'putXml' => $schema->asXML(), 'id' => $ps_product->id);
				$this->result_xml = self::$webService->edit($opt);
			}
			else
			{
//				echo '<pre>'.htmlspecialchars($schema->asXML(), ENT_QUOTES);exit;
				$opt = array('resource' => 'products',  'postXml' => $schema->asXML());
				$this->result_xml = self::$webService->add($opt);
			}
			
			$ps_product_return = $this->result_xml->children()->children();
		}
		catch (PrestaShopWebserviceException $e)
		{
			$error = $this->setError($e);
		}

		if (!$error)
		{
			$ps_id_product_return = (int) $ps_product_return->id;
			// Si j'ai fais un update, pas besoin de surcharger avec un save côté extrafields
			if ($dol_product->array_options['options_ps_id_product'] != $ps_id_product_return)
			{
				$dol_product->array_options['options_ps_id_product'] = $ps_id_product_return;
				$dol_product->insertExtraFields();
			}
		}
	}
	
	
	/**
	 * Load le schema d'une ressource dans un attribut de l'objet courant sous le format : schema_[$resourcename]_[$type]
	 * 
	 * @global type $conf
	 * @param string	$resourcename	nom de la ressource(products, customers, ... @see admin Prestashop, définition d'une clé webservice (pour la liste complète)
	 * @param string	$type			blank || synopsis
	 * @return boolean
	 */
	public function getSchema($resourcename, $type='synopsis')
	{
		global $conf;
		
		$error = false;
		try
		{
			// "blank" renvoi une sorte de page blanche prête à l'emploi sans les champs 
			// , alors que "synopsis" donne plus d'infos (type, filtrable, readOnly, ...)
			$opt = array('url' => $conf->global->DOLISHOP_PS_SHOP_PATH.'/api/'.$resourcename.'?schema='.$type);
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
			$this->{'schema_'.$resourcename.'_'.$type} = $schema;
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