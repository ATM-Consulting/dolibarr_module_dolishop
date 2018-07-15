<?php


require_once __DIR__.'/PSWebServiceLibrary.php';

class Dolishop
{
	private static $webService = null;
	
	public static $TLanguage = null;
	public static $TTaxe = null;
	
	public $error;
	public $errors = array();
	
	public $from_cron_job = false;
	
	public $result_xml;
	
	public $schema_products_blank;
	public $schema_products_synopsis;

	public function __construct($db)
	{
		global $conf;
		
		$this->db = $db;
		
		if (is_null(self::$webService)) self::$webService = new PrestaShopWebservice($conf->global->DOLISHOP_PS_SHOP_PATH, $conf->global->DOLISHOP_PS_WS_AUTH_KEY, (bool) $conf->global->DOLISHOP_PS_WS_DEBUG);
		if (is_null(self::$TLanguage)) self::$TLanguage = json_decode($conf->global->DOLISHOP_PS_LANGUAGES);
		if (is_null(self::$TTaxe)) self::$TTaxe = json_decode($conf->global->DOLISHOP_PS_TAXES);
	}
	
	
	/**
	 * Test de connectivité avec la boutique Prestashop
	 * 
	 * @return boolean
	 */
	public function testConnection()
	{
		$r = $this->getAll('');
		if ($r !== false) return $r->attributes()->shopName->__toString();
		return false;
	}
	
	/**
	 * Methode permettant de récupérer des configurations de Prestashop pour le bon fonctionnement du module
	 *  les langues	: DOLISHOP_PS_LANGUAGES
	 *  les taxes	: DOLISHOP_PS_TAXES
	 * (doit être appelée si la configuration évolue sur Prestashop)
	 * 
	 * @return boolean
	 */
	public function syncPsConf()
	{
		global $conf;
		
		$languages = $this->getAll('languages', array('display' => 'full'));
		if ($languages->children()->count() > 0)
		{
			$TLang = array();
			foreach ($languages->children() as $l)
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
				self::$TLanguage = $TLang;
			}
			else $this->errors[] = $this->db->lasterror();
		}
		
		$taxes = $this->getAll('taxes', array('display' => 'full'));
		$tax_rules = $this->getAll('tax_rules', array('display' => 'full'));
//		$tax_rule_groups = $this->getAll('tax_rule_groups', array('display' => 'full'));
		
//		var_dump($tax_rules->children());exit;
		
		if ($taxes->children()->count() > 0 && $tax_rules->children()->count() > 0)
		{
			$TTaxe = array();
			foreach ($taxes->children() as $taxe)
			{
				$id_tax = (int) $taxe->id;
				$vat_rate = (float) $taxe->rate; // Cast en float pour retirer les 0 à la fin
				if (!isset($TTaxe[(string) $vat_rate])) $TTaxe[(string) $vat_rate] = array('TLabel' => array(), 'TId_tax' => array(), 'TId_tax_rules_group' => array());
				$TTaxe[(string) $vat_rate]['TLabel'][$id_tax] = $taxe->name->language->__toString();
				$TTaxe[(string) $vat_rate]['TId_tax'][$id_tax] = $id_tax;
				
//				var_dump($id_tax);exit;
				foreach ($tax_rules->children() as $tax_rule)
				{
					if ((int) $tax_rule->id_tax == $id_tax)
					{
						$TTaxe[(string) $vat_rate]['TId_tax_rules_group'][$id_tax] = (int) $tax_rule->id_tax_rules_group;
						break;
					}
				}
				
			}
			
			$res = dolibarr_set_const($this->db, 'DOLISHOP_PS_TAXES', json_encode($TTaxe));
			if ($res > 0)
			{
//				self::$TLanguage = json_decode($conf->global->DOLISHOP_PS_TAXES);
				self::$TTaxe = $TTaxe;
			}
			else $this->errors[] = $this->db->lasterror();
		}
		
		
		
		if (empty($this->errors)) return true;
		return false;
	}
	
	
	/**
	 * Renvoie un tableau contenant les ID produit à synchroniser
	 * 
	 * @return array
	 */
	public function getTProductIdToSync()
	{
		global $conf;
		
		$TId = array();
		
		if (empty($conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES) && empty($conf->global->DOLISHOP_SYNC_PRODUCTS_RECK_CONF)) return $TId;
		
		$sql = 'SELECT DISTINCT p.rowid FROM '.MAIN_DB_PREFIX.'product p';
		if (empty($conf->global->DOLISHOP_SYNC_PRODUCTS_RECK_CONF))
		{
			$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_product = p.rowid)'; // TODO restriction par tags/categories
			$sql.= ' WHERE cp.fk_categorie IN ('.$conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES.')';
		}
		
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
		
		$this->from_cron_job = true;
		
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		
		if (!empty($conf->global->DOLISHOP_SYNC_PRODUCTS))
		{
			$ProductId = $this->getTProductIdToSync();
			$this->rsyncProducts($ProductId);
		}
		
		// TODO ... à venir
	}
	
	/**
	 * Synchronise les produits vers la boutique Prestashop
	 */
	public function rsyncProducts($ProductId)
	{
		if (empty($ProductId)) return 0;
		
		$this->getSchema('products', 'synopsis'); // Load schema pour les add / edit
		
		foreach ($ProductId as $fk_product)
		{
			$dol_product = new Product($this->db);
			if ($dol_product->fetch($fk_product) > 0)
			{
				if (empty($dol_product->array_options)) $dol_product->fetch_optionals();
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
		
		return 1;
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
//		$ps_product->id_tax_rules_group =  $dol_product->tva_tx;
		$ps_product->state =  1;
		$ps_product->weight =  $dol_product->weight; // TODO voir pour l'unité de mesure
		// dans Dolibarr  c'est la notion de LLH et sur Prestashop c'est LHP, à voir s'il y a vraiement une différence
		$ps_product->width =  $dol_product->length; // TODO voir pour l'unité de mesure
		$ps_product->height =  $dol_product->height; // TODO voir pour l'unité de mesure
		$ps_product->depth =  $dol_product->width; // TODO voir pour l'unité de mesure

		$ps_product->active =  $dol_product->status; // 1 = en vente donc à activer sur prestashop
		$ps_product->available_for_order =  $dol_product->status; // de même pour sa disponibilité sur la boutique
		$ps_product->show_price =  $dol_product->status; // de même pour afficher le prix sur la boutique
		$ps_product->redirect_type =  '404';
		
		$ps_product->low_stock_threshold = $dol_product->seuil_stock_alerte;
		
		// wholesale_price => prix d'achat
//		var_dump($dol_product);exit;
//		echo '<pre>'.htmlspecialchars(print_r($dol_product), ENT_QUOTES);exit;
		
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
//				echo '<pre>'.htmlspecialchars($schema->asXML(), ENT_QUOTES);exit;
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
				$dol_product->updateExtraField('ps_id_product');
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
		
		if (empty($this->{'schema_'.$resourcename.'_'.$type})) 
		{
			try
			{
				$opt = array('url' => $conf->global->DOLISHOP_PS_SHOP_PATH.'/api/'.$resourcename.'?schema='.$type);
				$this->result_xml = self::$webService->get($opt);
				$this->{'schema_'.$resourcename.'_'.$type} = $this->result_xml;

				return $this->{'schema_'.$resourcename.'_'.$type};
			}
			catch (PrestaShopWebserviceException $e)
			{
				$this->setError($e);
				return false;
			}
		}
		
		return $this->{'schema_'.$resourcename.'_'.$type};
	}
	
	
	/**
	 * Permet de retourner une liste de ressource
	 * 
	 * @param string	$resource_name	Nom de la ressource Prestashop
	 * @param array		$more_opt		Tableau d'option complémentaire pour la requête ('filter', 'display', 'sort', 'limit', 'id_shop', 'id_group_shop')
	 * @return SimpleXmlElement | boolean
	 */
	public function getAll($resource_name, $more_opt=array())
	{
		try
		{
			$opt = array('resource' => $resource_name, 'display' => 'full');
			if (!empty($more_opt)) $opt+= $more_opt;
			$this->result_xml = self::$webService->get($opt);
			return $this->result_xml->children();
		}
		catch (PrestaShopWebserviceException $e)
		{
			$this->setError($e);
		}
		
		return false;
	}
	
	/**
	 * Retourne un objet en particulier via son identifiant
	 * 
	 * @param string	$resource_name	Nom de la ressource Prestashop
	 * @param int		$id				Id de l'objet à charger
	 * @return SimpleXmlElement | boolean
	 */
	public function getOne($resource_name, $id)
    {
		try
		{
			$this->result_xml = self::$webService->get(array('resource' => $resource_name, 'id' => $id));
			return $this->result_xml->children();
		}
		catch (PrestaShopWebserviceException $e)
		{
			$this->setError($e);
		}
		
		return false;
    }
	
	
	/**
	 * Méthode qui valorise simplement les attributs "error" et "errors" de l'objet courant 
	 * si une erreur lors d'un appel au webservice est remontée
	 * 
	 * @global Translate $langs
	 * @param PrestaShopWebserviceException $e
	 * @return boolean
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
	 * Renvoie le contenu de self::TLanguage, self::TTaxe dans un format HTML pour être print
	 * 
	 * @global Translate $langs
	 * @return string
	 */
	public function getFormatedStringTConf()
	{
		global $langs;
		
		$str = '';
		
		if (!empty(self::$TLanguage))
		{
			$str.= '<div class="titre inline-block" style="font-weight:bold;">'.$langs->trans('DolishopPsLanguages').'</div>';
			$str.= '<table class="noborder" width="100%">';
			$str.= '<tr class="liste_titre">
						<th align="center" width="10%">id</th>
						<th>code</th>
						<th align="center">iso</th>
						<th align="center">Active</th>
					</tr>';
			foreach (self::$TLanguage as $code => $Tab)
			{
				$str.= '<tr class="oddeven">';
				
				$str.= '<td align="center">'.$Tab->id.'</td>';
				$str.= '<td>'.$code.'</td>';
				$str.= '<td align="center">'.$Tab->iso_code.'</td>';
				$str.= '<td align="center">'.$Tab->active.'</td>';

				$str.= '</tr>';
			}
			$str.= '</table>';
		}
		
		if (!empty(self::$TTaxe))
		{
			if (!empty($str)) $str.= '<br /><br />';
			$str.= '<div class="titre inline-block" style="font-weight:bold;">'.$langs->trans('DolishopPsTaxes').'</div>';
			$str.= '<table class="noborder " width="100%">';
			$str.= '<tr class="liste_titre">
						<th align="center" width="10%">%</th>
						<th></th>
						<th align="center">id_tax</th>
						<th align="center">id_tax_rules_group</th>
					</tr>';
			foreach (self::$TTaxe as $vat_rate => $Tab)
			{
				$str.= '<tr class="oddeven">';

				$str.= '<td align="center">'.$vat_rate.'</td>';
				$str.= '<td>';
				foreach ($Tab->TLabel as $label) $str.= $label.'<br />';
				$str.= '</td>';
				$str.= '<td align="center">';
				foreach ($Tab->TId_tax as $id_tax) $str.= $id_tax.'<br />';
				$str.= '</td>';
				$str.= '<td align="center">';
				foreach ($Tab->TId_tax_rules_group as $id_tax_rules_group) $str.= $id_tax_rules_group.'<br />';
				$str.= '</td>';
				
				$str.= '</tr>';
			}
			$str.= '</table>';
		}
		
		
		return $str;
	}
	
}