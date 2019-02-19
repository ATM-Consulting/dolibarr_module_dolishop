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

namespace Dolishop;


$loader = require __DIR__ . '/../vendor/autoload.php';

if (!class_exists('SeedObject'))
{
	define('INC_FROM_DOLIBARR', true);
	require_once __DIR__.'/../config.php';
}

require_once __DIR__.'/dictionaries.class.php';
require_once __DIR__.'/override.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

class Webservice
{
	public static $cache = array();

	/** @var PSWebServiceLibrary\PrestaShopWebservice|MGWebServiceLibrary\MGWebServiceLibrary|null $webService */
	private static $webService = null;

	/** @var \DoliDB $db */
	public $db;

	public $api_name = 'prestashop';
	
	private $url;
	private $key;
	private $debug;
	
	public static $ps_configuration = null;
	
	public $error;
	public $errors = array();
	
	public static $from_cron_job = false;
	public $from_product_card = false;

	public $schema_products_blank;
	public $schema_products_synopsis;
	public $schema_combinations_blank;
	public $schema_combinations_synopsis;
	public $schema_categories_blank;
	public $schema_categories_synopsis;

	public $DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR;


	private $mgClient;
	private $mgConfiguration;
	private $mgStoreCode='default';
	private $mgApiHost='';


	/**
	 * @see getTProductCategory
	 * @var array|int $TCategoryByProductId
	 */
	public $TCategoryByProductId;

	public function __construct($db, $from_product_card=false)
	{
		global $conf,$langs;

		$this->db = $db;
		$this->from_product_card = $from_product_card;
		$langs->load('dolishop@dolishop');

		$this->url = $conf->global->DOLISHOP_STORE_PATH;
		$this->key = $conf->global->DOLISHOP_PS_WS_AUTH_KEY;
		$this->debug = (bool) $conf->global->DOLISHOP_STORE_WS_DEBUG;

		if (!empty($conf->global->DOLISHOP_API_NAME)) $this->api_name = $conf->global->DOLISHOP_API_NAME;

		switch ($this->api_name) {
			case 'magento':

				require_once __DIR__.'/../src/MGWebServiceLibrary.php';

				$options = array(
					'username' => $conf->global->DOLISHOP_MAGENTO_USERNAME
					,'password' => $conf->global->DOLISHOP_MAGENTO_PASSWORD
					,'store_code' => $conf->global->DOLISHOP_SYNC_MAGENTO_STORE_CODE
				);

				if (is_null(self::$webService)) self::$webService = new MGWebServiceLibrary\MGWebServiceLibrary($this->url, $options, $this->debug);

				$now = dol_now();
				$Tab = unserialize($conf->global->DOLISHOP_MAGENTO_ADMIN_TOKEN);

				$ten_minutes = 600; // Pour forcer la génération d'un nouveau token si celui si arrive à terme
				if (empty($Tab) || $Tab['expiration_time'] <= $now - $ten_minutes)
				{
					try {
						$token = self::$webService->getToken();
						if (!empty($token))
						{
							$Tab = array(
								'token' => $token
								,'life_time' => 3600 * 4 // default life time is 4 hours TODO get it from REST call or set as conf
								,'expiration_time' => strtotime('+4 hours', $now)
							);

							\dolibarr_set_const($this->db, 'DOLISHOP_MAGENTO_ADMIN_TOKEN', serialize($Tab), 'chaine', 0, '', $conf->entity);

						}
					} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
						$this->setError($e);
					}
				}

				if (!empty($Tab['token']))
				{
					self::$webService->setHeader(array(
						'Authorization' => 'Bearer '.$Tab['token']
						,'Accept' => 'application/json'
						,'Content-Type' => 'application/json'
					));
				}

				break;
			case 'prestashop':
			default:
				require_once __DIR__.'/../src/PSWebServiceLibrary.php';

				if (is_null(self::$webService)) self::$webService = new PSWebServiceLibrary\PrestaShopWebservice($this->url, $this->key, $this->debug);
				if (is_null(self::$ps_configuration)) self::$ps_configuration = json_decode($conf->global->DOLISHOP_PS_CONFIGURATION, true);

				break;
		}

		$this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR = explode(',', $conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR);
	}

	public function isConfigured()
	{
		global $conf;

		switch ($this->api_name)
		{
			case 'magento':
				$is = !empty($conf->global->DOLISHOP_STORE_PATH) & !empty($conf->global->DOLISHOP_MAGENTO_USERNAME) & !empty($conf->global->DOLISHOP_MAGENTO_PASSWORD);
				break;
			case 'prestashop':
			default:
				$is = !empty($conf->global->DOLISHOP_STORE_PATH) & !empty($conf->global->DOLISHOP_PS_WS_AUTH_KEY);
				break;
		}

		return $is;
	}

	/**
	 * Test de connectivité avec la boutique distante
	 * 
	 * @return boolean
	 */
	public function testConnection()
	{
		if ($this->api_name == 'prestashop')
		{
			$r = $this->getAll('');
			if ($r !== false) return $r->attributes()->shopName->__toString();
		}
		else if ($this->api_name == 'magento')
		{
			$mg_store_config = $this->getAll('/V1/store/storeGroups');
			if ($mg_store_config) return 'Magento';
		}

		return false;
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
		
		if ($this->api_name == 'prestashop')
		{
			if (empty($this->{'schema_'.$resourcename.'_'.$type})) 
			{
				try
				{
					$opt = array('url' => $conf->global->DOLISHOP_STORE_PATH.'/api/'.$resourcename.'?schema='.$type);
					$result_xml = self::$webService->get($opt);
					$this->{'schema_'.$resourcename.'_'.$type} = $result_xml;

					return clone $this->{'schema_'.$resourcename.'_'.$type};
				}
				catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
				{
					$this->setError($e);
					return false;
				}
			}

			return $this->{'schema_'.$resourcename.'_'.$type};
		}
		
		return false;
	}
	
	private function removeUselessFields(\SimpleXMLElement &$xml, \SimpleXMLElement &$schema)
	{
		if ($this->api_name == 'prestashop')
		{
			// unset des champs qui sont en readOnly et qu'il faut retirer du xml pour l'envoi au webservice
			foreach ($schema->children()->children() as $nodeKey => $node)
			{
				if ( 
					isset($node->attributes()->readOnly) && $node->attributes()->readOnly == true
					|| isset($node->attributes()->read_only) && $node->attributes()->read_only == true)
				{
					unset($xml->$nodeKey);
				}
			}	
		}
	}
	
	/**
	 * Permet de retourner une liste de ressource
	 * 
	 * @param string	$resource_name	Nom de la ressource Prestashop
	 * @param array		$more_opt		Tableau d'option complémentaire pour la requête ('filter', 'display', 'sort', 'limit', 'id_shop', 'id_group_shop')
	 * @return \SimpleXMLElement|\stdClass[]|boolean
	 */
	public function getAll($resource_name, $more_opt=array(), $children=true)
	{
		$opt = array('resource' => $resource_name);
		if (!empty($more_opt))
		{
			foreach ($more_opt as $key => $value) $opt[$key] = $value;
		}

		if ($this->api_name == 'prestashop')
		{
			if (!isset($opt['display'])) $opt['display'] = 'full';
		}
//		else if ($this->api_name == 'magento') {}

		try {
			$result = self::$webService->get($opt);

			if ($this->api_name == 'prestashop' && $children) return $result->children();

			return $result;
		} catch (PSWebServiceLibrary\PrestaShopWebserviceException $e) {
			$this->setError($e);
		} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
			$this->setError($e);
		}
		
		return false;
	}

	/**
	 * Retourne un objet en particulier via son identifiant
	 *
	 * @param string		$resource_name	Nom de la ressource
	 * @param int|string	$id
	 * @param array			$more_opt
	 * @param bool			$children
	 * @param bool			$force
	 * @return bool|PSWebServiceLibrary\SimpleXMLElement|\GuzzleHttp\Promise\PromiseInterface|mixed|\Psr\Http\Message\ResponseInterface
	 */
	public function getOne($resource_name, $id, $more_opt=array(), $children=true, $force=false)
    {
    	if (!$force && !empty(self::$cache[$resource_name][$id])) return self::$cache[$resource_name][$id];

		if ($this->api_name == 'prestashop') $opt = array('resource' => $resource_name, 'id' => $id);
		else if ($this->api_name == 'magento') $opt = array('resource' => $resource_name.'/'.$id);

		if (!empty($more_opt))
		{
			foreach ($more_opt as $key => $value) $opt[$key] = $value;
		}

		try {
			$result = self::$webService->get($opt);

			if ($this->api_name == 'prestashop' && $children) return $result->children();

			self::$cache[$resource_name][$id] = $result;

			return $result;
		} catch (PSWebServiceLibrary\PrestaShopWebserviceException $e) {
			$this->setError($e);
		} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
			$this->setError($e);
		}

		return false;
    }
	
	/**
	 * Suppression d'une ressource
	 * 
	 * @param type $resource_name
	 * @param type $id
	 * @param type $opt_alt
	 * @return boolean
	 */
	public function deleteOne($resource_name, $id)
	{
		return false;
		
		if ($this->api_name == 'prestashop')
		{
			try
			{
				$opt = array('resource' => $resource_name, 'id' => $id);
				return self::$webService->delete($opt);
			}
			catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
			{
				$this->setError($e);
			}
		}
		
		return false;
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
	private function postImage($image_path, $resource_name, $id_resource, $id_image=0)
	{
		global $langs;
		
		if ($this->api_name == 'prestashop')
		{
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
				$e = $xml_result->children()->children();
				$error = $e[0];
				$this->error = $langs->trans('DolishopPostImageError', $error->code->__toString(), $error->message->__toString());
				$this->errors[] = $this->error;
				return false;
			}

			return $xml_result->children();	
		}
		else if ($this->api_name == 'magento')
		{
			$info = pathinfo($image_path);
			$data = file_get_contents($image_path);

			$mg_image = array(
				'media_type' => 'image'
				,'label' => 'Image'
				,'disabled' => false // permet de rendre l'image visible sur la boutique
				,'types' => array(
					'image'
					,'thumbnail'
					,'small_image'
				)
				,'file' => $info['basename']
				,'content' => array(
					'base64_encoded_data' => base64_encode($data)
					,'type' => mime_content_type($image_path)
					,'name' => $info['basename']
				)
			);

			try {
				$mg_image_return = self::$webService->post(array(
					'resource' => '/V1/products/'.$id_resource.'/media'
					,'body' => array('entry' => $mg_image)
				));

				return $mg_image_return;
			} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
				$this->setError($e);
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
	public function saveImages(&$dol_product, $TFileName, $dir, $savingdocmask='')
	{
		global $user;

		if ($this->api_name == 'prestashop')
		{
			if (empty(self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products'])) return 0;
			if (empty($dol_product->array_options['options_ps_id_product'])) return 0;

			foreach ($TFileName as $name)
			{
				$info = pathinfo($name);
				if (!empty($savingdocmask)) $info['filename'] = str_replace('__file__', $info['filename'], $savingdocmask);

				$filename = dol_sanitizeFileName($info['filename'].'.'.strtolower($info['extension']));
				$image_path = $dir.'/'.$filename;
				$mime_type = mime_content_type($image_path);
				if (in_array($mime_type, self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products']))
				{
					$ecm = new EcmFilesDolishop($this->db);
					$ecm->fetchByFileNamePath($filename, $dol_product->ref);

					$result = $this->postImage($image_path, 'products', $dol_product->array_options['options_ps_id_product'], $ecm->ps_id_image);
					if ($result === false)
					{
						if ($ecm->ps_id_image > 0) $result = $result = $this->postImage($image_path, 'products', $dol_product->array_options['options_ps_id_product']);
						if ($result === false) return -1;
					}

					$ps_id_image_return = (int) $result->image->id;
					if ($ecm->id > 0 && $ps_id_image_return != $ecm->ps_id_image)
					{
						$ecm->ps_id_image = $ps_id_image_return;
						$ecm->update($user);
					}
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			// TODO à recup en conf pour connaitre les types d'images autorisés
//			if (empty(self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products'])) return 0;
			if (empty($dol_product->array_options['options_mg_id_product'])) return 0;

			foreach ($TFileName as $name)
			{
				$info = pathinfo($name);
				if (!empty($savingdocmask)) $info['filename'] = str_replace('__file__', $info['filename'], $savingdocmask);

				$filename = dol_sanitizeFileName($info['filename'].'.'.strtolower($info['extension']));
				$image_path = $dir.'/'.$filename;
				$mime_type = mime_content_type($image_path);
//				if (in_array($mime_type, self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products']))
//				{
					$ecm = new EcmFilesDolishop($this->db);
					$ecm->fetchByFileNamePath($filename, $dol_product->ref);

					$mg_id_image_return = $this->postImage($image_path, 'products', $dol_product->ref, $ecm->mg_id_image);
					if ($mg_id_image_return === false)
					{
						if ($ecm->mg_id_image > 0) $mg_id_image_return = $result = $this->postImage($image_path, 'products', $dol_product->ref);
						if ($mg_id_image_return === false) return -1;
					}

					if ($ecm->id > 0 && $mg_id_image_return != $ecm->mg_id_image)
					{
						$ecm->mg_id_image = $mg_id_image_return;
						$ecm->update($user);
					}
//				}
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
	public function deleteImages(&$dol_product, $TFileName)
	{
		if (DolishopTools::checkProductCategoriesD2P($dol_product->id))
		{
			foreach ($TFileName as $filename)
			{
				$ecm = new EcmFilesDolishop($this->db);
				$ecm->fetchByFileNamePath($filename, $dol_product->ref);
				if ($this->api_name == 'prestashop')
				{
					if ($ecm->ps_id_image > 0)
					{
						$res = $this->deleteImage('products', $dol_product->array_options['options_ps_id_product'], $ecm->ps_id_image);
					}
				}
				else if ($this->api_name == 'magento')
				{
					if ($ecm->mg_id_image > 0)
					{
						$res = $this->deleteImage('products', $dol_product->ref, $ecm->mg_id_image);
					}
				}



			}
		}
		
		return 0;
	}

	/**
	 * @param $resource_name
	 * @param $id_resource
	 * @param $id_image
	 * @return mixed	return empty string if success
	 */
	public function deleteImage($resource_name, $id_resource, $id_image)
	{
		$result = false;

		if ($this->api_name == 'prestashop')
		{
			$url = $this->url;
			if (substr($url, -1, 1) !== '/') $url.= '/api/images/';
			else $url.= 'api/images/';

			$url.= $resource_name.'/'.$id_resource;
			$url.= '/'.$id_image.'?ps_method=DELETE';

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_USERPWD, $this->key.':');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			curl_close($ch);
		}
		else if ($this->api_name == 'magento')
		{
			$options = array(
				'resource' => '/V1/products/'.$id_resource.'/media/'.$id_image
			);

			try {
				$result = self::$webService->delete($options, array('async' => true));
				$result->wait(false); // Obligation d'attendre la réponse pour que la suppression soit effective
			} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
				$this->setError($e);
			}
		}

		return $result;
	}
	
	public function syncConf()
	{
		if ($this->api_name == 'prestashop')
		{
			return $this->syncPsConf();
		}
	}
	
	/**
	 * Methode permettant de récupérer des configurations de Prestashop pour le bon fonctionnement du module
	 *  les langues		: self::$ps_configuration['PS_LANGUAGES']
	 *  les taxes		: self::$ps_configuration['PS_TAXES']
	 *  les mime types	: self::$ps_configuration['PS_IMAGES_MIME_TYPES']
	 * (doit être appelée si la configuration évolue sur Prestashop)
	 * 
	 * @global Conf $conf
	 * @return boolean
	 */
	private function syncPsConf()
	{
		global $conf;
		
		if (empty(self::$ps_configuration)) self::$ps_configuration = array();
		
		$languages = $this->getAll('languages', array('display' => 'full'));
		if ($languages && $languages->children()->count() > 0)
		{
			$TLang = array();
			foreach ($languages->children() as $l)
			{
				$TLang[(int) $l->id] = array(
					'id' => (int) $l->id
					,'name' => $l->name->__toString()
					,'iso_code' => $l->iso_code->__toString()
					,'dol_iso_code' => $l->language_code->__toString().'_'.strtoupper($l->iso_code->__toString())
					,'language_code' => $l->language_code->__toString()
					,'active' => (int) $l->active
				);
			}
			
			self::$ps_configuration['PS_LANGUAGES'] = $TLang;
		}
		else return false;
		
		$taxes = $this->getAll('taxes');
		$tax_rules = $this->getAll('tax_rules');
		
		if ($taxes && $tax_rules && $taxes->children()->count() > 0 && $tax_rules->children()->count() > 0)
		{
			$TTaxe = array();
			foreach ($taxes->children() as $taxe)
			{
				$id_tax = (int) $taxe->id;
				$vat_rate = (float) $taxe->rate; // Cast en float pour retirer les 0 à la fin
				if (!isset($TTaxe[(string) $vat_rate])) $TTaxe[(string) $vat_rate] = array('TLabel' => array(), 'TId_tax' => array(), 'TId_tax_rules_group' => array());
				$TTaxe[(string) $vat_rate]['TLabel'][$id_tax] = $taxe->name->language->__toString();
				$TTaxe[(string) $vat_rate]['TId_tax'][$id_tax] = $id_tax;
				
				foreach ($tax_rules->children() as $tax_rule)
				{
					if ((int) $tax_rule->id_tax == $id_tax)
					{
						$TTaxe[(string) $vat_rate]['TId_tax_rules_group'][$id_tax] = (int) $tax_rule->id_tax_rules_group;
						break;
					}
				}
				
			}
			
			self::$ps_configuration['PS_TAXES'] = $TTaxe;
		}
		else return false;
		
		$images = $this->getAll('images');
		if ($images && !empty($images->image_types->products->attributes()->upload_allowed_mimetypes))
		{
			self::$ps_configuration['PS_IMAGES_MIME_TYPES'] = array();
			self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products'] = explode(', ', $images->image_types->products->attributes()->upload_allowed_mimetypes);
		}
		else return false;
		
		
		$res = dolibarr_set_const($this->db, 'DOLISHOP_PS_CONFIGURATION', json_encode(self::$ps_configuration), 'chaine', 0, '', $conf->entity);
		if ($res > 0)
		{
			self::$ps_configuration = json_decode($conf->global->DOLISHOP_PS_CONFIGURATION, true);
			return true;
		}
		else
		{
			$this->errors[] = $this->db->lasterror();
			return false;
		}
	}
	
	public function setCarriersAssociation($TCarrierAssociation)
	{
		global $conf;
		
		self::$ps_configuration['WEB_SHIPPING_ASSOC'] = array();
		
		foreach ($TCarrierAssociation as $web_id_carrier => $fk_shipping_method)
		{
			self::$ps_configuration['WEB_SHIPPING_ASSOC'][$web_id_carrier] = $fk_shipping_method;
		}
		
		$res = dolibarr_set_const($this->db, 'DOLISHOP_PS_CONFIGURATION', json_encode(self::$ps_configuration), 'chaine', 0, '', $conf->entity);
		if ($res > 0) return true;
		else
		{
			$this->errors[] = $this->db->lasterror();
			return false;
		}
	}
	
	/**
	 * Synchronise les objets vers Prestashop
	 * Méthode utilisée par la tâche cron déclarée dans Dolibarr à l'activation du module
	 * 
	 * @global Conf $conf
	 * @return int
	 */
	public function rsyncProducts($fk_user, $direction, $sync_images=false, $minutes=30)
	{
		global $conf,$langs;
		
		self::$from_cron_job = true;

		if (!is_numeric($minutes)) $minutes = 30;
		$date_min = date('Y-m-d H:i:s', strtotime('-'.$minutes.' minutes'));

		if (empty($conf->global->DOLISHOP_SYNC_PRODUCTS))
		{
			$this->outpout = $langs->trans('DolishopSyncProductsIsDisabled');
			return 0;
		}
		
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

		$user = new \User($this->db);
		if ($user->fetch($fk_user) <= 0 || $user->statut == 0)
		{
			$this->output = $langs->trans('DolishopParameterUserIdNotFound');
			return 1;
		}
		$user->getrights();

		if ($sync_images) require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		if ($direction == 'dolibarr2website')
		{
			$this->syncDolCombinationsOptions();

			$TProductId = DolishopTools::getTProductIdToSync($date_min);
			$this->updateWebProducts($TProductId, $sync_images);
		}
		else // website2dolibarr
		{
			$this->syncWebCombinationsOptions($date_min);

			if ($this->api_name == 'prestashop')
			{
				// TODO mettre en place le filtre avec $date_min vis à vis de la date de dernière mis à jour
				$more_opt = array('filter[id_shop_default]' => '['.$conf->global->DOLISHOP_SYNC_PS_SHOP_ID.']');
				$ps_products = $this->getAll('products', $more_opt);
				if ($ps_products)
				{
					foreach ($ps_products->children() as $ps_product)
					{
						$this->saveProductFromWebProduct($ps_product, $sync_images);
					}
				}
			}
			else if ($this->api_name == 'magento')
			{
				/**
				 * =simple = produit
				 * grouped = produit + lien produit virtuel
				 * configurable = produit + variante
				 * =virtual = service
				 * bundle = produit + lien produit virtuel * => puis sur récuépration de la commande on verra ce qu'il y a
				 * =downloadable = produit
				 */
				// TODO REMOVE NEXT LINE
//				$date_min = '2018-11-06 11:00:00';

				$mg_configurables_products = $this->getAll('/V1/products', array(
					'params' => array(
//						'searchCriteria[filterGroups][0][filters][0][field]' => 'type_id'
//						,'searchCriteria[filterGroups][0][filters][0][value]' => 'configurable'
//						,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq' // Greater than or equal
						'searchCriteria[filterGroups][0][filters][0][field]' => 'type_id'
						,'searchCriteria[filterGroups][0][filters][0][value]' => 'configurable'
						,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq' // Greater than or equal
						,'searchCriteria[filterGroups][1][filters][0][field]' => 'updated_at'
						,'searchCriteria[filterGroups][1][filters][0][value]' => $date_min
						,'searchCriteria[filterGroups][1][filters][0][conditionType]' => 'gteq' // Greater than or equal
//						,'searchCriteria[currentPage]'=>1
//						,'searchCriteria[pageSize]'=>80
					)
				));

				foreach ($mg_configurables_products->items as &$mg_product)
				{
					$this->saveProductFromWebProduct($mg_product, $sync_images);
				}

				$TProductChildToIgnore = array();
				$sql = 'SELECT p.ref FROM '.MAIN_DB_PREFIX.'product p';
				$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'product_attribute_combination pac ON (p.rowid = pac.fk_product_child)';
				$resql = $this->db->query($sql);
				if ($resql)
				{
					while ($row = $this->db->fetch_object($resql)) $TProductChildToIgnore[$row->ref] = $row->ref;
				}

				$mg_products = $this->getAll('/V1/products', array(
					'params' => array(
						'searchCriteria[filterGroups][0][filters][0][field]' => 'type_id'
						,'searchCriteria[filterGroups][0][filters][0][value]' => 'bundle,configurable'
						,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'nin' // Greater than or equal
						,'searchCriteria[filterGroups][0][filters][0][field]' => 'sku'
						,'searchCriteria[filterGroups][0][filters][0][value]' => implode(',', $TProductChildToIgnore)
						,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'nin' // Greater than or equal
						,'searchCriteria[filterGroups][1][filters][0][field]' => 'updated_at'
						,'searchCriteria[filterGroups][1][filters][0][value]' => $date_min
						,'searchCriteria[filterGroups][1][filters][0][conditionType]' => 'gteq' // Greater than or equal
//						,'searchCriteria[currentPage]'=>1
//						,'searchCriteria[pageSize]'=>80
					)
				));

				if ($mg_products)
				{
					foreach ($mg_products->items as &$mg_product)
					{
						$this->saveProductFromWebProduct($mg_product, $sync_images);
					}
				}



			}

		}
		
		return 0;
	}
	
	/**
	 * Synchronise les produits vers la boutique Prestashop
	 * Attention : il n'y a pas de vérification sur les catégories associées aux produits (doit être faite avant l'appel à cette méthode)
	 * 
	 * @param array $ProductId
	 * @return int
	 */
	public function updateWebProducts($TProductId, $sync_images=false)
	{
		if (empty($TProductId)) return 0;
		if ($sync_images) require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		if ($this->api_name == 'prestashop')
		{
			$this->getSchema('products', 'synopsis'); // sert d'init
			foreach ($TProductId as $fk_product)
			{
				$this->syncProductToPrestashop($fk_product, $sync_images);
			}
		}
		else if ($this->api_name == 'magento')
		{
			foreach ($TProductId as $fk_product)
			{
				$this->syncProductToMagento($fk_product, $sync_images);
			}
		}
		
		return 0;
	}
	
	/**
	 * Synchronise les produits vers la boutique Prestashop
	 * Attention : il n'y a pas de vérification sur les catégories associées aux produits (doit être faite avant l'appel à cette méthode)
	 * 
	 * @param array $ProductId
	 * @return int
	 */
	private function syncProductToPrestashop($fk_product, $sync_images=false)
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

			if ($xml_origin !== false) $res = $this->savePsProduct($dol_product, $xml_origin);
			else $res = $this->savePsProduct($dol_product);
			
			if ($sync_images && $res > 0)
			{
				$dir = DolishopTools::getProductDirScan($dol_product);
				$TFileInfo = \dol_dir_list($dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 0);
				$TFileName = array();
				foreach ($TFileInfo as $info) $TFileName[] = $info['name'];
				
				$res = $this->saveImages($dol_product, $TFileName, $dir);
			}
		}
		
		return $res;
	}

	// TODO à finaliser
	private function syncProductToMagento($fk_product, $sync_images=false, $TCategory=array(), $fk_comb=0, $dol_parent_product=null)
	{
		$dol_product = new \Product($this->db);
		if ($dol_product->fetch($fk_product) > 0)
		{
			global $conf;

			if (empty($dol_product->array_options)) $dol_product->fetch_optionals();

			// TODO peut etre faut il faire un test sur l'extrafield "mg_id_product"
			$mg_product = $this->getOne('/V1/products', $dol_product->ref);
			if (!$mg_product)
			{
				// IGNORE ERROR
				$this->error = '';
				unset($this->errors[0]);
				$mg_product = new \stdClass();
			}

			$TChildCombinationId = array();
			if (!empty($conf->variants->enabled)) $TChildCombinationId = DolishopTools::getAllChildProductCombinationId($dol_product->id);

//			$mg_product['id'] = isset($data['id']) ? $data['id'] : null;
			$mg_product->sku = $dol_product->ref;
			$mg_product->name = $dol_product->label;
			$mg_product->attribute_set_id = 4; // 4 is id of Default attribute set here
			$mg_product->price = $dol_product->price;
			$mg_product->status = $dol_product->status;
			$mg_product->visibility = ($dol_product->status > 0 ? 4 : 1); // 1 = Non visible individuellement; 4 = Catalogue, recherche (2 = Catalogue; 3 = Rechercher)

			if (!empty($TChildCombinationId)) $mg_product->type_id = 'configurable';
			else $mg_product->type_id = 'simple'; // simple, bundle, virtual
//			$mg_product['created_at'] = isset($data['created_at']) ? $data['created_at'] : null;
//			$mg_product['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : null;
			$coef = 1;
//			if (isset(self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT']) && $dol_product->weight_units != self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'])
//			{
//				$delta = $dol_product->weight_units - self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'];
//				$coef = pow(10, $delta);
//			}
//			$mg_product['weight'] = $dol_product->weight * $coef;
			$mg_product->weight = $dol_product->weight * $coef;

			$TIntersec = array();
			if (!empty($mg_product->custom_attributes))
			{
				$extrafields = new \ExtraFields($this->db);
				$extralabels=$extrafields->fetch_name_optionals_label('product');

				foreach ($mg_product->custom_attributes as &$custom_attribute)
				{
					if (isset($extralabels['mg_'.$custom_attribute->attribute_code]))
					{
						$TIntersec[] = $custom_attribute->attribute_code;
					}
				}

			}

			$mg_product->custom_attributes = array();

			foreach ($TIntersec as $index)
			{
				$mg_product->custom_attributes[] = array('attribute_code' => $index, 'value' => $dol_product->array_options['options_mg_'.$index]);
			}

			$mg_product->custom_attributes[] = array('attribute_code' => 'description', 'value' => $dol_product->description);
			if (!empty($conf->global->DOLISHOP_STORE_TRUNC_DESCRIPTION_SHORT)) $mg_product->custom_attributes[] = array('attribute_code' => 'short_description', 'value' => DolishopTools::trunc($dol_product->description, $conf->global->DOLISHOP_STORE_TRUNC_DESCRIPTION_SHORT, true, false));


			if (empty($TCategory)) $TCategory = $this->getTProductCategory($dol_product->id);
			if (is_array($TCategory))
			{
				$TCatForMagento = array();
				foreach ($TCategory as $cat)
				{
					if (empty($cat['import_key']) || in_array($cat['id'], $this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR)) continue;
					$TCatForMagento[] = $cat['import_key'];
				}

				$mg_product->custom_attributes[] = array('attribute_code' => 'category_ids', 'value' => $TCatForMagento);
			}


			// There is a variant
			if (!empty($fk_comb) && !empty($conf->variants->enabled))
			{
				require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination2ValuePair.class.php';

				$prodcomb = new ProductCombinationDolishop($this->db);
				$res = $prodcomb->fetch($fk_comb);
				if ($res <= 0) return -1; // fetch error

				// Pas besoin, la fiche de la variante embarque déjà l'augmentation
//				$mg_product->price += $prodcomb->variation_price;
//				$mg_product->weight += $prodcomb->variation_weight;

				$TProdAttrValById = ProductAttributeValueDolishop::getAll('id', false, true, $this->api_name);
				$TProdAttrById = ProductAttributeDolishop::getAll('id', false, true, $this->api_name);

				$comb2val = new \ProductCombination2ValuePair($this->db);
				$TValuePair = $comb2val->fetchByFkCombination($prodcomb->id);
				if (!is_array($TValuePair)) return -2; // error

				$mg_product->extension_attributes = array();
				$mg_product->extension_attributes['configurable_product_options'] = array();

//				 Si des identifiants sont manquants, la synchro ne fonctionnera pas
				foreach ($TValuePair as $i => $valuePair)
				{
					$mg_product->custom_attributes[] = array('attribute_code' => strtolower($TProdAttrById[$valuePair->id]->ref), 'value' => $TProdAttrValById[$valuePair->fk_prod_attr_val]->mg_eav_attribute_option_id);
				}

			}

			// L'init stock fonctionne uniquement sur la création
			if (!empty($conf->global->DOLISHOP_SYNC_STOCK))
			{
				if (empty($mg_product->extension_attributes->stock_item->item_id))
				{
					$mg_product->extension_attributes->stock_item = new \stdClass();
				}

				// Nécessaire pour charger le stock theorique
				$dol_product->load_stock('warehouseopen');

				$mg_product->extension_attributes->stock_item->qty = $dol_product->stock_theorique; // stock_reel
				$mg_product->extension_attributes->stock_item->is_in_stock = ($dol_product->stock_theorique > 0) ? true : false;
//				var_dump($mg_product->extension_attributes->stock_item);exit;
			}

			$error = 0;
			try {
				if (!empty($mg_product->id))
				{
					$mg_product_res = self::$webService->put(array(
						'resource' => '/V1/products/'.$mg_product->sku
						,'body' => array('product' => $mg_product)
					));
				}
				else
				{
					$mg_product_res = self::$webService->post(array(
						'resource' => '/V1/products'
						,'body' => array('product' => $mg_product)
					));
				}
			} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
				$error++;
				$this->setError($e);
			}

			if (!$error)
			{
				$mg_id_product_return = $mg_product_res->id;
				if ($dol_product->array_options['options_mg_id_product'] != $mg_id_product_return)
				{
					$need_insert = false;
					if (empty($dol_product->array_options)) $need_insert = true;

					$dol_product->array_options['options_mg_id_product'] = $mg_id_product_return;

					if ($need_insert) $res = $dol_product->insertExtraFields();
					else $res = $dol_product->updateExtraField('mg_id_product');
				}

				if ($sync_images && $mg_id_product_return)
				{
					// TODO voir comment intégrer les images dans le create/update au dessus, car Magento permet d'envoyer en même temps une image ( voir si possible pour +sieurs )
//					$mg_product['media_gallery_entries'] = isset($data['media_gallery_entries']) ? $data['media_gallery_entries'] : null;
					$dir = DolishopTools::getProductDirScan($dol_product);
					$TFileInfo = \dol_dir_list($dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'position_name', SORT_ASC, 0);
					$TFileName = array();
					foreach ($TFileInfo as $info) $TFileName[] = $info['name'];

					$res = $this->saveImages($dol_product, $TFileName, $dir);
				}

				// TODO sync les variantes s'il y en a... Avec Magento il faut les considérer comme des fiches produits puis faire un appel pour faire la liaison
				// @see https://devdocs.magento.com/guides/v2.3/rest/tutorials/configurable-product/define-config-product-options.htmls
				//if ($res > 0) $this->saveWebCombinationsFromDolProduct($ps_product_return, $dol_product);
				foreach ($TChildCombinationId as $fk_child => $fk_comb)
				{
					$this->syncProductToMagento($fk_child, $sync_images, $TCategory, $fk_comb, $dol_product);


					// TODO add link
				}

/*
				if ($dol_parent_product !== null)
				{
					$body = array(
						'option' => array(
							'product_sku' => $dol_parent_product->ref
							,'title' => $dol_product->label
							,'type' => 'field'
							,'sort_order' => 1
							,'is_require' => false
							,'price' => $mg_product->price
							,'price_type' => 'fixed'
							,'sku' => $mg_product->sku
//							,'max_characters' => 15
						)
					);
//					var_dump($body);

					$result = self::$webService->post(array(
						'resource' => '/V1/products/options'
						,'body' => $body
					));
//					var_dump($result);
//					exit('FFIINN');

					$body = array(
						'childSku' => $mg_product->sku
					);

					$result = self::$webService->post(array(
						'resource' => '/V1/configurable-products/'.$dol_parent_product->ref.'/child'
						,'body' => $body
					));

					var_dump($result);
//					exit;
				}*/


			}

		}
	}

	/**
	 *
	 * @param MouvementStock $mouvement
	 */
	public function stockMovementToWebProduct($mouvement)
	{
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

		$product = new \Product($this->db);
		if ($product->fetch($mouvement->product_id) > 0 && !empty($product->array_options['options_mg_id_product']))
		{
			try
			{
				$mg_product = $this->getOne('/V1/products', $product->ref);
				// MAJ stock exemple
				if (!empty($mg_product->extension_attributes->stock_item->item_id))
				{
					$mg_product->extension_attributes->stock_item->qty += $mouvement->qty;
					$mg_product->extension_attributes->stock_item->is_in_stock = ($mg_product->extension_attributes->stock_item->qty > 0) ? true : false;

					$mg_stock = self::$webService->put(array(
						'resource' => '/V1/products/'.$mg_product->sku.'/stockItems/'.$mg_product->extension_attributes->stock_item->item_id
						,'body' => array('stock_item' => $mg_product->extension_attributes->stock_item)
					));
				}
				else
				{
					$mg_product->extension_attributes->stock_item = new \stdClass();

					// Nécessaire pour charger le stock theorique
					$product->load_stock('warehouseopen');

					// stock_reel
					$qty = $product->stock_theorique + $mouvement->qty;

					$mg_product->extension_attributes->stock_item->qty = $qty;
					$mg_product->extension_attributes->stock_item->is_in_stock = ($qty > 0) ? true : false;
	//				var_dump($mg_product->extension_attributes->stock_item);exit;

					$mg_product_res = self::$webService->put(array(
						'resource' => '/V1/products/'.$mg_product->sku
						,'body' => array('product' => $mg_product)
					));

				}
			} catch (MGWebServiceLibrary\MagentoWebserviceException $e) {
				$this->setError($e);
//				var_dump($mg_product->sku, $this->error);exit;
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
	 * @return \SimpleXMLElement | boolean
	 */
	private function findPsProductResource($opt, $alt_opt=array())
	{
		$error = 0;
		try
		{
			if (!isset($opt['limit'])) $opt['limit'] = 2; // ça m'évite de charger des résultats dont je n'ai pas besoin
			if (!isset($opt['display'])) $opt['display'] = 'full'; // je souhaite récupérer la totalité des champs pour un éventuel update
			$result_xml = self::$webService->get($opt);
			$resources = $result_xml->children();
		}
		catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
		{
			$error++;
			$this->setError($e);
		}
		
		if ($error == 0)
		{
			// Nombre d'occurrence ("product") dans "products"
			if ($resources->children()->count() == 1) return $result_xml;
			else if (!empty($alt_opt)) return $this->findPsProductResource($alt_opt);
		}
		
		return false;
	}
	
	/**
	 * Méthode qui se charge de faire appel au add() ou edit() du webservice
	 * 
	 * @global \Translate	$langs
	 * @global \Conf		$conf
	 * @param	\Product				$dol_product
	 * @param	\SimpleXMLElement	$xml_origin
	 * @return int
	 */
	private function savePsProduct(&$dol_product, $xml_origin=false)
	{
		global $langs,$conf;
		
		if ($xml_origin !== false) $schema = $xml_origin->children();
		else $schema = clone $this->schema_products_synopsis;

		// Pour comprendre pourquoi tant de children(), faire un print $schema->asXML()
		$ps_product = $schema->children()->children();
		
		unset($ps_product->position_in_category); // unset car l'api Prestashop génère une erreur 500 une fois sur 2
		
		$this->removeUselessFields($ps_product, $this->schema_products_synopsis);
		
//		var_dump($ps_product);exit;
		
		$ps_product->reference = $dol_product->ref;
		$ps_product->price =  $dol_product->price;
		
		$tva_tx = (float) $dol_product->tva_tx;
		$id_tax_rules_group = key((array) self::$ps_configuration['PS_TAXES'][$tva_tx]['TId_tax_rules_group']);
		$ps_product->id_tax_rules_group = $id_tax_rules_group;
		
		$ps_product->state =  1;
		
		$coef = 1;
		if (isset(self::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT']) && $dol_product->length_units != self::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT'])
		{
			$delta = $dol_product->length_units - self::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT'];
			$coef = pow(10, $delta);
		}
		$ps_product->width =  $dol_product->length * $coef;
		$ps_product->depth =  $dol_product->width * $coef;
		$ps_product->height =  $dol_product->height * $coef;
		
		$coef = 1;
		if (isset(self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT']) && $dol_product->weight_units != self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'])
		{
			$delta = $dol_product->weight_units - self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT'];
			$coef = pow(10, $delta);
		}
		$ps_product->weight =  $dol_product->weight * $coef;
		
		$ps_product->active =  $dol_product->status; // 1 = en vente donc à activer sur prestashop
		$ps_product->available_for_order =  $dol_product->status; // de même pour sa disponibilité sur la boutique
		$ps_product->show_price =  $dol_product->status; // de même pour afficher le prix sur la boutique
		$ps_product->redirect_type =  '404';
		
		$ps_product->low_stock_threshold = $dol_product->seuil_stock_alerte;
		
		// wholesale_price => prix d'achat
//		var_dump($dol_product);exit;
//		echo '<pre>'.htmlspecialchars(print_r($dol_product), ENT_QUOTES);exit;
		
		if (!empty($conf->global->MAIN_MULTILANGS) && !empty(self::$ps_configuration['PS_LANGUAGES']))
		{
			// TODO à voir plus tard si j'utilise PRODUCT_USE_OTHER_FIELD_IN_TRANSLATION pour m'en servir comme "description_short"
			$TProperty = array('name' => 'label', 'description' => 'description');
			if (!empty($conf->global->DOLISHOP_STORE_TRUNC_DESCRIPTION_SHORT)) $TProperty['description_short'] = 'description_short_trunc';
			
			foreach ($TProperty as $nodeKey => $dol_index)
			{
				preg_match('/.*\_(trunc)$/', $dol_index, $reg);
				foreach ($ps_product->{$nodeKey}->children() as $language)
				{
					if (!empty(self::$ps_configuration['PS_LANGUAGES'][(int) $language->attributes()->id]))
					{
						$dol_iso_code = self::$ps_configuration['PS_LANGUAGES'][(int) $language->attributes()->id]['dol_iso_code'];
						if (!empty($dol_product->multilangs[$dol_iso_code]))
						{
							if (empty($reg)) $language[0] = $dol_product->multilangs[$dol_iso_code][$dol_index];
							else if ($reg[1] == 'trunc') $language[0] = DolishopTools::trunc($dol_product->multilangs[$dol_iso_code]['description'], $conf->global->DOLISHOP_STORE_TRUNC_DESCRIPTION_SHORT, true, false);
							else {} // prévoir les autres cas si besoin
						}
					}
				}
			}
		}
		else
		{
			$ps_product->name->language[0] = $dol_product->label;
			$ps_product->description->language[0] = $dol_product->description;
			if (!empty($conf->global->DOLISHOP_STORE_TRUNC_DESCRIPTION_SHORT)) $ps_product->description_short->language[0] = DolishopTools::trunc($dol_product->description, $conf->global->DOLISHOP_STORE_TRUNC_DESCRIPTION_SHORT, true, false);
		}

		// Association des catégories
		if (!empty($conf->global->DOLISHOP_SYNC_PRODUCT_CATEG_D2W))
		{
			$TCategory = $this->getTProductCategory($dol_product->id);
			if (is_array($TCategory))
			{
				$TCategoryFromXml = array();
				foreach ($ps_product->associations->categories->children() as $ps_category)
				{
					$TCategoryFromXml[(int) $ps_category->id] = (int) $ps_category->id;
				}

				unset($ps_product->associations->categories);
				$ps_product->associations->addChild('categories');
				if (isset($TCategoryFromXml[2]))
				{
					// Je ne touche pas à la catégorie "Accueil"
					$pp = $ps_product->associations->categories->addChild('category');
					$pp->id = 2;
				}

				foreach ($TCategory as $cat)
				{
					if (empty($cat['import_key']) || in_array($cat['id'], $this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR)) continue;
					$pp = $ps_product->associations->categories->addChild('category');
					$pp->id = $cat['import_key'];
				}

				// TODO si $ps_product->id_category_default ne fait pas partie des ids associés alors Prestashop ne supprimera pas le lien
				// Voir pour ajouter un extrafield "web_id_category_default" qui sera une liste issue d'une table (llx_category ::type=0 AND import_key>0)
				// en attendant, s'il y qu'une seule catégorie alors c'est celle par défaut
				if ($ps_product->associations->categories->children()->count() == 1) $ps_product->id_category_default = (int) $ps_product->associations->categories->category->id;
			}
		}

		$error = 0;
		try
		{
			if ((int) $ps_product->id > 0)
			{
//				echo '<pre>'.htmlspecialchars($schema->asXML(), ENT_QUOTES);exit;
				$opt = array('resource' => 'products',  'putXml' => $schema->asXML(), 'id' => (int) $ps_product->id);
				$result_xml = self::$webService->edit($opt);
			}
			else
			{
//				echo '<pre>'.htmlspecialchars($schema->asXML(), ENT_QUOTES);exit;
				$opt = array('resource' => 'products',  'postXml' => $schema->asXML());
				$result_xml = self::$webService->add($opt);
			}
			
			$ps_product_return = $result_xml->children()->children();
		}
		catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
		{
			$error++;
			$this->setError($e);
		}
		
		if ($error == 0)
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
			
			if (self::$from_cron_job)
			{
				if ($res > 0) $this->output.= $langs->trans('DolishopCronjob_SyncProductSuccess', $dol_product->ref, $ps_id_product_return)."\n";
				else $this->output.= $langs->trans('DolishopCronjob_SyncProductFailUpdateExtrafield', $dol_product->ref, $ps_id_product_return)."\n";
			}
			
			if ($res > 0) $this->saveWebCombinationsFromDolProduct($ps_product_return, $dol_product);
			
			return $ps_id_product_return;
		}
		else
		{
			if (self::$from_cron_job) $this->output.= $langs->trans('DolishopCronjob_SyncProductError', $this->error)."\n";
		}
		
		return 0;
	}
	
	/**
	 * Ce charge de crééer les produit dans Dolibarr
	 * Si le module "variants" est actif, alors les déclinaisons sont synchro aussi
	 * 
	 * @global \Dolishop\Conf $conf
	 * @global \Dolishop\User $user
	 * @global \Translate	$langs $langs
	 * @param type $web_product
	 * @param type $sync_images
	 * @return int
	 */
	private function saveProductFromWebProduct($web_product, $sync_images)
	{
		global $conf,$langs;

		if (!DolishopTools::checkProductCategoriesW2D($this->api_name, $web_product)) return 0;

		$default_iso_code = $langs->getDefaultLang();
		$multilangs = array();
		$label = $description = '';

		if ($this->api_name == 'prestashop')
		{
			if (!empty($conf->global->MAIN_MULTILANGS) && !empty(self::$ps_configuration['PS_LANGUAGES']))
			{
				$TProperty = array('name' => 'label', 'description' => 'description');
				foreach ($TProperty as $nodeKey => $dol_index)
				{
					foreach ($web_product->{$nodeKey}->children() as $language)
					{
						if (!empty(self::$ps_configuration['PS_LANGUAGES'][(int) $language->attributes()->id]))
						{
							$dol_iso_code = self::$ps_configuration['PS_LANGUAGES'][(int) $language->attributes()->id]['dol_iso_code'];
							if ($dol_iso_code == $default_iso_code) ${$dol_index} = $language[0]->__toString(); // add value dynamically in $label & $description
							if (empty($multilangs[$dol_iso_code])) $multilangs[$dol_iso_code] = array('other'=>'');
							$multilangs[$dol_iso_code][$dol_index] = $language[0]->__toString();
						}
					}
				}
			}
			else
			{
				$label = $web_product->name->language[0]->__toString();
				$description = $web_product->description->language[0]->__toString();
			}

			$dol_product = $this->saveProduct(
				(int) $web_product->id
				,$web_product->reference->__toString()
				,$label
				,$description
				,$multilangs
				,$web_product->price
				,DolishopTools::getVatRate(0, $web_product->id_tax_rules_group)
				,$web_product->active->__toString()
				,$web_product->low_stock_threshold->__toString()
				,(float) $web_product->width
				,(float) $web_product->depth
				,(float) $web_product->height
				,self::$ps_configuration['MESURING_UNITS']['DIMENSION_UNIT']
				,(float) $web_product->weight
				,self::$ps_configuration['MESURING_UNITS']['WEIGHT_UNIT']
			);
		}
		else if ($this->api_name == 'magento')
		{
			$mg_category_ids = array();
			foreach ($web_product->custom_attributes as &$cattr)
			{
				if ($cattr->attribute_code == 'description') $description = $cattr->value;
				else if ($cattr->attribute_code == 'category_ids') $mg_category_ids = $cattr->value;
			}

			$fk_product_type=0;
			if ($web_product->type_id == 'virtual') $fk_product_type = 1;

			$dol_product = $this->saveProduct(
				$web_product->id
				,$web_product->sku
				,$web_product->name
				,$description
				,$multilangs
				,$web_product->price
				,null // Les produits Magento sont associés à un id de règle de taxe et non pas à un taux directement
				,$web_product->status
				,$web_product->extension_attributes->stock_item->notify_stock_qty
				,null
				,null
				,null
				,null
				,$web_product->weight
				,0 // 0 = Kg, TODO conf paramétrable
				,$fk_product_type
			);
		}

		if (!empty($dol_product->error))
		{
			$this->error = $dol_product->error;
			$this->errors[] = $this->error;
			return -1;
		}


		$id_boutique = ($this->api_name == 'prestashop') ? $dol_product->array_options['options_ps_id_product'] : $dol_product->array_options['options_mg_id_product'];
		if ($dol_product->id > 0) $this->output.= $langs->trans('DolishopCronjob_SyncProductSuccess', $dol_product->ref, $id_boutique)."\n";
		else $this->output.= $langs->trans('DolishopCronjob_SyncProductFailUpdateExtrafield', $dol_product->ref, $id_boutique)."\n"; // TODO à vérifier sur une version Dolibarr 6.0, mais je pense qu'il n'est plus possible d'arriver dans ce cas l`après modification du code pour intégrer la synchro Magento

		$TCatToAdd = array();

		reset($this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR);
		$firstK = key($this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR);
		if (!empty($this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR[$firstK])) $TCatToAdd[] = $this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR[$firstK];

		if ($this->api_name == 'prestashop')
		{
			if ($web_product->associations->categories->children()->count() > 0)
			{
				$TCategory = $this->getTProductCategory(0, false, 'import_key');
				foreach ($web_product->associations->categories->children() as $ps_category)
				{
					if (isset($TCategory[(int) $ps_category->id])) $TCatToAdd[] = $TCategory[(int) $ps_category->id]['id'];
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			if (!empty($mg_category_ids))
			{
				$TCategory = $this->getTProductCategory(0, false, 'import_key');
				foreach ($mg_category_ids as $mg_category_id)
				{
					if (isset($TCategory[$mg_category_id])) $TCatToAdd[] = $TCategory[$mg_category_id]['id'];
				}
			}
		}

		if (!empty($TCatToAdd)) $dol_product->setCategories($TCatToAdd);

		// TODO gérér la récupération des images si $sync_images = true
		if ($sync_images)
		{
			$this->saveProductImageFromWebProduct($dol_product, $web_product);
		}

		// TODO voir si je passe $TCatToAdd pour les associations avec les déclinaisons
		$this->saveDolCombinationsFromWebProduct($web_product, $dol_product, $sync_images);
		
		return $dol_product->id;
	}

	private function saveProductImageFromWebProduct($dol_product, $web_product)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		if (! empty($conf->product->enabled)) $upload_dir = $conf->product->multidir_output[$dol_product->entity].'/'.get_exdir(0, 0, 0, 0, $dol_product, 'product').dol_sanitizeFileName($dol_product->ref);
		elseif (! empty($conf->service->enabled)) $upload_dir = $conf->service->multidir_output[$dol_product->entity].'/'.get_exdir(0, 0, 0, 0, $dol_product, 'product').dol_sanitizeFileName($dol_product->ref);

		if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO))    // For backward compatiblity, we scan also old dirs
		{
			if (! empty($conf->product->enabled)) $upload_dir = $conf->product->multidir_output[$dol_product->entity].'/'.substr(substr("000".$dol_product->id, -2),1,1).'/'.substr(substr("000".$dol_product->id, -2),0,1).'/'.$dol_product->id."/photos";
			else $upload_dir = $conf->service->multidir_output[$dol_product->entity].'/'.substr(substr("000".$dol_product->id, -2),1,1).'/'.substr(substr("000".$dol_product->id, -2),0,1).'/'.$dol_product->id."/photos";
		}

		$upload_dir_tmp = $upload_dir.'/temp';
		if (!is_dir($upload_dir_tmp)) dol_mkdir($upload_dir_tmp);

		if ($this->api_name == 'prestashop')
		{

		}
		else if ($this->api_name == 'magento')
		{
			$image_name = '';
			foreach ($web_product->custom_attributes as &$cattr)
			{
				if ($cattr->attribute_code == 'image')
				{
					$image_name = $cattr->value;
					break;
				}
			}

			if (empty($image_name)) return -1;

			$full_url = $this->url.'/pub/media/catalog/product'.$image_name;

			$info = pathinfo($full_url);
			$destfile = dol_sanitizeFileName($info['filename'].'.'.strtolower($info['extension']));

			$tmp_image = $upload_dir_tmp.'/'.$destfile;

			dol_copy($full_url, $tmp_image);
			if (dol_mkdir($upload_dir) >= 0)
			{
				$destfull = $upload_dir.'/'.$destfile;

				// TODO EcmFiles do not save mg_id_image yet (whatever I don't this ID, must make another REST call)
				$resmove = dol_move($tmp_image, $destfull);
				return $resmove;
			}

			return -2;
		}

		return 0;
	}

	private function saveProduct($web_product_id, $ref, $label='', $description='', $multilangs=array(), $price=0, $tva_tx=0, $status=1, $seuil_stock_alerte=null, $length = null, $width = null, $height = null, $length_units = null, $weight = null, $weight_units = null, $fk_product_type=0)
	{
		global $langs,$user,$conf;

		$dol_product = new \Product($this->db);

		// attention, avec Magento, c'est par la ref et non par l'id (bien qu'il soit sauvegardé)
		$fk_product = DolishopTools::getDolProductId($web_product_id, $ref);
		if ($fk_product == -1)
		{
			$dol_product->error = $langs->trans('DolishopErrorMultipleReferenceFound',$web_product_id, $ref);
			return $dol_product;
		}
		else if ($fk_product > 0)
		{
			$dol_product->fetch($fk_product);
		}

		if ($this->api_name == 'prestashop') $dol_product->array_options['options_ps_id_product'] = $web_product_id;
		else if ($this->api_name == 'magento') $dol_product->array_options['options_mg_id_product'] = $web_product_id;

		$dol_product->ref = $ref;
		$dol_product->label = $label;
		$dol_product->description = $description;
		$dol_product->multilangs = $multilangs;

		$dol_product->price = $price;
		if (!is_null($tva_tx)) $dol_product->tva_tx = $tva_tx;
		$dol_product->status = $status;
		$dol_product->seuil_stock_alerte = $seuil_stock_alerte;

		$dol_product->length = $length;
		$dol_product->width = $width;
		$dol_product->height = $height;
		$dol_product->length_units = $length_units;

		$dol_product->weight = $weight;
		$dol_product->weight_units = $weight_units;

		$dol_product->fk_product_type = $fk_product_type;
		$dol_product->fk_default_warehouse = $conf->global->DOLISHOP_DEFAULT_WAREHOUSE_ID;

		if (!empty($dol_product->id)) $res = $dol_product->update($dol_product->id, $user);
		else
		{
			$res = $dol_product->create($user);
			$dol_product->entity = $conf->entity;
			if ($res > 0 && !empty($conf->global->DOLISHOP_SYNC_STOCK))
			{
				$this->initStockFromWebProduct($dol_product, $web_product_id, $ref);
			}
		}

		return $dol_product;
	}

	/**
	 * @param \Product 	$dol_product
	 * @param integer 	$web_product_id
	 * @param string 	$ref
	 */
	private function initStockFromWebProduct($dol_product, $web_product_id, $ref)
	{
		global $user,$conf;

		if (empty($conf->global->DOLISHOP_DEFAULT_WAREHOUSE_ID)) return 0;

		if ($this->api_name == 'prestashop')
		{
			// TODO ...
		}
		else if ($this->api_name == 'magento')
		{
			$mg_stock = $this->getOne('/V1/stockItems', $ref);
			if ($mg_stock)
			{
				$this->db->begin();

				require_once DOL_DOCUMENT_ROOT .'/product/stock/class/mouvementstock.class.php';

				$movementstock=new \MouvementStock($this->db);
				$result=$movementstock->_create($user,$dol_product->id,$conf->global->DOLISHOP_DEFAULT_WAREHOUSE_ID,$mg_stock->qty,0,0,'Init Magento','');

				if ($result >= 0)
				{
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->error=$movementstock->error;
					$this->errors=$movementstock->errors;

					$this->db->rollback();
					return -1;
				}
			}
		}

		return 0;
	}

	/**
	 * Crée ou met à jour les combinaisons dans Dolibarr
	 * API Prestashop 	"combinations" accessible en GET
	 * API Magento 		"/V1/configurable-products/:sku/children"
	 * 
	 * @global \Dolishop\Conf		$conf
	 * @global \Dolishop\User		$user
	 * @param \SimpleXmlElement		$web_product
	 * @param \Product				$dol_product
	 * @param boolean				$sync_images
	 * @return int
	 */
	private function saveDolCombinationsFromWebProduct($web_product, $dol_product, $sync_images)
	{
		global $conf,$langs;
		
		if (empty($conf->variants->enabled)) return 0;
		require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination.class.php';
		
		if ($this->api_name == 'prestashop')
		{
			$TProdAttrGroupByPsId = ProductAttributeDolishop::getAllByPsId();
			$TProdAttrValByPsId = ProductAttributeValueDolishop::getAllByPsId();
			
			if (!empty($web_product->associations->product_option_values))
			{
				foreach ($web_product->associations->combinations->children() as $ps_id_combination)
				{
					// Attention, l'API Prestashop ne me permet pas de récupérer toutes les combinaisons avec un getAll(), car si certains ont un impact sur le prix et pas d'autre la récupération est fausse
					$ps_combination = $this->getOne('combinations', (int) $ps_id_combination->id);
					if ($ps_combination)
					{
						$ps_combination = $ps_combination->children();
						$sanit_features = array();
						
						foreach ($ps_combination->associations->product_option_values->children() as $ps_product_option_value)
						{
							if (!isset($TProdAttrValByPsId[(int) $ps_product_option_value->id]))
							{
								if (self::$from_cron_job)
								{
									$this->output.= $langs->trans('SyncProductCombinationFailAttributeMissing', $dol_product->ref, (int) $ps_product_option_value->id)."\n";
								}
								else
								{
									$this->error = $langs->trans('SyncProductCombinationFailAttributeMissing', $dol_product->ref, (int) $ps_product_option_value->id);
									$this->errors[] = $this->error;
								}
								
								continue 2;
							}
							
							$ps_id_option_group = $TProdAttrValByPsId[(int) $ps_product_option_value->id]->ps_id_option_group;
							$sanit_features[$TProdAttrGroupByPsId[$ps_id_option_group]->id] = $TProdAttrValByPsId[(int) $ps_product_option_value->id]->id;
						}

						$this->saveDolCombination($dol_product, $sanit_features, (int) $ps_combination->id, (double) $ps_combination->price, (double) $ps_combination->weight);
					}
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			if ($web_product->type_id != 'configurable') return 0; // avec Magento, si le type n'est pas "configurable", alors je passe

			$TProdAttrGroupByRef = ProductAttributeDolishop::getAll('ref', true, true, 'magento');
			$TProdAttrValByMgId = ProductAttributeValueDolishop::getAllByMgId();

			$mg_products = $this->getAll('/V1/configurable-products/'.$web_product->sku.'/children');

			// Ici, pas d'attribut "items"
			if (!empty($mg_products))
			{
				$sanit_features = array();
				$TAttrGroupUsed = array();
				// J'identifie la liste des groupes d'attribut possible (size, color,...)
				foreach ($mg_products[0]->custom_attributes as $k => $mg_custom_attributes)
				{
					if (isset($TProdAttrGroupByRef[strtoupper($mg_custom_attributes->attribute_code)]))
					{
						$TAttrGroupUsed[$k] = strtoupper($mg_custom_attributes->attribute_code);
					}
				}

				foreach ($mg_products as $mg_product)
				{
					foreach ($TAttrGroupUsed as $k => $group)
					{
						$sanit_features[$TProdAttrGroupByRef[$group]->id] = $TProdAttrValByMgId[$mg_product->custom_attributes[$k]->value]->id;
					}

					$this->saveDolCombination($dol_product, $sanit_features, $mg_product->id, (double) $mg_product->price, (double) $mg_product->weight);
				}
			}
		}

		return 1;
	}

	public function saveDolCombination(&$dol_product, &$sanit_features, $web_id_combination, $web_price, $web_weight)
	{
		global $user;

		$comb = new ProductCombinationDolishop($this->db, $this->api_name);
		$product_comb = $comb->fetchByProductCombination2ValuePairs($dol_product->id, $sanit_features);
		if (! $product_comb)
		{
			if ($this->api_name == 'prestashop') $dol_product->array_options['options_ps_id_product'] = null; // Les déclinaisons ne doivent pas être rattachées directement à cet identifiant
			else if ($this->api_name == 'magento') $dol_product->array_options['options_mg_id_product'] = null; // Les déclinaisons ne doivent pas être rattachées directement à cet identifiant

			$comb->mg_id_combination = $web_id_combination; // Pour Magento, cette valeur n'a pas vraiment de valeur
			$res = $comb->createProductCombination($dol_product, $sanit_features, array(), false, $web_price, $web_weight);
			if ($res < 0)
			{
				$this->error = $comb->db->lasterror();
				$this->errors = $this->error;
			}
		}
		else
		{
			$product_comb->variation_price_percentage = false;
			$product_comb->variation_price = $web_price;
			$product_comb->variation_weight = $web_weight;

			if ($product_comb->update($user) < 0)
			{
				$this->error = $this->db->lasterror(); // $product_comb->db interdit car attribut en private (j'utilise celui de l'objet courrant, ça doit normalement le faire car les objets passés en paramètre sont naturellement des références)
				$this->errors = $this->error;
			}
		}
	}
	
	/**
	 * Crée ou met à jour les combinaisons dans Prestashop
	 * API "combinations" accessible en PUT / POST
	 * 
	 * @global \Dolishop\Conf $conf
	 * @global \Translate	$langs $langs
	 * @param type $web_product
	 * @param type $dol_product
	 * @return int
	 */
	private function saveWebCombinationsFromDolProduct(&$web_product, &$dol_product)
	{
		global $conf,$langs;
		
		if (empty($conf->variants->enabled)) return 1;
		
		require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination2ValuePair.class.php';
		
		$prodcomb = new ProductCombinationDolishop($this->db);
		$TCombination = $prodcomb->fetchAllByFkProductParent($dol_product->id);
		
		if (!is_array($TCombination))
		{
			// error
			return -1;
		}
		
		$TProdAttrValByPsId = ProductAttributeValueDolishop::getAll('id');
		foreach ($TCombination as $comb)
		{
			$web_combination = $this->getWebCombinationFromDolComb($web_product, $comb);
			if ($web_combination)
			{
				$error = 0;
				if ($this->api_name == 'prestashop')
				{
					$ps_combination = &$web_combination->children()->children();
					$ps_combination->price = $comb->variation_price;
					$ps_combination->weight = $comb->variation_weight;
					$ps_combination->id_product = (int) $web_product->id;
					$ps_combination->minimal_quantity = 1; // require
					
					try 
					{
						if ((int) $ps_combination->id > 0)
						{
							$opt = array('resource' => 'combinations',  'putXml' => $web_combination->asXML(), 'id' => (int) $ps_combination->id);
							$result_xml = self::$webService->edit($opt);
						}
						else
						{
							$comb2val = new \ProductCombination2ValuePair($this->db);
							$TValuePair = $comb2val->fetchByFkCombination($comb->id);
							if (!is_array($TValuePair))
							{
								// error
								return -2;
							}
							
							foreach ($TValuePair as $i => $valuePair)
							{
								$ps_combination->associations->product_option_values->product_option_value[$i]->id = $TProdAttrValByPsId[$valuePair->fk_prod_attr_val]->ps_id_option_value;
							}
							
							$opt = array('resource' => 'combinations',  'postXml' => $web_combination->asXML());
							$result_xml = self::$webService->add($opt);
							
							$comb->ps_id_combination = (int) $result_xml->combination->id;
							$comb->updatePsValue(); // TODO gestion d'erreur
						}
					}
					catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
					{
						$error++;
						$this->setError($e);
					}
				}
				
				if ($error == 0)
				{
					if (self::$from_cron_job) $this->output.= $langs->trans('DolishopCronjob_SyncProductCombinationSuccess', $comb->ref)."\n";
				}
				else
				{
					if (self::$from_cron_job) $this->output.= $langs->trans('DolishopCronjob_SyncProductCombinationFail', $comb->ref)."\n";
				}
			}
		}
					
		return 0;
			
	}
	
	private function getWebCombinationFromDolComb(\SimpleXMLElement &$web_product, ProductCombinationDolishop &$comb)
	{
		$web_combination = null;
		
		if ($this->api_name == 'prestashop')
		{
			if (!empty($comb->ps_id_combination) && !empty($web_product->associations->combinations))
			{
				foreach ($web_product->associations->combinations->children() as $ps_combination)
				{
					if ($comb->ps_id_combination == (int) $ps_combination->id)
					{
						$web_combination = $this->getOne('combinations', $comb->ps_id_combination, array(), false);
						break;
					}
				}
			}
		}
		
		if (is_null($web_combination))
		{
			$web_combination = $this->getSchema('combinations');
		}	
			
		return $web_combination;
	}
	
	/**
	 * Dolibarr2Web
	 * Permet de synchroniser les groupes d'attributs et valeurs associées manquants (llx_product_attribute & llx_product_attribute_value)
	 * API product_options & product_option_values accessibles en GET / POST
	 */
	public function syncDolCombinationsOptions()
	{
		if ((float) DOL_VERSION < 6.0) return 0;

		if ($this->api_name == 'prestashop')
		{
			$this->syncDolCombinationsOptionsToPrestashop();
		}
		else if ($this->api_name == 'magento')
		{
			$this->syncDolCombinationsOptionsToMagento();
			// Je force les reload ici, car j'en aurai besoin dans le parcours de la synchro des produits
			ProductAttributeValueDolishop::getAll('id', true, true, $this->api_name);
			ProductAttributeDolishop::getAll('id', true, true, $this->api_name);
		}
	}


	private function syncDolCombinationsOptionsToPrestashop()
	{
		$ps_product_options = $this->getAll('product_options');
		if ($ps_product_options)
		{
			$ps_product_option_values = $this->getAll('product_option_values');

			$TProdAttr = ProductAttributeDolishop::getAll('', true, false);
			$TProdAttrValue = ProductAttributeValueDolishop::getAll('', true, false);

			foreach ($TProdAttr as $productAttr)
			{
				$error = 0;
				$found = false;
				foreach ($ps_product_options->children() as $ps_product_option)
				{
					if ((int) $ps_product_option->id == $productAttr->ps_id_option_group)
					{
						$found = true;
						break;
					}
				}

				if (!$found)
				{
					// need to create
					$xml = $this->getSchema('product_options');
					$product_option = $xml->children()->children();
					$product_option->group_type = 'select';
					$product_option->name->language[0] = $productAttr->ref;
					$product_option->public_name->language[0] = $productAttr->label;

					try
					{
						$opt = array('resource' => 'product_options',  'postXml' => $xml->asXML());
						$result_xml = self::$webService->add($opt);
					}
					catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
					{
						$error++;
						$this->setError($e);
					}

					if ($error == 0)
					{
						$productAttr->ps_id_option_group = (int) $result_xml->product_option->id;
						$productAttr->updatePsValue();
					}
				}

				// Get all values of object
				$TVal = array();
				foreach ($TProdAttrValue as $productAttrValue)
				{
					if ($productAttrValue->fk_product_attribute == $productAttr->id) $TVal[] = $productAttrValue;
				}

				foreach ($TVal as $value)
				{
					$found = false;
					foreach ($ps_product_option_values->children() as $ps_product_option_value)
					{
						if ((int) $ps_product_option_value->id == $value->ps_id_option_value)
						{
							$found = true;
							break;
						}
					}

					if (!$found)
					{
						// need to create
						$xml = $this->getSchema('product_option_values');

						$product_option_value = $xml->children()->children();
						$product_option_value->id_attribute_group = $productAttr->ps_id_option_group;
						$product_option_value->name->language[0] = $value->ref;

						try
						{
							$opt = array('resource' => 'product_option_values',  'postXml' => $xml->asXML());
							$result_xml = self::$webService->add($opt);
						}
						catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
						{
							$error++;
							$this->setError($e);
						}

						if ($error == 0)
						{
							$value->ps_id_option_group = $productAttr->ps_id_option_group;
							$value->ps_id_option_value = (int) $result_xml->product_option_value->id;
							$value->updatePsValue();
						}
					}
				}
			}
		}
	}

	/**
	 * Permet de créer tous les types d'attribut existants dans Dolibarr pour faire des variantes vers Magento
	 * @return int
	 */
	private function syncDolCombinationsOptionsToMagento()
	{
		$TProdAttr = ProductAttributeDolishop::getAll('', true, false, $this->api_name);
		if (empty($TProdAttr)) return 0;

		$TProdAttrValue = ProductAttributeValueDolishop::getAll('', true, false, $this->api_name);

		foreach ($TProdAttr as $productAttr)
		{
			// Get all values of object
			$TVal = $TOption = array();
			foreach ($TProdAttrValue as $productAttrValue)
			{
				if ($productAttrValue->fk_product_attribute == $productAttr->id)
				{
					$TVal[] = $productAttrValue;
					$TOption[] = array(
						'label' => $productAttrValue->value
						,'value' => !empty($productAttrValue->mg_eav_attribute_option_id) ? $productAttrValue->mg_eav_attribute_option_id : null
					);
				}
			}

			$error = 0;

			$body = array(
				'attribute' => array(
					'is_wysiwyg_enabled' => false,
					'is_html_allowed_on_front' => true,
					'used_for_sort_by' => false,
					'is_filterable' => true,
					'is_filterable_in_search' => false,
					'is_used_in_grid' => false,
					'is_visible_in_grid' => false,
					'is_filterable_in_grid' => false,
					//'position' => 'int',
					//'apply_to' => 'string[]',
					'is_searchable' => '0', // string
					'is_visible_in_advanced_search' => '0', // string
					'is_comparable' => '0', // string
					'is_used_for_promo_rules' => '1', // string
					'is_visible_on_front' => '0', // string
					'used_in_product_listing' => '1', // string
					'is_visible' => true,
					'scope' => 'global',
					//'extension_attributes' => '\Swagger\Client\Model\CatalogDataEavAttributeExtensionInterface',
					'attribute_id' => !empty($productAttr->mg_eav_attribute_id) ? $productAttr->mg_eav_attribute_id : null,
					'attribute_code' => strtolower($productAttr->ref),
					'frontend_input' => 'select',
					'entity_type_id' => '4', // string => correspond à "catalog_product"
					'is_required' => false,
					//'options' => '\Swagger\Client\Model\EavDataAttributeOptionInterface[]',
					'options' => $TOption,
					'is_user_defined' => true,
					'default_frontend_label' => $productAttr->label,
					//'frontend_labels' => '\Swagger\Client\Model\EavDataAttributeFrontendLabelInterface[]',
					//'note' => 'string',
					'backend_type' => 'int', // string: varchar | int | text | static
					//'backend_model' => 'string',
					//'source_model' => 'string', // Magento\Eav\Model\Entity\Attribute\Source\Table
					//'default_value' => 'string',
					'is_unique' => '0', // string
					//'frontend_class' => 'string',
					'validation_rules' => array(),
					'custom_attributes' => array()
				)
			);

			try
			{
				// L'update peut être capricieux, il suffit qu'un "mg_eav_attribute_id" soit manquant ou que "options" contienne des ids qui n'existe plus
				if (!empty($productAttr->mg_eav_attribute_id))
				{
					$result = self::$webService->put(array(
						'resource' => '/V1/products/attributes/'.strtolower($productAttr->ref)
						,'body' => $body
					));
				}
				else
				{
					$result = self::$webService->post(array(
						'resource' => '/V1/products/attributes'
						,'body' => $body
					));
				}

			}
			catch (MGWebServiceLibrary\MagentoWebserviceException $e)
			{
				$error++;
				$this->setError($e);
			}

			if ($error == 0)
			{
				$productAttr->mg_eav_attribute_id = $result->attribute_id;
				$productAttr->updateWebValue();

				foreach ($result->options as $mg_option)
				{
					foreach ($TVal as $productAttrValue)
					{
						if ($productAttrValue->value == $mg_option->label)
						{
							$productAttrValue->mg_eav_attribute_option_id = $mg_option->value;
							$productAttrValue->mg_eav_attribute_id = $result->attribute_id;
							$productAttrValue->updateWebValue();
						}
					}
				}
			}
		}
	}

	/**
	 * Web2Dolibarr
	 * Permet de synchroniser les groupes d'attributs et valeurs associées manquants (llx_product_attribute & llx_product_attribute_value)
	 * API product_options & product_option_values accessibles en GET
	 *
	 * @var string	$date_min	Date formated Y-m-d H:i:s (utile que pour une synchro via magento qui ne permet pas d'obtenir une liste simple des atributs de déclinaison sans passer la une fiche produit)
	 *
	 * @global \User $user
	 * @global \Conf $conf
	 * @global \User $user
	 */
	private function syncWebCombinationsOptions($date_min=null)
	{
		global $conf;

		if ($this->api_name == 'prestashop')
		{
			$TProdAttrGroupByPsId = ProductAttributeDolishop::getAllByPsId();
			$ps_product_options = $this->getAll('product_options');
			if ($ps_product_options)
			{
				foreach ($ps_product_options->children() as $ps_product_option)
				{
					if (!isset($TProdAttrGroupByPsId[(int) $ps_product_option->id]))
					{
						$product_attribute = $this->createDolProductAttribute(
							$ps_product_option->name->language[0]->__toString()
							,$ps_product_option->name->language[0]->__toString()
							,$conf->entity
							,(int) $ps_product_option->position
							,(int) $ps_product_option->id
						);

						if ($product_attribute === false)
						{
							$this->error = $product_attribute->db->lasterror();
							$this->errors[] = $this->error;
						}
						else
						{
							$TProdAttrGroupByPsId[$product_attribute->ps_id_option_group] = $product_attribute;
						}
					}
				}
			}
			
			$TProdAttrValByPsId = ProductAttributeValueDolishop::getAllByPsId();
			$ps_product_option_values = $this->getAll('product_option_values');
			if ($ps_product_option_values)
			{
				foreach ($ps_product_option_values->children() as $ps_product_option_value)
				{
					if (!isset($TProdAttrValByPsId[(int) $ps_product_option_value->id]))
					{
						$product_attribute_value = $this->createDolProductAttributeValue(
							$ps_product_option_value->name->language[0]->__toString() . (!empty($ps_product_option_value->color) ? ' ('.$ps_product_option_value->color->__toString().')' : '')
							,$ps_product_option_value->name->language[0]->__toString()
							,$TProdAttrGroupByPsId[(int) $ps_product_option_value->id_attribute_group]->id
							,$conf->entity
							,(int) $ps_product_option_value->id_attribute_group
							,(int) $ps_product_option_value->id
						);

						if ($product_attribute_value === false)
						{
							$this->error = $product_attribute_value->db->lasterror();
							$this->errors[] = $this->error;
						}
						else
						{
							$TProdAttrValByPsId[$product_attribute_value->ps_id_option_value] = $product_attribute_value;
						}
					}
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			// Je force sur la dernière heure, il n'est pas judicieux de récupérer la totalité à chaque fois, surtout que la tâche cron des commandes risque de tourner toute les 5 minutes
			if ($date_min === null) $date_min = date('Y-m-d H:i:s', strtotime('-1 hour'));

			$mg_products = $this->getAll('/V1/products', array(
				'params' => array(
					'searchCriteria[filterGroups][0][filters][0][field]' => 'type_id'
					,'searchCriteria[filterGroups][0][filters][0][value]' => 'configurable'
					,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq' // Greater than or equal
					,'searchCriteria[filterGroups][1][filters][0][field]' => 'updated_at'
					,'searchCriteria[filterGroups][1][filters][0][value]' => $date_min
					,'searchCriteria[filterGroups][1][filters][0][conditionType]' => 'gteq' // Greater than or equal
				)
			));

			if (!empty($mg_products->items))
			{
				$TProdAttrGroupByMgId = ProductAttributeDolishop::getAllByMgId();
				$TProdAttrValByMgId = ProductAttributeValueDolishop::getAllByMgId();

				foreach ($mg_products->items as $item)
				{
					$mg_options = $this->getAll('/V1/configurable-products/'.$item->sku.'/options/all', array(
						'params' => array()
					));

					// Récupération de tous les attributs possible (ATTENTION : tout est mélangé dans Magento)
					$mg_attributes = $this->getAll('/V1/products/attributes', array(
						'params' => array(
							'searchCriteria[filterGroups][0][filters][0][field]' => 'frontend_input' // 'attribute_id'
							,'searchCriteria[filterGroups][0][filters][0][value]' => 'select' // '1'
							,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq' // Greater than or equal
						)
					));

					$TAttributeId = array();
					foreach ($mg_options as $mg_option)
					{
						$TAttributeId[$mg_option->attribute_id] = $mg_option->attribute_id;
					}

					// Création des attributs si non existant (color, size, ...)
					foreach ($TAttributeId as $mg_eav_attribute_id)
					{
						foreach ($mg_attributes->items as $mg_attribute)
						{
							if ($mg_attribute->attribute_id == $mg_eav_attribute_id)
							{
								if (!isset($TProdAttrGroupByMgId[$mg_attribute->attribute_id]))
								{
									$product_attribute = $this->createDolProductAttribute(
										$mg_attribute->default_frontend_label
										,$mg_attribute->attribute_code
										,$conf->entity
										,$mg_attribute->position
										,$mg_attribute->attribute_id
									);

									if ($product_attribute === false)
									{
										$this->error = $this->db->lasterror();
										$this->errors[] = $this->error;
									}
									else
									{
										$TProdAttrGroupByMgId[$mg_attribute->attribute_id] = $product_attribute;
									}
								}

								// TODO vérifier si besoin de créer les valeurs d'attribut (bleu, rouge, taille 38, ...)
								foreach ($mg_attribute->options as $mg_option)
								{
									if (!empty($mg_option->value))
									{
										// $mg_option->value = id de l'option
										if (!isset($TProdAttrValByMgId[$mg_option->value]))
										{
											$product_attribute_value = $this->createDolProductAttributeValue(
												$mg_option->label
												,$mg_option->label
												,$TProdAttrGroupByMgId[$mg_attribute->attribute_id]->id
												,$conf->entity
												,$mg_attribute->attribute_id
												,$mg_option->value
											);

											if ($product_attribute_value === false)
											{
												$this->error = $product_attribute_value->db->lasterror();
												$this->errors[] = $this->error;
											}
											else
											{
												$TProdAttrValByPsId[$mg_option->value] = $product_attribute_value;
											}
										}
									}
								}

								break;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Permet de créer un objet ProductAttribute dans Dolibarr
	 *
	 * @param $label
	 * @param $ref
	 * @param $entity
	 * @param $rang
	 * @param $web_id_attribute
	 *
	 * @return bool|ProductAttributeDolishop
	 */
	private function createDolProductAttribute($label, $ref, $entity, $rang, $web_id_attribute)
	{
		global $user;

		$product_attribute = new ProductAttributeDolishop($this->db, $this->api_name);
		$product_attribute->label = $label;
		$product_attribute->ref = dol_string_nospecial(dol_sanitizeFileName($ref));
		$product_attribute->entity = $entity;
		$product_attribute->rang = $rang;

		if ($this->api_name == 'prestashop') $product_attribute->ps_id_option_group = $web_id_attribute;
		else if ($this->api_name == 'magento') $product_attribute->mg_eav_attribute_id = $web_id_attribute;

		if ($product_attribute->create($user) < 0)
		{
			$this->error = $product_attribute->db->lasterror();
			$this->errors[] = $this->error;
			return false;
		}

		return $product_attribute;
	}

	/**
	 * Permet de créer un objet ProductAttributeValue dans Dolibarr
	 *
	 * @param $label
	 * @param $ref
	 * @param $fk_product_attribute
	 * @param $entity
	 * @param $web_id_attribute
	 * @param $web_id_attribute_value
	 *
	 * @return bool|ProductAttributeValueDolishop
	 */
	private function createDolProductAttributeValue($label, $ref, $fk_product_attribute, $entity, $web_id_attribute, $web_id_attribute_value)
	{
		global $user;

		$product_attribute_value = new ProductAttributeValueDolishop($this->db, $this->api_name);
		$product_attribute_value->value = $label;
		$product_attribute_value->ref = dol_string_nospecial(dol_sanitizeFileName($ref));
		$product_attribute_value->entity = $entity;
		$product_attribute_value->fk_product_attribute = $fk_product_attribute;

		if ($this->api_name == 'prestashop')
		{
			$product_attribute_value->ps_id_option_group = $web_id_attribute;
			$product_attribute_value->ps_id_option_value = $web_id_attribute_value;
		}
		else if ($this->api_name == 'magento')
		{
			$product_attribute_value->mg_eav_attribute_id = $web_id_attribute;
			$product_attribute_value->mg_eav_attribute_option_id = $web_id_attribute_value;
		}

		if ($product_attribute_value->create($user) < 0)
		{
			$this->error = $product_attribute_value->db->lasterror();
			$this->errors[] = $this->error;
			return false;
		}

		return $product_attribute_value;
	}

	private function getTProductCategory($fk_product=0, $force=false, $by='rowid')
	{
		if ($force || empty($this->TCategoryByProductId[$fk_product]) || $this->TCategoryByProductId[$fk_product] === -1)
		{
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

			$TCat = array();

			$sql = 'SELECT DISTINCT c.rowid, c.label, c.description, c.fk_parent, c.import_key';	// Distinct reduce pb with old tables with duplicates
			$sql.= ' FROM '.MAIN_DB_PREFIX.'categorie as c';
			if ($fk_product > 0 && empty($this->from_product_card)) $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_categorie = c.rowid)';
			$sql .= ' WHERE c.entity IN (' . getEntity( 'category') . ')';
			$sql .= ' AND c.type = 0'; // Type product
			if ($fk_product > 0)
			{
				// So ugly but no choice
				if ($this->from_product_card) $sql.= ' AND c.rowid IN ('.implode(',', GETPOST('categories', 'array')).')';
				else $sql.= ' AND cp.fk_product = '.$fk_product;
			}
			$sql .= ' ORDER BY c.fk_parent, c.label';

			$resql = $this->db->query($sql);
			if ($resql)
			{
				while ($obj = $this->db->fetch_object($resql))
				{
					$TCat[$obj->{$by}]['rowid'] = $obj->rowid;
					$TCat[$obj->{$by}]['id'] = $obj->rowid;
					$TCat[$obj->{$by}]['id_parent'] = $obj->fk_parent;
					$TCat[$obj->{$by}]['fk_parent'] = $obj->fk_parent;
					$TCat[$obj->{$by}]['label'] = $obj->label;
					$TCat[$obj->{$by}]['description'] = $obj->description;
					$TCat[$obj->{$by}]['import_key'] = $obj->import_key;
				}

				$this->TCategoryByProductId[$fk_product] = $TCat;
			}
			else
			{
				dol_print_error($this->db);
				$this->TCategoryByProductId[$fk_product] = -1;
			}
		}

		return $this->TCategoryByProductId[$fk_product];
	}
	
	
	private function constructFullTree($arbo, $fk_parent, $TExclude=array())
	{
		$fullarbo = array();
		
		foreach ($arbo as $a)
		{
			if (!empty($TExclude) && in_array($a['id'], $TExclude)) continue;
			
			if (empty($a['label'])) $a['label'] = $a['name']['language'][0];
			if ($a['id_parent'] == $fk_parent)
			{
				$a['children'] = $this->constructFullTree($arbo, $a['id'], $TExclude);
				$fullarbo[] = $a;
			}
		}
		
		return $fullarbo;
	}
	
	/**
	 * Permet d'init un boolean qui indiquera s'il est nécessaire de créer la catégorie Dolibarr vers Prestashop (fonctionne dans le sens inverse)
	 * Si $update_import_key est à true, alors mettra aussi à jour le champ "import_key" de la table llx_categorie (@warning à utiliser uniquement dans le sens Dolibarr / Presta)
	 *
	 * @param array $dol_fullarbo
	 * @param array $web_fullarbo
	 * @param bool	$update_import_key
	 */
	public function syncCategories_checker(&$dol_fullarbo, &$web_fullarbo, $update_import_key=false)
	{
		foreach ($dol_fullarbo as &$dol_cat)
		{
			$found = false;

			if ($web_fullarbo)
			{
				foreach ($web_fullarbo as $web_cat)
				{
					// Pour Dolibarr & Prestashop c'est "label", mais pour Magento c'est "name"
					$dol_label = isset($dol_cat['label']) ? $dol_cat['label'] : $dol_cat['name'];
					$web_label = isset($web_cat['label']) ? $web_cat['label'] : $web_cat['name'];

					if ($dol_label == $web_label)
					{
						$found = true;
						break;
					}
				}
			}
			
			if (!$found)
			{
				$dol_cat['need_to_create'] = true;
			}
			else
			{
				$web_id = $web_cat['id'];
				$web_id_parent = isset($web_cat['id_parent']) ? $web_cat['id_parent'] : $web_cat['parent_id']; // Dolibarr & Prestashop c'est "id_parent", mais pour Magento c'est "parent_id"

				$dol_cat['web_id_parent'] = $web_id_parent;
				$dol_cat['web_id'] = $web_id; // rowid Dolibarr si les paramètres $dol_fullarbo et $web_fullarbo sont passés à l'envers
				if ($update_import_key && $dol_cat['import_key'] != $web_id)
				{
					$this->db->query('UPDATE '.MAIN_DB_PREFIX.'categorie SET import_key = \''.$web_id.'\' WHERE rowid = '.$dol_cat['id']);
				}

				if (isset($dol_cat['children'])) $dol_children = &$dol_cat['children'];
				else if (isset($dol_cat['children_data'])) $dol_children = &$dol_cat['children_data'];
				else $dol_children = array();

				if (isset($web_cat['children'])) $web_children = &$web_cat['children'];
				else if (isset($web_cat['children_data'])) $web_children = &$web_cat['children_data'];
				else $web_children = array();

				// Dolibarr & Prestashop => "children" ; Magento => "children_data"
				$this->syncCategories_checker(
					$dol_children
					,$web_children
					, $update_import_key
				);
			}
		}
	}
	
	private function syncCategoriesD2W_create(&$dol_fullarbo, $fk_parent=0, $force_create=false)
	{
		global $conf;

		foreach ($dol_fullarbo as $dol_cat)
		{
			$force_create_for_children = false;
			if ($force_create || !empty($dol_cat['need_to_create']))
			{
				if ($this->api_name == 'prestashop')
				{
					$schema = $this->getSchema('categories', 'blank');
					$ps_category = $schema->category;

					$ps_category->name->language[0] = $dol_cat['label'];
					$ps_category->description->language[0] = $dol_cat['description'];
					$link_rewrite = dol_string_nospecial(dol_sanitizeFileName($dol_cat['label'], '-'), '-');
					$ps_category->link_rewrite->language[0] = strtolower($link_rewrite);
					$ps_category->active = true;
					$ps_category->id_shop_default = $conf->global->DOLISHOP_SYNC_PS_SHOP_ID;
					$ps_category->id_parent = $fk_parent;

					$opt = array('resource' => 'categories',  'postXml' => $schema->asXML());
				}
				else if ($this->api_name == 'magento')
				{
					$mg_category = array(
						'category' => array(
						'parent_id' => $fk_parent
						,'name' => $dol_cat['label']
						,'is_active' => true
						,'include_in_menu' => true
						)
					);

					$opt = array('resource' => '/V1/categories',  'body' => $mg_category);
				}

				try
				{
					if ($this->api_name == 'prestashop') $response = self::$webService->add($opt);
					else $response = self::$webService->post($opt, array());

					if ($response)
					{
						if ($this->api_name == 'prestashop') $fk_parent_for_children = $response->category->id;
						else $fk_parent_for_children = $response->id;

						$this->db->query('UPDATE '.MAIN_DB_PREFIX.'categorie SET import_key = \''.$fk_parent_for_children.'\' WHERE rowid = '.$dol_cat['id']);

						$force_create_for_children = true;
					}
					else
					{
						// Error: this case should not happen
						break;
					}
				}
				catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
				{
					$this->setError($e);
					break;
				}
				catch (MGWebServiceLibrary\MagentoWebserviceException $e)
				{
					$this->setError($e);
					break;
				}
			}
			else
			{
				$fk_parent_for_children = $dol_cat['web_id'];
			}

			if (!empty($dol_cat['children'])) $this->syncCategoriesD2W_create($dol_cat['children'], $fk_parent_for_children, $force_create_for_children);

		}
	}
	
	public function getCategoriesFullArboFromWeb()
	{
		global $conf;
		
		if ($this->api_name == 'prestashop')
		{
			$ps_categories = $this->getAll('categories', array(
				'display' => '[id,id_parent,id_shop_default,name,description]'
				,'filter[id]' => '>[2]' // 1 = racine, 2 = Accueil
				,'filter[id_shop_default]' => '['.$conf->global->DOLISHOP_SYNC_PS_SHOP_ID.']'
				,'sort' => '[id_parent_ASC,name_ASC]'
			));

			if ($ps_categories)
			{
				$ps_categories = json_decode(json_encode($ps_categories->children()), true); // Cast array
				$ps_fullarbo = $this->constructFullTree($ps_categories['category'], 2);
				return $ps_fullarbo;
			}
		}
		else
		{
			$mg_categories = $this->getAll('/V1/categories', array(
				'return_as_array' => true
				,'params' => array(
					'rootCategoryId' => 2 // 1 = root, 2 default cat (both seems no deletable)
//					,'depth' => 10
				)
			));

			if ($mg_categories && !empty($mg_categories['children_data']))
			{
				return $mg_categories['children_data'];
			}
		}
		
		return false;
	}

	public function getCategoriesFullArboFromDol()
	{
		global $conf;
		
		$TProductCat = $this->getTProductCategory();
		$dol_fullarbo = $this->constructFullTree($TProductCat, 0, $this->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR);

		return $dol_fullarbo;
	}

	// Dolibarr2Web
	public function syncCategoriesD2W()
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

		$dol_fullarbo = array();

		$web_fullarbo = $this->getCategoriesFullArboFromWeb();
		if ($web_fullarbo)
		{
			$dol_fullarbo = $this->getCategoriesFullArboFromDol();

			// tag les catégories devant être create
			$this->syncCategories_checker($dol_fullarbo, $web_fullarbo);

			// Sur Prestashop l'id de la catégorie root par défaut c'est 2 (Accueil)
			// Et sur Magento il semble que la catégorie de base c'est aussi 2
			$id_category_root = !empty($conf->global->DOLISHOP_STORE_ROOT_CATEGORY_ID) ? $conf->global->DOLISHOP_STORE_ROOT_CATEGORY_ID : 2;
			$this->syncCategoriesD2W_create($dol_fullarbo, $id_category_root);
		}
	}

	private function syncCategoriesW2D_create(&$web_fullarbo, $fk_parent=0, $force_create=false)
	{
		global $user,$conf;

		foreach ($web_fullarbo as $web_cat)
		{
			if ($force_create || !empty($web_cat['need_to_create']))
			{
				$dol_cat = new \Categorie($this->db);
				if ($this->api_name == 'prestashop')
				{
					$dol_cat->label = $web_cat['name']['language'][0];
					$dol_cat->description = is_string($web_cat['description']['language'][0]) ? $web_cat['description']['language'][0] : '';
				}
				else if ($this->api_name == 'magento')
				{
					$dol_cat->label = $web_cat['name'];
					$dol_cat->description = ''; // semble pas disponible
				}

				$dol_cat->color = !empty($conf->global->DOLISHOP_COLOR_FOR_CATEGORY) ? $conf->global->DOLISHOP_COLOR_FOR_CATEGORY : '';
				$dol_cat->import_key = $web_cat['id'];
				$dol_cat->visible = 1;
				$dol_cat->fk_parent = $fk_parent;

				$dol_cat->type = \Categorie::TYPE_PRODUCT;
				if ($dol_cat->create($user) > 0)
				{
					$fk_parent_for_children = $dol_cat->id;
					$force_create_for_children = true;
				}
				else
				{
					$this->error = $dol_cat->error;
					$this->errors[] = $this->error;
					break;
				}
			}
			else
			{
				// En amont, la méthode syncCategories_checker() est appelé avec les paramètres à l'envers, ça veut dire que web_id contient le rowid Dolibarr
				$fk_parent_for_children = $web_cat['web_id'];
				$force_create_for_children = false;
			}

			if (!empty($web_cat['children'])) $this->syncCategoriesW2D_create($web_cat['children'], $fk_parent_for_children, $force_create_for_children);
			else if (!empty($web_cat['children_data'])) $this->syncCategoriesW2D_create($web_cat['children_data'], $fk_parent_for_children, $force_create_for_children);
		}
	}

	// Web2Dolibarr
	public function syncCategoriesW2D()
	{
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

		$web_fullarbo = $this->getCategoriesFullArboFromWeb();
		if ($web_fullarbo)
		{
			$dol_fullarbo = $this->getCategoriesFullArboFromDol();

			// tag les catégories devant être create
			$this->syncCategories_checker($web_fullarbo, $dol_fullarbo);

			// create categories in Dolibarr
			$this->syncCategoriesW2D_create($web_fullarbo);
		}
	}
	
	private function createProductFromWebProductId($web_id_product, $web_product_attribute_id=0)
	{
		global $conf;
		
		if ($this->api_name == 'prestashop')
		{
			$ps_product = $this->getOne('products', $web_id_product);
			if ($ps_product)
			{
				$fk_product = $this->saveProductFromWebProduct($ps_product->product, (bool) $conf->global->DOLISHOP_SYNC_PRODUCTS_IMAGES);
				if ($fk_product > 0) 
				{
					if ($web_product_attribute_id > 0) return DolishopTools::getDolProductId(0, '', $web_product_attribute_id);
					else return $fk_product;
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			$mg_product = $this->getOne('/V1/products', $web_id_product);
			if ($mg_product)
			{
				$fk_product = $this->saveProductFromWebProduct($mg_product, (bool) $conf->global->DOLISHOP_SYNC_PRODUCTS_IMAGES);
				if ($fk_product > 0)
				{
					// TODO voir pour la gestion des variantes
					if ($web_product_attribute_id > 0) return DolishopTools::getDolProductId(0, '', $web_product_attribute_id);
					else return $fk_product;
				}
			}
		}
		
		return 0;
	}
	
	
	
	
	public function rsyncOrders($fk_user, $minutes=30, $date_min='')
	{
		global $langs,$user,$conf;

		self::$from_cron_job = true;

		if (empty($conf->global->DOLISHOP_SYNC_ORDERS))
		{
			$this->outpout = $langs->trans('DolishopSyncOrdersIsDisabled');
			return 0;
		}
		
		$user = new \User($this->db);
		if ($user->fetch($fk_user) <= 0 || $user->statut == 0)
		{
			$this->output = $langs->trans('DolishopParameterUserIdNotFound');
			return 1;
		}
		$user->getrights();
		
		if ($this->api_name == 'prestashop' && empty($conf->global->DOLISHOP_SYNC_WEB_ORDER_STATES))
		{
			$this->output = $langs->trans('DolishopMissingPsOrderStatesConf');
			return 1;
		}
		
		if (!empty($date_min))
		{
			if (!\DateTime::createFromFormat("Y-m-d H:i:s", $date_min))
			{
				$this->output = $langs->trans('DolishopParameterDateWithWrongFormat');
				return 1;
			}
		}
		else
		{
			// Autremement on récupère uniquement les commandes de la demi heure passée
			if (!is_numeric($minutes)) $minutes = 30;
			$date_min = date('Y-m-d H:i:s', strtotime('-'.$minutes.' minutes')); // WARNING: possibilité d'avoir 1 heure de décalage entre la BDD Dolibarr et Magento
		}
		
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		
		$this->syncWebCombinationsOptions();
		
		$now = date('Y-m-d H:i:s');
		
		if ($this->api_name == 'prestashop')
		{
			$web_orders = $this->getAll('orders', array(
				'filter[id_shop]' => '['.$conf->global->DOLISHOP_SYNC_PS_SHOP_ID.']'
				,'filter[current_state]' => '['.$conf->global->DOLISHOP_SYNC_WEB_ORDER_STATES.']'
				,'sort' => 'id_DESC'
				,'date' => 1
				,'filter[date_upd]' => '['.$date_min.','.$now.']')
			);
			
			if ($web_orders)
			{
				foreach ($web_orders->children() as $web_order)
				{
					if (!DolishopTools::checkOrderExist($web_order->reference->__toString(), (int) $web_order->id_shop))
					{
						$this->createDolOrder($web_order);
					}
					
					// TODO à voir mais si l'objet contient un id_invoice je dois sans doute créer l'objet dans Dolibarr aussi
					// de plus, une commande peut passer plusieurs fois par là sans avoir de facture au 1er passage mais un id au 2eme
					
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			$options = array(
				'params' => array(
//					'searchCriteria' => ''
					'searchCriteria[filterGroups][0][filters][0][field]' => 'updated_at'
					,'searchCriteria[filterGroups][0][filters][0][value]' => $date_min
//					,'searchCriteria[filterGroups][0][filters][0][value]' => '2018-07-12 12:00:00'
					,'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'gteq' // Greater than or equal
					,'searchCriteria[sortOrders][0][field]' => 'entity_id'
					,'searchCriteria[sortOrders][0][direction]' => 'ASC'
//					,'searchCriteria[pageSize]' => ''
//					,'searchCriteria[currentPage]' => ''
				)
			);

			$i=1;
			$TState = explode('|', $conf->global->DOLISHOP_SYNC_WEB_ORDER_STATES);
			foreach ($TState as $state)
			{
				if (!empty($state))
				{
					$options['params']['searchCriteria[filterGroups]['.$i.'][filters][0][field]'] = 'status';
					$options['params']['searchCriteria[filterGroups]['.$i.'][filters][0][value]'] = implode(',', $TState);
					$options['params']['searchCriteria[filterGroups]['.$i.'][filters][0][conditionType]'] = 'in';
					break;
				}
			}

			$mg_orders = $this->getAll('/V1/orders', $options);

			if ($mg_orders)
			{
				foreach ($mg_orders->items as $mg_order)
				{
					// TODO remove condition
//					if ( $mg_order->increment_id != '000000004' ) continue;

					if (!DolishopTools::checkOrderExist($mg_order->increment_id))
					{
						$this->createDolOrder($mg_order);
					}
				}
			}

		}
		
		$this->output.='FIN';
		return 0;
	}
	
	public function createDolOrder($web_order)
	{
		global $user,$langs,$conf;
		
		$error = 0;
		
		$this->db->begin();


		$commande = new \Commande($this->db);
		if ($this->api_name == 'prestashop') $fk_commande = $this->createDolOrderFromPrestashop($commande, $web_order);
		else if ($this->api_name == 'magento') $fk_commande = $this->createDolOrderFromMagento($commande, $web_order);

//		var_dump($fk_commande, $this->errors, $commande);
//	exit;

		if ($fk_commande < 0) $error++;

//		$this->debugXml($web_order);
		if (!$error && $fk_commande > 0)
		{
			$res = $commande->valid($user);
			if ($res < 0) $error++;
			else
			{
				if ($this->api_name == 'prestashop')
				{
					// Creation d'une expédition brouillon, par défaut Prestashop créé en automatique après chaque commande un order_carriers (expédition)
					$ps_order_carriers = $this->getAll('order_carriers', array('filter[id_order]' => '['.$web_order->id.']'));
					if ($ps_order_carriers && $ps_order_carriers->children()->count() > 0)
					{
						$commande->fetch_lines();
						$res = $this->createDolExpeditionDraft($commande, $ps_order_carriers->children()->order_carrier);
					}
				}
				else if ($this->api_name == 'magento')
				{
					// TODO gestion des expéditions ...?
				}
			}
		}
		
		if ($error)
		{
			$this->db->rollback();
			return -1;
		}


		if ($conf->global->DOLISHOP_STATUS_TO_CREATE_INVOICE === '0' || $conf->global->DOLISHOP_STATUS_TO_CREATE_INVOICE === '1')
		{
			if (empty($commande->lines)) $commande->fetch_lines();

			if (!class_exists('Facture')) require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$facture = new \Facture($this->db);
			$res = $facture->createFromOrder($commande, $user);
			if ($res <= 0)
			{
				$error++;
				$this->error = '('.$commande->ref_client.') '.$facture->error;
				$this->errors[] = $this->error;
				$this->output.= $this->error."\n";
				$this->db->rollback();
				return -2;
			}

			if ($conf->global->DOLISHOP_STATUS_TO_CREATE_INVOICE === '1')
			{
				$res = $facture->validate($user, '', $conf->global->DOLISHOP_DEFAULT_WAREHOUSE_ID);
				if ($res <= 0)
				{
					$error++;
					$this->error = '('.$commande->ref_client.') '.$facture->error;
					$this->errors[] = $this->error;
					$this->output.= $this->error."\n";
					$this->db->rollback();
					return -3;
				}
			}
		}

		$this->db->commit();
		$this->output.= $langs->trans('DolishopNewOrderCreated', $commande->ref, $commande->ref_client)."\n";

		return $commande->id;
	}

	/**
	 * @param $commande
	 * @param $ps_order
	 * @return int
	 */
	private function createDolOrderFromPrestashop(&$commande, $ps_order)
	{
		global $conf,$langs,$user;

		$error = 0;

		$TState = explode('|', $conf->global->DOLISHOP_SYNC_WEB_ORDER_STATES);

		$commande->array_options['options_web_id_order'] = (int) $ps_order->id;

		$current_state = (int) $ps_order->current_state;
		if (!in_array($current_state, $TState)) return 0;

		$commande->ref_client = $ps_order->reference->__toString();

		$TRes = $this->saveDolCustomerAddressFromPrestashop((int) $ps_order->id_customer, (int) $ps_order->id_address_delivery, (int) $ps_order->id_address_invoice);
		$fk_soc = $TRes[0];
		$fk_socpeople_delivery = $TRes[1];
		$fk_socpeople_billing = $TRes[2];

		if (empty($fk_soc) || empty($fk_socpeople_delivery) || empty($fk_socpeople_billing))
		{
			$this->output.= $langs->trans('DolishopCronjob_ErrorSyncCustomersAndAdresses', $this->api_name);
			return -1;
		}

		$commande->socid = $fk_soc;
		$commande->date = strtotime($ps_order->date_add->__toString());
		$commande->date_commande = $commande->date;
		$commande->note_private = ''; // TODO à voir avec la ressource "messages"
		$commande->note_public = '';

//		$commande->cond_reglement_id = GETPOST('cond_reglement_id');
//		$commande->mode_reglement_id = GETPOST('mode_reglement_id');
//		$commande->fk_account = GETPOST('fk_account', 'int'); // TODO peut être une conf global
//		$commande->availability_id = GETPOST('availability_id'); // Delai de livraison
//		$commande->demand_reason_id = GETPOST('demand_reason_id'); // Channel => dictionnaire llx_c_input_reason (Origines des propales/commandes)

		if ($ps_order->delivery_date > '1000-00-00 00:00:00') $commande->date_livraison = strtotime($ps_order->delivery_date->__toString());

		if (!empty(self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $ps_order->id_carrier])) $commande->shipping_method_id = self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $ps_order->id_carrier];
//		$commande->warehouse_id = GETPOST('warehouse_id', 'int'); // TODO conf global ? ->id_warehouse
//		$commande->fk_delivery_address = GETPOST('fk_address');
//		$commande->contactid = GETPOST('contactid');

//		$commande->multicurrency_code = GETPOST('multicurrency_code', 'alpha');
		$commande->multicurrency_tx = (double) $ps_order->conversion_rate;

		if ($commande->create($user) < 0)
		{
			$error++;
			$this->error = $langs->trans('DolishopErrorOrderCreate', $ps_order->reference, $commande->db->lasterror());
			$this->errors[] = $this->error;
			return -2;
		}

		// Ajout contact livraison / facturation
		if (!empty($conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY) && $fk_socpeople_delivery > 0) $commande->add_contact($fk_socpeople_delivery, $conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY, 'external');
		if (!empty($conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING) && $fk_socpeople_billing > 0) $commande->add_contact($fk_socpeople_billing, $conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING, 'external');

		// Ajout des lignes de la commande
		$order_details = $this->getAll('order_details', array('filter[id_order]' => '['.((int) $ps_order->id).']'));
		foreach ($order_details->children() as $order_detail)
		{
			$fk_product = DolishopTools::getDolProductId((int) $order_detail->product_id, $order_detail->product_reference->__toString(), (int) $order_detail->product_attribute_id);
			if ($fk_product == -1)
			{
				if (self::$from_cron_job)
				{
					$this->output.= $langs->trans('DolishopErrorMultipleReferenceFound', (int) $order_detail->product_id, $order_detail->product_reference->__toString())."\n";
				}
				else
				{
					$this->error = $langs->trans('DolishopErrorMultipleReferenceFound', (int) $order_detail->product_id, $order_detail->product_reference->__toString());
					$this->errors[] = $this->error;
				}

				return -3;
			}
			else if ($fk_product == 0 && !empty($conf->global->DOLISHOP_SYNC_WEB_PRODUCT_IF_NOT_EXISTS))
			{
				$fk_product = $this->createProductFromWebProductId((int) $order_detail->product_id, (int) $order_detail->product_attribute_id);
			}

			if ($fk_product > 0) $desc = '';
			else $desc = $order_detail->product_name->__toString();

			$r=$commande->addline(
				$desc
				,(double) $order_detail->unit_price_tax_excl
				,(double) $order_detail->product_quantity
				,DolishopTools::getVatRate((int) $order_detail->associations->taxes->tax->id)
				,0 // $txlocaltax1
				,0 // $txlocaltax2
				,$fk_product
				,0 // $remise_percent
				,0 // $info_bits
				,0 // $fk_remise_except
				,'HT'
				,0 // PU TTC
				,'' // date_start
				,'' // date_end
				,0 // type
				,-1 // rang
				,0
				,0
				,null
				,0 // pa_ht
				,'' // label
				,array() // array_options
			);
			if ($r < 0) return -4;
		}

		if (!empty($ps_order->total_shipping))
		{
			$fk_product = !empty($conf->global->DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE) ? $conf->global->DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE : 0;
			$desc = ($fk_product > 0) ? '' : $langs->trans('DolishopShippingCosts');
			$r=$commande->addline(
				$desc
				,$ps_order->total_shipping_tax_excl
				,1
				,$ps_order->carrier_tax_rate
				,0 // $txlocaltax1
				,0 // $txlocaltax2
				,$fk_product
			);
			if ($r < 0) return -5;
		}

		return $commande->id;
	}

	/**
	 * @param \Commande $commande
	 * @param $mg_order
	 * @return int
	 */
	private function createDolOrderFromMagento(&$commande, $mg_order)
	{
		global $conf,$langs,$user;

		$error = 0;

		$current_state = $mg_order->status;

		$TState = \MgSalesOrderStatuses::getAllLabelByCode();
		if (!isset($TState[$current_state])) return 0;

		$TRes = $this->saveDolCustomerAddressFromMagento($mg_order);
		$fk_soc = $TRes[0];
		$fk_socpeople_delivery = $TRes[1];
		$fk_socpeople_billing = $TRes[2];

		if (empty($fk_soc) || empty($fk_socpeople_delivery) || empty($fk_socpeople_billing))
		{
			$this->output.= $langs->trans('DolishopCronjob_ErrorSyncCustomersAndAdresses', $this->api_name);
			return -1;
		}

		$commande->array_options['options_web_id_order'] = $mg_order->entity_id;
		$commande->ref_client = $mg_order->increment_id;

		$commande->socid = $fk_soc;
		$commande->date = strtotime($mg_order->updated_at);
		$commande->date_commande = $commande->date;

		$commande->note_private = ''; // TODO à voir avec la ressource "messages"
		if (!empty($mg_order->coupon_code)) $commande->note_private = $langs->trans('DiscountCodeUsed', $mg_order->coupon_code, $mg_order->base_discount_amount);

		if (!empty($mg_order->status_histories))
		{
			foreach($mg_order->status_histories as $commentaire)
			{
				$commande->note_private.= '['.$commentaire->created_at.'] ('.$TState[$current_state].')'."\n";
				$commande->note_private.= $commentaire->comment."\n\n";
			}
		}
		$commande->note_public = '';

//		$commande->cond_reglement_id = GETPOST('cond_reglement_id');
//		$commande->mode_reglement_id = GETPOST('mode_reglement_id'); // TODO * => $mg_order->payment->method (ex: banktransfer)
//		$commande->fk_account = GETPOST('fk_account', 'int'); // TODO peut être une conf global
//		$commande->availability_id = GETPOST('availability_id'); // Delai de livraison
//		$commande->demand_reason_id = GETPOST('demand_reason_id'); // Channel => dictionnaire llx_c_input_reason (Origines des propales/commandes)

		// TODO Méthode d'expédition
//		if (!empty(self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $ps_order->id_carrier])) $commande->shipping_method_id = self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $ps_order->id_carrier];
//		$commande->warehouse_id = GETPOST('warehouse_id', 'int'); // TODO conf global ? ->id_warehouse
//		$commande->fk_delivery_address = GETPOST('fk_address');
//		$commande->contactid = GETPOST('contactid');

//		$commande->multicurrency_code = GETPOST('multicurrency_code', 'alpha');
		$commande->multicurrency_tx = (double) $mg_order->base_to_order_rate; // TODO à déterminer si c'est bien la bonne valeur, autrement il y a l'attribut "base_currency_code" (ex: EUR)

		if ($commande->create($user) < 0)
		{
			$this->error = $langs->trans('DolishopErrorOrderCreate', $mg_order->increment_id, $commande->db->lasterror());
			$this->errors[] = $this->error;
			return -2;
		}

		// Ajout contact livraison / facturation
		if (!empty($conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY) && $fk_socpeople_delivery > 0) $commande->add_contact($fk_socpeople_delivery, $conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_DELIVERY, 'external');
		if (!empty($conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING) && $fk_socpeople_billing > 0) $commande->add_contact($fk_socpeople_billing, $conf->global->DOLISHOP_EXTERNAL_TYPE_FOR_CONTACT_BILLING, 'external');

		// Ajout des lignes de la commande
//		$order_details = $this->getAll('order_details', array('filter[id_order]' => '['.((int) $ps_order->id).']'));
		foreach ($mg_order->items as $mg_order_line)
		{
			// TODO use subtotal
			// Si la commande contient un produit de type Bundle, alors le détail est aussi présent dans la commande
			// l'équivalent du type Bundle dans Dolibarr serait un titre de sous total
			if ($mg_order_line->product_type == 'bundle') continue;
			else if ($mg_order_line->product_type == 'configurable') continue; // Petite subtilité, il faudra utiliser l'attribut "parent_item" du produit choisi pour avoir le détail du prix...

			$fk_product = DolishopTools::getDolProductId($mg_order_line->product_id, $mg_order_line->sku, 0);
			if ($fk_product == -1)
			{
				if (self::$from_cron_job)
				{
					$this->output.= $langs->trans('DolishopErrorMultipleReferenceFound', $mg_order_line->product_id, $mg_order_line->sku)."\n";
				}
				else
				{
					$this->error = $langs->trans('DolishopErrorMultipleReferenceFound', $mg_order_line->product_id, $mg_order_line->sku);
					$this->errors[] = $this->error;
				}

				return -3;
			}
			else if ($fk_product == 0 && !empty($conf->global->DOLISHOP_SYNC_WEB_PRODUCT_IF_NOT_EXISTS))
			{
				// TODO voir comment récupérer l'info s'il s'agit d'un produit décliné pour pouvoir le rattacher au parent
//				var_dump($mg_order_line);exit;
				$fk_product = $this->createProductFromWebProductId($mg_order_line->sku, 0);
			}

			if ($fk_product > 0) $desc = '';
			else $desc = $mg_order_line->name;

			$obj_detail = $mg_order_line;
			if (!empty($mg_order_line->parent_item)) $obj_detail = $mg_order_line->parent_item; // Subtilité des produits configurables

			$r=$commande->addline(
				$desc
				,(double) $obj_detail->price
				,(double) $obj_detail->qty_ordered
				,$obj_detail->tax_percent
				,0 // $txlocaltax1
				,0 // $txlocaltax2
				,$fk_product
				,$obj_detail->discount_percent // $remise_percent
				,0 // $info_bits
				,0 // $fk_remise_except
				,'HT'
				,0 // PU TTC
				,'' // date_start
				,'' // date_end
				,0 // type
				,-1 // rang
				,0
				,0
				,null
				,0 // pa_ht
				,'' // label
				,array('options_mg_item_id' => $mg_order_line->item_id) // array_options
			);

			if ($r < 0) return -4;
		}

		if (!empty($mg_order->shipping_amount))
		{
			$fk_product = !empty($conf->global->DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE) ? $conf->global->DOLISHOP_DEFAULT_ID_SHIPPING_SERVICE : 0;
			$desc = ($fk_product > 0) ? '' : $langs->trans('DolishopShippingCosts');
			$r=$commande->addline(
				$desc
				,$mg_order->shipping_amount
				,1
				,(($mg_order->shipping_incl_tax / $mg_order->shipping_amount) - 1) * 100 // Calcul de la tva
				,0 // $txlocaltax1
				,0 // $txlocaltax2
				,$fk_product
			);
			if ($r < 0) return -5;
		}

		return $commande->id;
	}

	/**
	 * @param int    $web_id_customer
	 * @param string $name
	 * @param string $email
	 * @param string $firstname
	 * @param string $lastname
	 * @param string $civility_id
	 * @param int    $entity
	 * @param string $code_client
	 * @param int    $status
	 * @param int    $client
	 * @param int    $fournisseur
	 * @param int    $tva_assuj
	 * @param int    $typent_id			8 = Particulier
	 * @param string $default_lang		en_US, fr_FR ...
	 */
	private function saveSociete($web_id_customer, $name, $email, $firstname='', $lastname='', $civility_id='MR', $entity=1, $code_client='auto', $status=1, $client=1, $fournisseur=0, $tva_assuj=1, $typent_id = 8, $default_lang='')
	{
		global $user;

		$fk_soc = DolishopTools::getSociete($web_id_customer, $email);

		$societe = new \Societe($this->db);
		if ($fk_soc > 0) $societe->fetch($fk_soc);

		$societe->name = $name;
		$societe->email = $email;
		$societe->firstname = $firstname;
		$societe->name_bis = $lastname;
		$societe->civility_id = $civility_id;
		$societe->status = $status;
		$societe->client = $client;
		$societe->code_client = $code_client;
		$societe->fournisseur = $fournisseur;

		$societe->tva_assuj = $tva_assuj;
		$societe->typent_id = $typent_id; // Particulier // TODO mettre en conf module ?
		$societe->typent_code = dol_getIdFromCode($this->db, $societe->typent_id, 'c_typent', 'id', 'code');	// Force typent_code too so check in verify() will be done on new type

		$societe->entity = $entity;
		$societe->default_lang = $default_lang;

		if (!empty($societe->id))
		{
			$societe->update('', $user);
		}
		else
		{
			$societe->array_options['options_web_id_customer'] = $web_id_customer;
			if ($societe->create($user) <= 0) return false;
		}

		return $societe;
	}

	private function saveDolCustomerAddressFromPrestashop($web_id_customer, $web_id_address_delivery, $web_id_address_invoice)
	{
		global $conf;

		$ps_customer = $this->getOne('customers', $web_id_customer);
		if ($ps_customer)
		{
			$ps_customer = $ps_customer->customer;

			if ((int) $ps_customer->id_gender == 1) $civility_id = 'MR';
			else if ((int) $ps_customer->id_gender == 2) $civility_id = 'MME';
			else $civility_id = '';

			$societe = $this->saveSociete($web_id_customer, dolGetFirstLastname($ps_customer->firstname->__toString(), $ps_customer->lastname->__toString()), $ps_customer->email->__toString(), $ps_customer->firstname->__toString(), $ps_customer->lastname->__toString(), $civility_id, $conf->entity);

			$fk_socpeople_delivery = $this->saveDolContactFromPrestashop($societe, $ps_customer, $web_id_address_delivery);

			if ($web_id_address_delivery == $web_id_address_invoice) $fk_socpeople_billing = $fk_socpeople_delivery;
			else $fk_socpeople_billing = $this->saveDolContactFromPrestashop($societe, $ps_customer, $web_id_address_invoice);

			return array($societe->id, $fk_socpeople_delivery, $fk_socpeople_billing);
		}

		return array(0, 0, 0);
	}

	private function saveDolContactFromPrestashop(&$societe, &$ps_customer, $web_id_address)
	{
		$ps_address = $this->getOne('addresses', $web_id_address);
		if ($ps_address)
		{
			$ps_address = $ps_address->address;

			$contact = $this->saveDolContact(
				$societe
				,$web_id_address
				,$ps_address->lastname->__toString()
				,$ps_address->firstname->__toString()
				,array($ps_address->address1->__toString(), $ps_address->address2->__toString())
				,$ps_address->postcode->__toString()
				,$ps_address->city->__toString()
				,$ps_address->phone->__toString()
				,strtotime($ps_customer->birthday->__toString().' 12:00:00')
				,!empty(self::$ps_configuration['COUNTRIES_ID'][(int) $ps_address->id_country]) ? !empty(self::$ps_configuration['COUNTRIES_ID'][(int) $ps_address->id_country]) : null
				,1
				,0
			);

			return $contact->id;
		}

		return 0;
	}

	private function saveDolCustomerAddressFromMagento(&$mg_order)
	{
		global $conf;
		if ($mg_order->customer_gender == 1) $civility_id = 'MR';
		else if ($mg_order->customer_gender == 2) $civility_id = 'MME';
		else $civility_id = '';

// TODO faire la gestion d'erreur si les create societe/contact ne fonctionnent pas
		if (!empty($mg_order->customer_id))	$societe = $this->saveSociete($mg_order->customer_id, dolGetFirstLastname($mg_order->customer_firstname, $mg_order->customer_lastname), $mg_order->customer_email, $mg_order->customer_firstname, $mg_order->customer_lastname, $civility_id, $conf->entity);
		// Commande en mode anonyme (sans connexion)
		else $societe = $this->saveSociete(0, dolGetFirstLastname($mg_order->billing_address->firstname, $mg_order->billing_address->lastname), $mg_order->customer_email, $mg_order->billing_address->firstname, $mg_order->billing_address->lastname, $civility_id, $conf->entity);

		$contact_billing = $this->saveDolContact(
			$societe
			,$mg_order->billing_address->customer_address_id
			,$mg_order->billing_address->lastname
			,$mg_order->billing_address->firstname
			,$mg_order->billing_address->street // il s'agit d'un tableau
			,$mg_order->billing_address->postcode
			,$mg_order->billing_address->city
			,$mg_order->billing_address->telephone
			,$mg_order->billing_address->email
			,strtotime($mg_order->customer_dob)
			,\DolCountry::getIdFromCode($mg_order->billing_address->country_id)
			,1
			,0
		);

		if (
			!empty($mg_order->extension_attributes->shipping_assignments[0]->shipping->address)
			&& $mg_order->extension_attributes->shipping_assignments[0]->shipping->address != $mg_order->billing_address->customer_address_id
		)
		{
			$address_delivery = &$mg_order->extension_attributes->shipping_assignments[0]->shipping->address;
			$contact_delivery = $this->saveDolContact(
				$societe
				,$address_delivery->customer_address_id
				,$address_delivery->lastname
				,$address_delivery->firstname
				,$address_delivery->street // il s'agit d'un tableau
				,$address_delivery->postcode
				,$address_delivery->city
				,$address_delivery->telephone
				,$address_delivery->email
				,strtotime($mg_order->customer_dob)
				,\DolCountry::getIdFromCode($address_delivery->country_id)
				,1
				,0
			);
		}
		else
		{
			$contact_delivery = $contact_billing; // Même adresse
		}

		return array($societe->id, $contact_delivery->id, $contact_billing->id);
	}

	/**
	 * @param \Societe		$societe
	 * @param int			$web_id_address
	 * @param string		$lastname
	 * @param string		$firstname
	 * @param string|array	$address
	 * @param string		$zip
	 * @param string		$town
	 * @param string		$phone_pro
	 * @param int			$birthday_timestamp
	 * @param int			$country_id
	 * @param int			$statut
	 * @param int			$priv
	 * @return \Contact
	 */
	private function saveDolContact(&$societe, $web_id_address, $lastname, $firstname, $address, $zip, $town, $phone_pro, $email, $birthday_timestamp, $country_id, $statut = 1, $priv = 0)
	{
		global $user;

		if (!class_exists('\Contact')) require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

		$contact = new \Contact($this->db);
		$fk_socpeople = DolishopTools::getContact($web_id_address);
		if ($fk_socpeople > 0) $contact->fetch($fk_socpeople);

		$contact->name = $lastname;
		$contact->firstname = $firstname;
		$contact->civility_id = $societe->civility_id;
		$contact->address = is_array($address) ? implode("\n", $address) : $address;
		$contact->email = empty($email) ? $societe->email : $email;
		$contact->zip = $zip;
		$contact->town = $town;
		$contact->phone_pro = $phone_pro;
		$contact->birthday = $birthday_timestamp;
		$contact->country_id = $country_id;
//		$contact->state_id          = $societe->state_id;

		if (!empty($contact->id))
		{
			$result = $contact->update($contact->id, $user);
		}
		else
		{
			$contact->statut = $statut;
			$contact->priv = $priv;
			$contact->socid = $societe->id;	// fk_soc
			$result = $contact->create($user);
		}

		return $contact;
	}
	
	private function createDolExpeditionDraft(\Commande &$commande, \SimpleXMLElement $web_order_carrier)
	{
		global $user,$conf;
		
		if (!class_exists('\Expedition')) require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
		
		$expedition = new \Expedition($this->db);
		$expedition->ref_customer = $commande->ref_client;
		$expedition->fk_project = $commande->fk_project;
		$expedition->date_delivery = $commande->date_livraison;
		$expedition->socid = $commande->socid;
		$expedition->weight = $web_order_carrier->weight->__toString();
		$expedition->weight_units = 0; // TODO voir comment synchro les unités (à = kg)
		if (!empty(self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $web_order_carrier->id_carrier])) $expedition->shipping_method_id = self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $web_order_carrier->id_carrier];
		else $expedition->shipping_method_id = $commande->shipping_method_id;
		
		$expedition->weight = 'NULL';
	    $expedition->sizeH = 'NULL';
	    $expedition->sizeW = 'NULL';
	    $expedition->sizeS = 'NULL';
		$expedition->size_units = 0; // TODO voir comment synchro les unités (o = mètre)
		
		$expedition->origin = $commande->element;
        $expedition->origin_id = $commande->id;
		
		foreach ($commande->lines as &$line)
		{
			if ($line->product_type == \Product::TYPE_PRODUCT) $expedition->addline($conf->global->DOLISHOP_DEFAULT_WAREHOUSE_ID, $line->id, $line->qty);
		}
		
		$expedition->array_options['options_web_id_order_carrier'] = (int) $web_order_carrier->id;
		
		$res = $expedition->create($user);
		return $res;
	}
	
	public function setWebOrderAsShipped($web_id_order, \Expedition $expedition)
	{
		global $conf;
		
		if ($conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING < 0) return -1;
		
		if ($this->api_name == 'prestashop')
		{
			$error = 0;
			try
			{
				$ps_order = $this->getOne('orders', $web_id_order, array(), false);
				if ($ps_order)
				{
					// J'édite la commande distante que si son statut n'a pas encore était modifié (il est possible de faire plusieurs expéditions)
					if ($ps_order->order->current_state != $conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING)
					{
						$ps_order->order->current_state = $conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CREATE_SHIPPING;
						$opt = array('resource' => 'orders',  'putXml' => $ps_order->asXML(), 'id' => $web_id_order);
						$result_xml = self::$webService->edit($opt);
					}
					
					$schema_order_carrier = $this->getSchema('order_carriers', 'blank');
					
					$ps_order_carriers = $this->getAll('order_carriers', array('filter[id_order]' => '['.$web_id_order.']'));
					if (!empty($expedition->array_options['options_web_id_order_carrier']))
					{
						foreach ($ps_order_carriers->children() as $ps_order_carrier)
						{
							if ((int) $ps_order_carrier->id == $expedition->array_options['options_web_id_order_carrier'])
							{
								foreach ($ps_order_carrier as $nodeKey => $node)
								{
									$schema_order_carrier->order_carrier->{$nodeKey} = $node;
								}
								break;
							}
						}
					}
					
					$schema_order_carrier->order_carrier->id_order = $ps_order->order->id;
					$schema_order_carrier->order_carrier->id_order_invoice = $ps_order->order->invoice_number;
					
					// Il possible aussi que le transporteur change, donc s'il a bien était modifié sur l'expédition Dolibarr et que celui-ci correspond à quelque chose de configuré, alors j'utilise cet identifiant de transporteur sinon c'est celui par défaut de la commande Prestashop
					$id_carrier = array_search($expedition->shipping_method_id, Webservice::$ps_configuration['WEB_SHIPPING_ASSOC']);
					$schema_order_carrier->order_carrier->id_carrier = ($id_carrier !== false) ? $id_carrier : $ps_order->order->id_carrier;

					if ($ps_order_carriers->children()->count() == 0)
					{
						// J'applique les frais de livraison qu'à la première expédition
						$schema_order_carrier->order_carrier->shipping_cost_tax_excl = $ps_order->order->total_shipping_tax_excl;
						$schema_order_carrier->order_carrier->shipping_cost_tax_incl = $ps_order->order->total_shipping_tax_incl;
					}
					
					$schema_order_carrier->order_carrier->weight = $expedition->trueWeight; // TODO faire la concordance des unités
					if (!empty($expedition->tracking_number)) $schema_order_carrier->order_carrier->tracking_number = $expedition->tracking_number;
//					if (!empty($expedition->date_delivery)) $schema_order_carrier->order_carrier->date_add = date('Y-m-d H:i:s', $expedition->date_delivery);
					
					// le champ en base s'appel "id_order_carrier" mais "id" au niveau de l'objet
					if ((int) $schema_order_carrier->order_carrier->id > 0)
					{
						$opt = array('resource' => 'order_carriers',  'putXml' => $schema_order_carrier->asXML(), 'id' => (int) $schema_order_carrier->order_carrier->id);
						$result_xml = self::$webService->edit($opt);
					}
					else
					{
						$opt = array('resource' => 'order_carriers',  'postXml' => $schema_order_carrier->asXML());
						$result_xml = self::$webService->add($opt);
					}
					
					$expedition->array_options['options_web_id_order_carrier'] = (int) $result_xml->order_carrier->id;
					$res = $expedition->updateExtraField('web_id_order_carrier');
					if ($res < 0) $this->errors[] = $expedition->error;
				}
			}
			catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
			{
				$error++;
				$this->setError($e);
			}
			
			if ($error) return -2;
					
			return 1;
		}
		else if ($this->api_name == 'magento')
		{
			$error = 0;
			$body = array(
				'items' => array()
			);

			require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

			foreach ($expedition->lines as $line)
			{
				$commandedet = new \OrderLine($this->db);
				$commandedet->fetch($line->fk_origin_line);
				if (empty($commandedet->id)) $commandedet->id = $commandedet->rowid; // <= 8.0
				$commandedet->fetch_optionals();

				if ($commandedet->array_options['options_mg_item_id'] > 0)
				{
					$body['items'][] = array(
						'extension_attributes' => array()
						,'order_item_id' => $commandedet->array_options['options_mg_item_id'] // Si cette valeur est vide il sera impossible de faire une expedition
						,'qty' => $line->qty_shipped
					);
				}
			}

			try
			{
				$mg_shipping_id = self::$webService->post(array(
					'resource' => '/V1/order/'.$web_id_order.'/ship'
					,'body' => $body
				));
			}
			catch (MGWebServiceLibrary\MagentoWebserviceException $e)
			{
				$error++;
				$this->setError($e);
			}

			if (!$error)
			{
				$expedition->array_options['options_web_id_order_carrier'] = $mg_shipping_id;
				$expedition->updateExtraField('web_id_order_carrier');

				return $mg_shipping_id;
			}

			return -1;
		}
		
		return 0;
	}

	/**
	 * @param Facture $object
	 */
	public function createWebInvoice($object)
	{
		if ($this->api_name == 'prestashop')
		{
			// TODO ...
		}
		else if ($this->api_name == 'magento')
		{
			$error = 0;
			$body = array(
				'items' => array()
			);


			// /V1/order/{orderId}/invoice

//			$apiInstance = new \Swagger\Client\Api\SalesInvoiceOrderV1Api(
//			// If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
//			// This is optional, `GuzzleHttp\Client` will be used as default.
//				new \GuzzleHttp\Client()
//			);
//			$order_id = 56; // int |
//			$body = new \Swagger\Client\Model\Body77(); // \Swagger\Client\Model\Body77 |
//
//			try {
//				$result = $apiInstance->salesInvoiceOrderV1ExecutePost($order_id, $body);
//				print_r($result);
//			} catch (Exception $e) {
//				echo 'Exception when calling SalesInvoiceOrderV1Api->salesInvoiceOrderV1ExecutePost: ', $e->getMessage(), PHP_EOL;
//			}


			foreach ($object->lines as $line)
			{
				if (empty($line->array_options)) $line->fetch_optionals();

				if ($line->array_options['options_mg_item_id'] > 0)
				{
					$body['items'][] = array(
						'extension_attributes' => array()
						,'order_item_id' => $line->array_options['options_mg_item_id'] // Si cette valeur est vide il sera impossible de faire une expedition
						,'qty' => $line->qty
					);
				}
			}
			//var_dump($object->array_options['web_id_order'], $body);exit;
			try
			{
				$mg_invoice_id = self::$webService->post(array(
					'resource' => '/V1/order/'.$object->array_options['options_web_id_order'].'/invoice'
					,'body' => $body
				));
			}
			catch (MGWebServiceLibrary\MagentoWebserviceException $e)
			{
				$error++;
				$this->setError($e);
			}

			if (!$error)
			{
				$object->array_options['options_web_id_invoice'] = $mg_invoice_id;
				$object->updateExtraField('web_id_invoice');

				return $mg_invoice_id;
			}

			return -1;
		}

		return 0;
	}
	
	public function setWebOrderAsDelivered($web_id_order)
	{
		global $conf;
		
		if ($conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED < 0) return -1;
		
		$error = 0;
		if ($this->api_name == 'prestashop')
		{
			try
			{
				$ps_order = $this->getOne('orders', $web_id_order, array(), false);
				if ($ps_order)
				{
					// Check du statut dès fois que Dolibarr ai eu un petit souci tech et que le statut soit déjà mis à jour sur Prestashop
					if ($ps_order->order->current_state != $conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED)
					{
						$ps_order->order->current_state = $conf->global->DOLISHOP_UPDATE_WEB_ORDER_ON_CLOSE_AS_DELIVERED;
						$opt = array('resource' => 'orders',  'putXml' => $ps_order->asXML(), 'id' => $web_id_order);
						$result_xml = self::$webService->edit($opt);
					}
				}
			}
			catch (PSWebServiceLibrary\PrestaShopWebserviceException $e)
			{
				$error++;
				$this->setError($e);
			}
		}
		else if ($this->api_name == 'magento')
		{
			// Rien à faire ici, le statut est mis à jour en automatique sur création/validation d'expédition
		}
		
		if ($error) return -2;
		else return 1;
	}
	/**
	 * Méthode qui valorise simplement les attributs "error" et "errors" de l'objet courant 
	 * si une erreur lors d'un appel au webservice est remontée
	 * 
	 * @global Translate $langs
	 * @param PSWebServiceLibrary\PrestaShopWebserviceException | MGWebServiceLibrary\MagentoWebserviceException $e
	 * @return boolean
	 */
	private function setError($e)
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
	 * Renvoie le contenu de self::$ps_configuration dans un format HTML pour être print
	 * 
	 * @global Translate $langs
	 * @return string
	 */
	public function getFormatedStringTConf()
	{
		global $langs;
		
		$str = '';
		
		if (!empty(self::$ps_configuration['PS_LANGUAGES']))
		{
			$str.= '<div class="titre" style="font-weight:bold;"><i class="fa fa-language"></i> '.$langs->trans('DolishopPsLanguages').'</div>';
			$str.= '<table class="noborder" width="100%">';
			$str.= '<tr class="liste_titre">
						<th align="center" width="10%">id_lang</th>
						<th align="center" width="25%">language_code</th>
						<th align="center" width="25%">iso</th>
						<th align="center" width="25%">code Dolibarr</th>
						<th align="center">Active</th>
					</tr>';
			foreach (self::$ps_configuration['PS_LANGUAGES'] as $ps_id_lang => $Tab)
			{
				$str.= '<tr class="oddeven">';
				
				$str.= '<td align="center">'.$Tab['id'].'</td>';
				$str.= '<td align="center">'.$Tab['language_code'].'</td>';
				$str.= '<td align="center">'.$Tab['iso_code'].'</td>';
				$str.= '<td align="center">'.$Tab['dol_iso_code'].'</td>';
				$str.= '<td align="center">'.$Tab['active'].'</td>';

				$str.= '</tr>';
			}
			$str.= '</table>';
		}
		
		if (!empty(self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products']))
		{
			if (!empty($str)) $str.= '<br /><br />';
			$str.= '<div class="titre" style="font-weight:bold;"><i class="fa fa-image"></i></i> '.$langs->trans('DolishopPsImagesMimeTypes').'</div>';
			$str.= '<p class="">'.$langs->trans('DolishopPsImagesMimeTypesAllowed', implode(', ', self::$ps_configuration['PS_IMAGES_MIME_TYPES']['products'])).'</p>';
		}
		
		if (!empty(self::$ps_configuration['PS_TAXES']))
		{
			if (!empty($str)) $str.= '<br /><br />';
			$str.= '<div class="titre" style="font-weight:bold;"><i class="fa fa-balance-scale"></i> '.$langs->trans('DolishopPsTaxes').'</div>';
			$str.= '<table class="noborder " width="100%">';
			$str.= '<tr class="liste_titre">
						<th align="center" width="10%">%</th>
						<th></th>
						<th align="center">id_tax</th>
						<th align="center">id_tax_rules_group</th>
					</tr>';
			foreach (self::$ps_configuration['PS_TAXES'] as $vat_rate => $Tab)
			{
				$str.= '<tr class="oddeven">';

				$str.= '<td align="center">'.$vat_rate.'</td>';
				$str.= '<td>';
				foreach ($Tab['TLabel'] as $label) $str.= $label.'<br />';
				$str.= '</td>';
				$str.= '<td align="center">';
				foreach ($Tab['TId_tax'] as $id_tax) $str.= $id_tax.'<br />';
				$str.= '</td>';
				$str.= '<td align="center">';
				foreach ($Tab['TId_tax_rules_group'] as $id_tax_rules_group) $str.= $id_tax_rules_group.'<br />';
				$str.= '</td>';
				
				$str.= '</tr>';
			}
			$str.= '</table>';
		}
		
		return $str;
	}
	
	public function WsGetAllProductsCategories()
	{
		global $conf;
		
		$TCateg = array();
		
		if ($this->api_name == 'prestashop')
		{
			$more_opt = array('display'=>'[id,id_parent,name]', 'filter[id]'=>'>[1]'); // > 1 pour ne pas avoir la categorie nommée "Racine" qui est visible uniquement en bdd
			if (!empty($conf->global->DOLISHOP_SYNC_PS_SHOP_ID)) $more_opt['filter[id_shop_default]'] = '['.$conf->global->DOLISHOP_SYNC_PS_SHOP_ID.']';
			$xml = $this->getAll('categories', $more_opt);
			if ($xml)
			{
				foreach ($xml->children() as $child) 
				{
					$TCateg[(int) $child->id] = $child->name->language[0]->__toString();
				}
				
				asort($TCateg);
			}
		}
		else if ($this->api_name == 'magento')
		{
//			$mg_categories = $this->getAll('/V1/categories', array(
//				'return_as_array' => true
//				,'params' => array(
//					'rootCategoryId' => 2 // 1 = root, 2 default cat (both seems no deletable)
////					,'depth' => 10
//				)
//			));

			$sql = 'SELECT label, import_key FROM '.MAIN_DB_PREFIX.'categorie';
			$sql.= ' WHERE import_key > 0';
			$sql.= ' AND entity = '.$conf->entity;
			$sql.= ' AND type = 0'; // 0 = TYPE_PRODUCT
			$sql.= ' ORDER BY fk_parent, label';

			$resql = $this->db->query($sql);
			if ($resql)
			{
				while ($row = $this->db->fetch_object($resql))
				{
					$TCateg[$row->import_key] = $row->label;
				}
			}
		}

		return $TCateg;
	}
	
	public function WsGetAllCountries()
	{
		global $conf;
		
		$TCountry = array();
		
		if ($this->api_name == 'prestashop')
		{
			$more_opt = array('filter[active]'=>'[1]');
			if (!empty($conf->global->DOLISHOP_SYNC_PS_SHOP_ID)) $more_opt['id_shop'] = $conf->global->DOLISHOP_SYNC_PS_SHOP_ID;
			$xml = $this->getAll('countries', $more_opt);
			if ($xml)
			{
				foreach ($xml->children() as $child)
				{
					$TCountry[(int) $child->id] = $child->name->language[0]->__toString();
				}
			}	
		}
		
		
		return $TCountry;
	}

	public function WsGetAllShops()
	{
		$TShop = array();

		if ($this->api_name == 'prestashop')
		{
			$ps_shops = $this->getAll('shops', array());
			if ($ps_shops)
			{
				foreach ($ps_shops->children() as $ps_shop)
				{
					$TShop[(int) $ps_shop->id] = $ps_shop->name->__toString();
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			$TShop['default'] = 'DolishopMagentoDefaultStoreCode';
			$TShop['all'] = 'DolishopMagentoAllStoreCode';

			$mg_shops = $this->getAll('/V1/store/storeViews', array());
			if ($mg_shops)
			{
				foreach ($mg_shops as $mg_shop)
				{
					if ($mg_shop->id > 0) $TShop[$mg_shop->code] = $mg_shop->name;
				}
			}
		}

		echo($this->error);

		return $TShop;
	}

	public function WsGetAllOrderStates()
	{
		$TOrderState = array();

		if ($this->api_name == 'prestashop')
		{
			$order_states = $this->getAll('order_states', array());
			if ($order_states)
			{
				foreach ($order_states->children() as $order_state)
				{
					$TOrderState[(int) $order_state->id] = $order_state->name->language[0]->__toString();
				}
			}
		}
		else if ($this->api_name == 'magento')
		{
			// Non récupérable par l'API, il faut avoir saisie les codes dans le dictionnaire c_mg_sales_order_statuses avant
			$TOrderState = \MgSalesOrderStatuses::getAllLabelByCode();
		}

		return $TOrderState;
	}

	public function WsGetAllShippingMethods()
	{
		$TShippingMethod = array();

		if ($this->api_name == 'prestashop')
		{
			$carriers = $this->getAll('carriers', array());
			foreach ($carriers->children() as $carrier)
			{
				$TShippingMethod[(int) $carrier->id] = array(
					'id' => (int) $carrier->id
					,'label' => $carrier->name->__toString()
					,'selected' => !empty(self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $carrier->id]) ? self::$ps_configuration['WEB_SHIPPING_ASSOC'][(int) $carrier->id] : ''
				);
			}
		}
		else if ($this->api_name == 'magento')
		{
			// Non récupérable par l'API, il faut avoir saisie les codes dans le dictionnaire c_shipment_mode avant
			$TShippingMethod = \MgShippingMethod::getAllLabelByCode();
		}

		return $TShippingMethod;
	}
	
	public function debugXml($xml)
	{
		print '<pre>'. print_r($xml, true) .'</pre>';
	}
}

class DolishopTools
{
	public static function getSociete($web_id_customer, $email='')
	{
		global $db;

		if (!empty($web_id_customer)) $sql = 'SELECT fk_object AS fk_soc FROM '.MAIN_DB_PREFIX.'societe_extrafields WHERE web_id_customer = '.$web_id_customer;
		else if (!empty($email)) $sql = 'SELECT rowid AS fk_soc FROM '.MAIN_DB_PREFIX.'societe WHERE email = \''.$db->escape($email).'\'';
		else return 0;

		$resql = $db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
			if (!empty($obj->fk_soc)) return $obj->fk_soc;
			else
			{
				if ($web_id_customer > 0) return self::getSociete(0, $email); // Si la recherche par l'identifiant donne rien, alors j'essaye avec l'adresse email (il peut s'agir d'un client ayant passé des commandes en annonyme par le passé et qui a créé un compte)
				else return 0;
			}
		}
		else exit($db->lasterror());
	}
	
	public static function getContact($web_id_address)
	{
		global $db;
		
		if ($web_id_address <= 0) return 0;
		
		$sql = 'SELECT fk_object FROM '.MAIN_DB_PREFIX.'socpeople_extrafields WHERE web_id_address = '.$web_id_address;
		$resql = $db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
			if (!empty($obj->fk_object)) return $obj->fk_object;
			else return 0;
		}
		else exit($db->lasterror());
	}
	
	public static function getVatRate($id_tax=0, $id_tax_rules_group=0)
	{
		if (($id_tax == 0 && $id_tax_rules_group == 0) || empty(Webservice::$ps_configuration['PS_TAXES'])) return 0;
		
		foreach (Webservice::$ps_configuration['PS_TAXES'] as $rate => $Tab)
		{
			if ($id_tax > 0) {
				if (in_array($id_tax, $Tab['TId_tax_rules_group'])) return $rate;
			} else {
				if (in_array($id_tax_rules_group, $Tab['TId_tax'])) return $rate;
			}
		}
	}
	
	/**
	 * Retourne le rowid du produit Dolibarr correspondant à l'id ou la référence ou par son id de combinaison
	 * 
	 * @param type $ps_id_product
	 * @param type $ps_product_reference
	 * @param type $ps_id_combination
	 * @return int 0 = not found; > 0 id found; -1 if multiple id found
	 */
	public static function getDolProductId($ps_id_product, $ps_product_reference='', $ps_id_combination=0)
	{
		global $db,$conf;
		
		if (empty($ps_id_combination))
		{
			$sql = 'SELECT p.rowid FROM '.MAIN_DB_PREFIX.'product p';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields pe ON (pe.fk_object = p.rowid)';
			$sql.= ' WHERE p.entity = '.$conf->entity;
			$sql.= ' AND (pe.ps_id_product = '.$ps_id_product.' OR p.ref = \''.$db->escape($ps_product_reference).'\')';
		}
		else
		{
			$sql = 'SELECT pac.fk_product_child AS rowid';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'product_attribute_combination pac';
			$sql.= ' WHERE pac.entity = '.$conf->entity;
			$sql.= ' AND pac.ps_id_combination = '.$ps_id_combination;
		}
		
		$resql = $db->query($sql);
		if ($resql)
		{
			if ($db->num_rows($resql) > 1) return -1;
			
			$obj = $db->fetch_object($resql);
			if (!empty($obj->rowid)) return $obj->rowid;
		}
		else exit($db->lasterror());
		
		return 0;
	}
	
	public static function checkOrderExist($web_order_reference)
	{
		global $db,$conf;
		
		$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'commande WHERE entity = '.$conf->entity.' AND ref_client = \''.$db->escape($web_order_reference).'\'');
		if ($resql)
		{
			return $db->num_rows($resql);
		}
		else exit($db->lasterror());
	}
	
	/**
	 * Méthode qui vérifie si le produit web fait bien partie d'une des catégories produits à synchroniser
	 * 
	 * @param string							$api_name
	 * @param \stdClass|\SimpleXMLElement		$web_product
	 * @return boolean
	 */
	public static function checkProductCategoriesW2D($api_name, $web_product)
	{
		global $conf;
		
		$TCat = explode(',', $conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_WEBSITE);
		if (empty($TCat) || count($TCat) == 1 && $TCat[0] === '') return true;

		if ($api_name == 'prestashop')
		{
			foreach ($web_product->associations->categories->children() as $category)
			{
				if (in_array((int) $category->id, $TCat)) return true;
			}
		}
		else if ($api_name == 'magento')
		{
			foreach ($web_product->custom_attributes as $custom_attributes)
			{
				if ($custom_attributes->attribute_code == 'category_ids')
				{
					$intersect = array_intersect($TCat, $custom_attributes->value);
					if (!empty($intersect)) return true;
					break;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Méthode qui vérifie si le fk_product fait bien partie d'une des catégories produits à synchroniser
	 * 
	 * @param int		$fk_product
	 * @return boolean
	 */
	public static function checkProductCategoriesD2P($fk_product)
	{
		global $db,$conf;
		
		if (!class_exists('Categorie')) require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		
		$TCatFilter = explode(',', $conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR);
		$category = new \Categorie($db);

		$TCategoryId = $category->containing($fk_product, 'product', 'id');
//		$TCategory = $category->getListForItem($fk_product, \Categorie::TYPE_PRODUCT);
		if (is_array($TCategoryId))
		{
			foreach ($TCategoryId as $fk_category)
			{
				if (in_array($fk_category, $TCatFilter)) return true;
			}	
		}
		
		return false;
	}
	
	/**
	 * Renvoie un tableau contenant les ID produit Dolibarr à synchroniser
	 * 
	 * @global Conf $conf
	 * @return array
	 */
	public static function getTProductIdToSync($date_min=null)
	{
		global $conf,$db;
		
		$TId = array();
		
		if (empty($conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR)) return $TId;
		
		$sql = 'SELECT DISTINCT p.rowid FROM '.MAIN_DB_PREFIX.'product p';
		if (!empty($conf->variants->enabled)) $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_attribute_combination comb ON (p.rowid = comb.fk_product_child)';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie_product cp ON (cp.fk_product = p.rowid)'; // restriction par tags/categories
		$sql.= ' WHERE fk_product_type = '.\Product::TYPE_PRODUCT; // TODO à voir car magento permet de créer des produits de type "virtual" pour l'équivalence d'un service
		$sql.= ' AND cp.fk_categorie IN ('.$conf->global->DOLISHOP_SYNC_PRODUCTS_CATEGORIES_FROM_DOLIBARR.')';
		if (!empty($conf->variants->enabled)) $sql.= ' AND comb.fk_product_child IS NULL';
		if (!empty($date_min)) $sql.= ' AND p.tms >= \''.$date_min.'\'';

		$resql = $db->query($sql);
		if ($resql)
		{
			while ($arr = $db->fetch_array($resql))
			{
				$TId[] = $arr['rowid'];
			}
		}
		else
		{
			// TODO manage error
//			$this->error = $db->lasterror();
//			$thisclass/webservice.class.phpclass/webservice.class.phpclass/webservice.class.phpclass/webservice.class.php->errors[] = $db->error;
		}
		
		return $TId;
	}
	
	/**
	 * Tronque la chaine $input en conservant le maximum de mots entier pour le nombre de caractères possible
	 * 
	 * @param string	$input
	 * @param int		$length
	 * @param bool		$ellipses
	 * @param bool		$strip_html
	 * @return string
	 */
	public static function trunc($input, $length=0, $ellipses = true, $strip_html = true)
	{
		if ($strip_html) $input = strip_tags($input);
		if (strlen($input) <= $length) return $input;
		
		$last_space = strrpos(substr($input, 0, $length), ' ');
		$str = substr($input, 0, $last_space);
		
		if ($ellipses) $str.= '...';

		return $str;
	}
	
	public static function getProductDirScan(&$object)
	{
		global $conf;
		
		if (! empty($conf->product->enabled)) $upload_dir = $conf->product->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 0, $object, 'product').dol_sanitizeFileName($object->ref);
		elseif (! empty($conf->service->enabled)) $upload_dir = $conf->service->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 0, $object, 'product').dol_sanitizeFileName($object->ref);

		if (! empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO))    // For backward compatiblity, we scan also old dirs
		{
			if (! empty($conf->product->enabled)) $upload_dirold = $conf->product->multidir_output[$object->entity].'/'.substr(substr("000".$object->id, -2),1,1).'/'.substr(substr("000".$object->id, -2),0,1).'/'.$object->id."/photos";
			else $upload_dirold = $conf->service->multidir_output[$object->entity].'/'.substr(substr("000".$object->id, -2),1,1).'/'.substr(substr("000".$object->id, -2),0,1).'/'.$object->id."/photos";
		}
		
		return !empty($upload_dirold) ? $upload_dirold : $upload_dir;
	}

	public static function getAllChildProductCombinationId($fk_parent)
	{
		global $db;

		$TChildId = array();
		$sql = 'SELECT pac.fk_product_child, pac.rowid';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'product_attribute_combination pac';
		$sql.= ' WHERE pac.fk_product_parent = '.$fk_parent;

		$resql = $db->query($sql);
		if ($resql)
		{
			while ($row = $db->fetch_object($resql)) $TChildId[$row->fk_product_child] = $row->rowid;
		}
		else exit($db->lasterror());

		return $TChildId;
	}

}
