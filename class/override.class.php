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

if (is_file(DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php')) require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
else dol_include_once('/dolishop/class/backward/ecmfiles.class.php');

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
//			,'filepath'=>array('type'=>'string','length'=>255)
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
		if ((float) DOL_VERSION < 6.0) $sql.= ' AND fullpath LIKE \'%'.$this->db->escape($ref_object).'\'';
		else $sql.= ' AND filepath LIKE \'%'.$this->db->escape($ref_object).'\'';
		
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

if ((float) DOL_VERSION >= 6.0)
{
	require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductAttribute.class.php';

	class ProductAttributeDolishop extends \ProductAttribute
	{
		public $db;

		/**
		 * api = product_options
		 * @var integer
		 */
		public $ps_id_option_group;

		private static $TProdAttr;

		/**
		 * @override
		 */
		function __construct(\DoliDB $db)
		{
			parent::__construct($db);
			$this->db = $db;
		}

		public function updatePsValue()
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_attribute SET ps_id_option_group = '.$this->ps_id_option_group.' WHERE rowid = '.$this->id;
			$resql = $this->db->query($sql);

			if ($resql) return $this->id;
			else
			{
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				return -1;
			}
		}

		/**
		 * @override
		 * Creates a product attribute
		 *
		 * @param	User	$user	Object user that create
		 * @return 					int <0 KO, Id of new variant if OK
		 */
		public function create(\User $user)
		{
			$this->db->begin();

			$res = parent::create($user);
			if ($res > 0)
			{
				$res = $this->updatePsValue();
			}

			if ($res > 0) $this->db->commit();
			else $this->db->rollback();

			return $res;
		}

		public function fetchByPsId($ps_id_option_group)
		{
			if (!$ps_id_option_group) {
				return -1;
			}

			$sql = "SELECT rowid, ref, label, rang, ps_id_option_group FROM ".MAIN_DB_PREFIX."product_attribute WHERE ps_id_option_group = ".(int) $ps_id_option_group." AND entity IN (".getEntity('product').")";

			$query = $this->db->query($sql);

			if (!$this->db->num_rows($query)) {
				return -1;
			}

			$result = $this->db->fetch_object($query);

			$this->id = $result->rowid;
			$this->ref = $result->ref;
			$this->label = $result->label;
			$this->rang = $result->rang;
			$this->ps_id_option_group = $result->ps_id_option_group;

			return 1;
		}

		public static function getAll($byKey='', $force_reload=false, $filter_ps_id_option_group=true)
		{
			global $db;

			if (empty(self::$TProdAttr) || $force_reload)
			{
				$sql = "SELECT rowid, ref, label, rang, ps_id_option_group FROM ".MAIN_DB_PREFIX."product_attribute";
				$sql.= ' WHERE entity IN ('.getEntity('product').')';
				if ($filter_ps_id_option_group) $sql.= ' AND ps_id_option_group > 0';

				$resql = $db->query($sql);

				if ($resql)
				{
					self::$TProdAttr = array();
					while ($result = $db->fetch_object($resql))
					{
						$tmp = new ProductAttributeDolishop($db);
						$tmp->id = $result->rowid;
						$tmp->ref = $result->ref;
						$tmp->label = $result->label;
						$tmp->rang = $result->rang;
						$tmp->ps_id_option_group = $result->ps_id_option_group;

						if (!empty($byKey)) self::$TProdAttr[$tmp->{$byKey}] = $tmp;
						else self::$TProdAttr[] = $tmp;
					}
				}
				else
				{
					dol_print_error($db);
				}
			}

			return self::$TProdAttr;
		}

		public static function getAllByPsId($force_reload=false)
		{
			return self::getAll('ps_id_option_group', $force_reload);
		}
	}

	require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductAttributeValue.class.php';

	class ProductAttributeValueDolishop extends \ProductAttributeValue
	{
		public $db;

		/**
		 * api = product_option_values
		 * @var integer
		 */
		public $ps_id_option_value;
		/**
		 * foreign key of llx_product_attribute(ps_id_option_group)
		 * @var integer
		 */
		public $ps_id_option_group;

		private static $TProdAttrVal;

		/**
		 * @override
		 */
		function __construct(\DoliDB $db)
		{
			parent::__construct($db);
			$this->db = $db;
		}

		public function updatePsValue()
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_attribute_value SET ps_id_option_value = '.$this->ps_id_option_value.', ps_id_option_group = '.$this->ps_id_option_group.' WHERE rowid = '.$this->id;
			$resql = $this->db->query($sql);

			if ($resql) return $this->id;
			else
			{
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				return -1;
			}
		}

		/**
		 * @override
		 * Creates a value for a product attribute
		 *
		 * @return int <0 KO >0 OK
		 */
		public function create()
		{
			if (!$this->fk_product_attribute) {
				return -1;
			}

			//Ref must be uppercase
			$this->ref = strtoupper($this->ref);

			$sql = "INSERT INTO ".MAIN_DB_PREFIX."product_attribute_value (fk_product_attribute, ref, value, entity, ps_id_option_value, ps_id_option_group)
		VALUES ('".(int) $this->fk_product_attribute."', '".$this->db->escape($this->ref)."',
		'".$this->db->escape($this->value)."', ".(int) $this->entity.", ".(int) $this->ps_id_option_value.", ".(int) $this->ps_id_option_group.")";

			$query = $this->db->query($sql);

			if ($query) {
				$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'product_attribute_value');
				return $this->id;
			}

			return -1;
		}

		public function fetchByPsId($ps_id_option_value)
		{
			$sql = "SELECT rowid, fk_product_attribute, ref, value, ps_id_option_value, ps_id_option_group FROM ".MAIN_DB_PREFIX."product_attribute_value WHERE ps_id_option_value = ".(int) $ps_id_option_value." AND entity IN (".getEntity('product').")";

			$query = $this->db->query($sql);

			if (!$query) {
				return -1;
			}

			if (!$this->db->num_rows($query)) {
				return -1;
			}

			$result = $this->db->fetch_object($query);

			$this->id = $result->rowid;
			$this->fk_product_attribute = $result->fk_product_attribute;
			$this->ref = $result->ref;
			$this->value = $result->value;
			$this->ps_id_option_value = $result->ps_id_option_value;
			$this->ps_id_option_group = $result->ps_id_option_group;

			return 1;
		}

		public static function getAll($byKey='', $force_reload=false, $filter_ps_id_option_value=true)
		{
			global $db;

			if (empty(self::$TProdAttrVal) || $force_reload)
			{
				$sql = "SELECT rowid, fk_product_attribute, ref, value, ps_id_option_value, ps_id_option_group FROM ".MAIN_DB_PREFIX."product_attribute_value";
				$sql.= ' WHERE entity IN ('.getEntity('product').')';
				if ($filter_ps_id_option_value) $sql.= ' AND ps_id_option_value > 0';

				$resql = $db->query($sql);
				if ($resql)
				{
					self::$TProdAttrVal = array();
					while ($result = $db->fetch_object($resql))
					{
						$tmp = new ProductAttributeValueDolishop($db);
						$tmp->fk_product_attribute = $result->fk_product_attribute;
						$tmp->id = $result->rowid;
						$tmp->ref = $result->ref;
						$tmp->value = $result->value;
						$tmp->ps_id_option_value = $result->ps_id_option_value;
						$tmp->ps_id_option_group = $result->ps_id_option_group;

						if (!empty($byKey)) self::$TProdAttrVal[$tmp->{$byKey}] = $tmp;
						else self::$TProdAttrVal[] = $tmp;
					}
				}
				else
				{
					dol_print_error($db);
				}
			}


			return self::$TProdAttrVal;
		}

		public static function getAllByPsId($force_reload=false)
		{
			return self::getAll('ps_id_option_value', $force_reload);
		}
	}


	require_once DOL_DOCUMENT_ROOT.'/variants/class/ProductCombination.class.php';

	class ProductCombinationDolishop extends \ProductCombination
	{
		public $db;

		/**
		 * api = combinations
		 * @var integer
		 */
		public $ps_id_combination;

		/**
		 * @override
		 */
		function __construct(\DoliDB $db)
		{
			parent::__construct($db);
			$this->db = $db;
		}

		public function updatePsValue()
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_attribute_combination SET ps_id_combination = '.$this->ps_id_combination.' WHERE rowid = '.$this->id;
			$resql = $this->db->query($sql);

			if ($resql) return $this->id;
			else
			{
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
				return -1;
			}
		}

		/**
		 * @override
		 */
		public function createProductCombination(\Product $product, array $combinations, array $variations, $price_var_percent = false, $forced_pricevar = false, $forced_weightvar = false)
		{
			$this->db->begin();

			$res = parent::createProductCombination($product, $combinations, $variations, $price_var_percent, $forced_pricevar, $forced_weightvar);
			if ($res > 0)
			{
				$sql = 'SELECT MAX(rowid) AS rowid FROM '.MAIN_DB_PREFIX.'product_attribute_combination LIMIT 1';
				$resql = $this->db->query($sql);
				if ($resql)
				{
					$o = $this->db->fetch_object($resql);
					if (!empty($o))
					{
						$this->id = $o->rowid;
						$res = $this->updatePsValue();
					}
				}
			}

			if ($res > 0) $this->db->commit();
			else $this->db->rollback();

			return $res;
		}

		/**
		 * @override
		 */
		public function fetchByFkProductChild($fk_child)
		{
			$res = parent::fetchByFkProductChild($fk_child);
			if ($res)
			{
				$sql = "SELECT ps_id_combination FROM ".MAIN_DB_PREFIX."product_attribute_combination WHERE rowid = ".$this->id;
				$resql = $this->db->query($sql);
				if ($resql)
				{
					$o = $this->db->fetch_object($resql);
					$this->ps_id_combination = $o->ps_id_combination;
				}
				else return -1;
			}

			return 1;
		}

		/**
		 * @override
		 * Retrieves all product combinations by the product parent row id
		 *
		 * @param int $fk_product_parent Rowid of parent product
		 * @return int|ProductCombination[] <0 KO
		 */
		public function fetchAllByFkProductParent($fk_product_parent)
		{
			$sql = 'SELECT pac.rowid, pac.fk_product_parent, pac.fk_product_child, pac.variation_price, pac.variation_price_percentage, pac.variation_weight, pac.ps_id_combination';
			$sql.= ', p.ref';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'product_attribute_combination pac';
			$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'product p ON (p.rowid = pac.fk_product_child)';
			$sql.= ' WHERE pac.fk_product_parent = '.(int) $fk_product_parent.' AND pac.entity IN ('.getEntity('product').')';

			$query = $this->db->query($sql);

			if (!$query) {
				return -1;
			}

			$return = array();

			while ($result = $this->db->fetch_object($query)) {

				$tmp = new ProductCombinationDolishop($this->db);
				$tmp->id = $result->rowid;
				$tmp->ref = $result->ref;
				$tmp->fk_product_parent = $result->fk_product_parent;
				$tmp->fk_product_child = $result->fk_product_child;
				$tmp->variation_price = $result->variation_price;
				$tmp->variation_price_percentage = $result->variation_price_percentage;
				$tmp->variation_weight = $result->variation_weight;
				$tmp->ps_id_combination = $result->ps_id_combination;

				$return[] = $tmp;
			}

			return $return;
		}
	}
}
