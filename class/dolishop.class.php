<?php

namespace Dolishop;


if (!class_exists('SeedObject'))
{
	define('INC_FROM_DOLIBARR', true);
	require_once __DIR__.'/../config.php';
}


require_once __DIR__.'/PSWebServiceLibrary.php';

class Dolishop
{
	private static $webService = null;
	
	private $url;
	private $key;
	private $debug;
	
	public static $TLanguage = null;
	public static $TTaxe = null;
	
	public $error;
	public $errors = array();
	
	public $from_cron_job = false;
	
	public $result_xml;
	
	public $schema_products_blank;
	public $schema_products_synopsis;
	
	public $TProductCategoryIdSync = array();

	public function __construct($db)
	{
		global $conf,$langs;
		
		$this->db = $db;
		
		$langs->load('dolishop@dolishop');
		
		$this->url = $conf->global->DOLISHOP_PS_SHOP_PATH;
		$this->key = $conf->global->DOLISHOP_PS_WS_AUTH_KEY;
		$this->debug = (bool) $conf->global->DOLISHOP_PS_WS_DEBUG;
		
		if (is_null(self::$webService)) self::$webService = new \Dolishop\PrestaShopWebservice($this->url, $this->key, $this->debug);
		if (is_null(self::$TLanguage)) self::$TLanguage = json_decode($conf->global->DOLISHOP_PS_LANGUAGES);
		if (is_null(self::$TTaxe)) self::$TTaxe = json_decode($conf->global->DOLISHOP_PS_TAXES);
		
		$this->TProductCategoryIdSync = explode(',', $conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES);
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
	 * @global Conf $conf
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
	 * @global Conf $conf
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
			$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_product = p.rowid)'; // restriction par tags/categories
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
	 * @global Conf $conf
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
	 * * Synchronise les produits vers la boutique Prestashop
	 * 
	 * @param array $ProductId
	 * @return int
	 */
	public function rsyncProducts($ProductId)
	{
		if (empty($ProductId)) return 0;
		
		$this->getSchema('products', 'synopsis'); // Load schema pour les add / edit
		
		foreach ($ProductId as $fk_product)
		{
			$dol_product = new \Product($this->db);
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
	 * @return \SimpleXMLElement | boolean
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
	 * @global Societe		$mysoc
	 * @global Translate	$langs
	 * @param	Product				$dol_product
	 * @param	\SimpleXMLElement	$xml_origin
	 */
	private function savePsProduct(&$dol_product, $xml_origin=false)
	{
		global $mysoc,$langs;
		
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
			$res = 1;
			$ps_id_product_return = (int) $ps_product_return->id;
			// Si j'ai fais un edit(), pas besoin de surcharger avec un update côté extrafields
			if ($dol_product->array_options['options_ps_id_product'] != $ps_id_product_return)
			{
				$need_insert = false;
				if (empty($dol_product->array_options)) $need_insert = true;
		
				$dol_product->array_options['options_ps_id_product'] = $ps_id_product_return;
				
				if ($need_insert) $res = $dol_product->insertExtraFields();
				else $res = $dol_product->updateExtraField('ps_id_product');
			}
			
			if ($this->from_cron_job)
			{
				if ($res > 0) $this->output.= $langs->trans('DolishopCronjob_SyncProductSuccess', $dol_product->ref, $ps_id_product_return)."\n";
				else $this->output.= $langs->trans('DolishopCronjob_SyncProductFailUpdateExtrafield', $dol_product->ref, $ps_id_product_return)."\n";
			}
		}
		else
		{
			if ($this->from_cron_job) $this->output.= $langs->trans('DolishopCronjob_SyncProductError', $this->error)."\n";
		}
	}
	
	
	/**
	 * Load le schema d'une ressource dans un attribut de l'objet courant sous le format : schema_[$resourcename]_[$type]
	 * 
	 * @global Conf $conf
	 * @param string	$resourcename	nom de la ressource("addresses", "carriers", "cart_rules", "carts", "categories", "combinations", "configurations", "contacts", "content_management_system", "countries", "currencies", "customer_messages", "customer_threads", "customers", "customizations", "deliveries", "employees", "groups", "guests", "image_types", "images", "languages", "manufacturers", "messages", "order_carriers", "order_details", "order_histories", "order_invoices", "order_payments", "order_slip", "order_states", "orders", "price_ranges", "product_customization_fields", "product_feature_values", "product_features", "product_option_values", "product_options", "product_suppliers", "products", "search", "shop_groups", "shop_urls", "shops", "specific_price_rules", "specific_prices", "states", "stock_availables", "stock_movement_reasons", "stock_movements", "stocks", "stores", "suppliers", "supply_order_details", "supply_order_histories", "supply_order_receipt_histories", "supply_order_states", "supply_orders", "tags", "tax_rule_groups", "tax_rules", "taxes", "translated_configurations", "warehouse_product_locations", "warehouses", "weight_ranges", "zones")
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
	 * @return \SimpleXMLElement | boolean
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
	 * @return \SimpleXMLElement | boolean
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
	 * Méthode qui vérifie si le fk_product fait bien partie de/des catégories produits à synchroniser
	 * 
	 * @param int		$fk_product
	 * @return boolean
	 */
	public function checkProductCategories($fk_product)
	{
		if (!class_exists('Categorie')) require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		
		$category = new \Categorie($this->db);
		$TCategory = $category->getListForItem($fk_product, \Categorie::TYPE_PRODUCT);
		if (is_array($TCategory))
		{
			foreach ($TCategory as $cat)
			{
				if (in_array($cat['id'], $this->TProductCategoryIdSync)) return true;
			}	
		}
		
		return false;
	}
	
	/**
	 * Méthode qui upload count($TFileName) image(s) vers Prestashop pour un produit Dolibarr déjà synchonisé
	 * 
	 * @global User $user
	 * @param Product	$dol_product
	 * @param array		$TFileName
	 * @param string	$dir
	 * @return int		1 = OK; 0 = RAF; -1 = Erreur, mais l'envoi peut être partiel
	 */
	public function addPsProductImages(&$dol_product, $TFileName, $dir)
	{
		global $user;
		
		if (!$this->checkProductCategories($dol_product->id)) return 0;
		
		$xml_images = $this->getAll('images');
		$TMimeTypeAllowed = explode(', ', $xml_images->children()->products->attributes()->upload_allowed_mimetypes);

		foreach ($TFileName as $name)
		{
			$info = pathinfo($name);
			$filename = dol_sanitizeFileName($info['filename'].'.'.strtolower($info['extension']));
			$image_path = $dir.'/'.$filename;
			$mime_type = mime_content_type($image_path);
			if (in_array($mime_type, $TMimeTypeAllowed))
			{
				$ecm = new \Dolishop\EcmFilesDolishop($this->db);
				$ecm->fetchByFileNamePath($filename, $dol_product->ref);

				$result = $this->postImage($image_path, 'products', $dol_product->array_options['options_ps_id_product'], $ecm->ps_id_image);
				if ($result === false) return -1;
				else
				{
					$ps_id_image_return = (int) $result->image->id;
					if ($ecm->id > 0 && $ps_id_image_return != $ecm->ps_id_image)
					{
						$ecm->ps_id_image = $ps_id_image_return;
						$ecm->update($user);
					}
				}
			}
		}
		
		return 1;
	}	
	
	/**
	 * Supprimer count($TFileName) image(s) produits de la boutique Prestashop
	 * 
	 * @param Product	$dol_product
	 * @param array		$TFileName
	 * @return int		1 = OK; 0 = RAF; -1 = Echec de la suppression
	 */
	public function deletePsProductImages(&$dol_product, $TFileName)
	{
		if ($this->checkProductCategories($dol_product->id))
		{
			foreach ($TFileName as $filename)
			{
				$ecm = new \Dolishop\EcmFilesDolishop($this->db);
				$ecm->fetchByFileNamePath($filename, $dol_product->ref);

				if ($ecm->ps_id_image > 0)
				{
					$res = $this->delete('images/products/'.$dol_product->array_options['options_ps_id_product'], $ecm->ps_id_image);
					if ($res) return 1;
					else return -1;
				}	
			}
		}
		
		return 0;
	}
	
	/**
	 * Envoi une requête de création ou de mise à jour d'une image
	 * @see http://doc.prestashop.com/display/PS16/Chapter+9+-+Image+management
	 * 
	 * @param string	$image_path		chemin complet de l'image (exemple : /var/www/.../mon_image.png)
	 * @param string	$resource_name	nom de la resource ("general", "products", "categories", "customizations", "manufacturers", "suppliers", "stores") 
	 * @param int		$id_resource	id ressource
	 * @param int		$id_image		id image pour un update
	 * @return \SimpleXMLElement | boolean
	 */
	public function postImage($image_path, $resource_name, $id_resource, $id_image=0)
	{
		global $langs;
		
		$url = $this->url;
		if (substr($url, -1, 1) !== '/') $url.= '/api/images/';
		else $url.= 'api/images/';
		
		$url.= $resource_name.'/'.$id_resource;
		if ($id_image > 0) $url.= '/'.$id_image.'?ps_method=PUT'; // update an existing image

		// php 5.5+ et semble nécessaire pour ne pas avoir un message d'erreur en retour (possiblement dû à ma version PHP7.2, mais cela reste à confirmer)
		if (function_exists('curl_file_create')) $cFile = curl_file_create($image_path);
		else $cFile = '@'.realpath($image_path);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->key.':');
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => $cFile));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close($ch);
		
		$xml_result = new \SimpleXMLElement($result);
		if (isset($xml_result->children()->errors))
		{
			$error = $xml_result->children()->children()[0];
			$this->error = $langs->trans('DolishopPostImageError', $error->code->__toString(), $error->message->__toString());
			$this->errors[] = $this->error;
			return false;
		}
		
		return $xml_result->children();
	}
	
	/**
	 * Suppression d'une ressource
	 * 
	 * @param type $resource_name
	 * @param type $id
	 * @param type $opt_alt
	 * @return boolean
	 */
	public function delete($resource_name, $id, $opt_alt=array())
	{
		try
		{
			$opt = array('resource' => $resource_name, 'id' => $id);
			if (!empty($opt_alt)) $opt+= $opt_alt;
			return self::$webService->delete($opt);
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
	
	public function debugXml($xml)
	{
		print '<pre>'. print_r($xml, true) .'</pre>';
	}
}

require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';

class EcmFilesDolishop extends \SeedObject
{
	public $table_element = 'ecm_files';
	public $element = 'ecmfiles';
	
	public $ref;					// hash of file path
	public $label;					// hash of file content (md5_file(dol_osencode($destfull))
	public $share;					// hash for file sharing, empty by default (example: getRandomPassword(true))
	public $entity;
	public $filename;
	public $filepath;
	public $fullpath_orig;
	public $description;
	public $keywords;
	public $cover;
	public $position;
	public $gen_or_uploaded;       // can be 'generated', 'uploaded', 'unknown'
	public $extraparams;
	public $date_c = '';
	public $date_m = '';
	public $fk_user_c;
	public $fk_user_m;
	public $acl;
	
	public $ps_id_image=0;
	
	public function __construct($db)
	{
		parent::__construct($db);
		
		$this->fields=array(
			'ref'=>array('type'=>'string','length'=>128)
			,'label'=>array('type'=>'string','length'=>128, 'index'=>true)
			,'entity'=>array('type'=>'integer')
			,'filename'=>array('type'=>'string','length'=>255)
			,'filepath'=>array('type'=>'string','length'=>255)
			,'description'=>array('type'=>'text')
			,'keywords'=>array('type'=>'text')
			,'position'=>array('type'=>'integer')

			// Prestashop
			,'ps_id_image'=>array('type'=>'integer', 'index'=>true)
		);
		
		$this->init();
		
	}
	
	public function fetchByFileNamePath($filename, $ref_object)
	{
		global $conf;
		
		$sql = 'SELECT rowid, ps_id_image FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql.= ' WHERE entity = '.$conf->entity;
		$sql.= ' AND filename = \''.$this->db->escape($filename).'\'';
		$sql.= ' AND filepath LIKE \'%'.$this->db->escape($ref_object).'\'';
		
		$resql = $this->db->query($sql);
		if ($resql)
		{
			if (($obj = $this->db->fetch_object($resql)))
			{
				$this->fetch($obj->rowid);
				$this->ps_id_image = $obj->ps_id_image;
				return 1;
			}
			
			return 0;
		}
		else
		{
			$this->error = $this->db->lasterror();
			return -1;
		}
		
	}
	
}