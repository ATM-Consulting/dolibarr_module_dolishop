<?php

if (!class_exists('SeedObject'))
{
	define('INC_FROM_DOLIBARR', true);
	require_once __DIR__.'/../config.php';
}

class mgCustomAttributeDictionary extends SeedObject
{
	public static $prefix_table = 'c_';
	public $table_element;

	public static function createExtrafield($label, $code_attribute, $prefix='')
	{
		global $db;

		$search = $prefix.$code_attribute;

		$extrafields = new ExtraFields($db);
		$extralabels=$extrafields->fetch_name_optionals_label('product');

		if (!isset($extralabels[$search]))
		{
			$index = self::$prefix_table.$code_attribute.':label:value::active=1';
			$extrafields->addExtraField($search, $label, 'sellist', 201, '', 'product', 0, 0, '', array('options' => array($index => null)), 0, '');
		}
	}

	public static function createDictionnary($table, $label, $options)
	{
		global $db,$conf,$user;

		$o = new mgCustomAttributeDictionary($db);
		$o->table_element = self::$prefix_table.$table;
		$o->init_db_by_vars();

		$Tab = self::getAdditionalDictionaries();
		if (!isset($Tab[$o->table_element]))
		{
			$Tab[$o->table_element] = array(
				'tabname' => MAIN_DB_PREFIX.$o->table_element
				,'tablib' => $label
				,'tabsql' => 'SELECT f.rowid as rowid, f.value, f.label, f.entity, f.active FROM '.MAIN_DB_PREFIX.$o->table_element.' as f'
				,'tabsqlsort' => 'value ASC'
				,'tabfield' => 'value,label'
				,'tabfieldvalue' => 'value,label'
				,'tabfieldinsert' => 'value,label,entity'
				,'tabrowid' => 'rowid'
				,'tabcond' => '$conf->dolishop->enabled && $conf->global->DOLISHOP_API_NAME == \'magento\''
			);
			dolibarr_set_const($db, 'DOLISHOP_MG_CUSTOM_ATTRIBUTES_DICTIONNARIES', json_encode($Tab), 'chaine', 0, '', $conf->entity);
		}

		foreach ($options as $option)
		{
			if (empty($option->value)) continue;
			$o->id = '';
			$o->fetchBy($option->value, 'value');
			if (empty($o->id))
			{
				$o->value = $option->value;
				$o->active = 1;
				$o->entity = $conf->entity;
			}
			$o->label = $option->label;

			$o->create($user);
		}
	}

	public static function getAdditionalDictionaries()
	{
		global $conf;

		if (empty($conf->global->DOLISHOP_MG_CUSTOM_ATTRIBUTES_DICTIONNARIES)) return array();

		return json_decode($conf->global->DOLISHOP_MG_CUSTOM_ATTRIBUTES_DICTIONNARIES, true);
	}

	public function __construct(DoliDB $db)
	{
		parent::__construct($db);

		$this->fields=array(
			'value'=>array('type'=>'string','index'=>true)
			,'label'=>array('type'=>'string')
			,'active'=>array('type'=>'integer','index'=>true) // date, integer, string, float, array, text
			,'entity'=>array('type'=>'integer','index'=>true)
		);

		$this->init();
	}

}

/**
 * Class DolCountry Mirror of llx_c_country dictionary
 */
class DolCountry extends SeedObject
{
	/** @var array $fields */
	public $fields = array(
		'code' => array('type' => 'string', 'length' => '2')
		,'code_iso' => array('type' => 'string', 'length' => '3')
		,'label' => array('type' => 'string', 'length' => '50')
		,'active' => array('type' => 'integer')
		,'favorite' => array('type' => 'integer')
	);

	/** @var string  */
	public $table_element = 'c_country';
	/** @var string  */
	public $element = 'country';

	public static $cache = array();

	/**
	 * DolCountry constructor.
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->init();
	}

	public static function loadIdByCode()
	{
		global $db;

		$sql = 'SELECT rowid, code FROM '.MAIN_DB_PREFIX.'c_country WHERE active = 1';
		$resql = $db->query($sql);
		if ($resql)
		{
			while ($row = $db->fetch_object($resql))
			{
				self::$cache[$row->code] = $row->rowid;
			}
		}
	}

	public static function getIdFromCode($code)
	{
		if (empty(self::$cache))
		{
			self::loadIdByCode();
		}

		if (isset(self::$cache[$code])) return self::$cache[$code];
		else return 0;
	}
}

/**
 * Class MgSalesOrderStatuses
 */
class MgSalesOrderStatuses extends SeedObject
{
	/** @var array $fields */
	public $fields = array(
		'code' => array('type' => 'string', 'length' => '80')
		,'label' => array('type' => 'string', 'length' => '150')
		,'entity' => array('type' => 'integer')
		,'active' => array('type' => 'integer')
	);

	/** @var string  */
	public $table_element = 'c_mg_sales_order_statuses';
	/** @var string  */
	public $element = 'mg_sales_order_statuses';

	/**
	 * MgSalesOrderStatuses constructor.
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		$this->init();

		$this->entity = $conf->entity;
	}

	public static function getAllLabelByCode()
	{
		global $db,$conf;

		$TRes = array();

		$sql = 'SELECT code, label';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'c_mg_sales_order_statuses';
		$sql.= ' WHERE active = 1';
		$sql.= ' AND entity = '.$conf->entity;

		$resql = $db->query($sql);
		if ($resql)
		{
			while ($row = $db->fetch_object($resql))
			{
				$TRes[$row->code] = $row->label;
			}
		}
		else
		{
			dol_print_error($db);
		}

		return $TRes;
	}
}

/**
 * Class MgShippingMethod Mirror of llx_c_shipment_mode
 */
class MgShippingMethod extends SeedObject
{
	/** @var array $fields */
	public $fields = array(
		'rowid' => array('type' => 'integer')
		,'code' => array('type' => 'string', 'length' => '30')
		,'libelle' => array('type' => 'string', 'length' => '50')
		,'description' => array('type' => 'text')
		,'tracking' => array('type' => 'string', 'length' => '256')
		,'active' => array('type' => 'integer')
		,'module' => array('type' => 'string', 'length' => '32')
	);

	/** @var string  */
	public $table_element = 'c_shipment_mode';
	/** @var string  */
	public $element = 'shipment_mode';

	/**
	 * MgShippingMethod constructor.
	 * @param $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->init();
	}

	protected function init()
	{
		parent::init();

		unset($this->fields['date_creation']);
		unset($this->fields['tms']);
//		unset($this->fields['rowid']);
	}

	public static function getAllLabelByCode()
	{
		global $db;

		$TRes = array();

		$sql = 'SELECT code, libelle';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'c_shipment_mode';
		$sql.= ' WHERE active = 1';

		$resql = $db->query($sql);
		if ($resql)
		{
			while ($row = $db->fetch_object($resql))
			{
				$TRes[$row->code] = $row->label;
			}
		}
		else
		{
			dol_print_error($db);
		}

		return $TRes;
	}

	/**
	 * https://github.com/magento-engcom/msi/wiki/Step-8.-Configure-shipping-and-payment-methods-%5BWeb-API-Tutorial%5D#configure-supported-shipping-methods-optional
	 */
	static public function createDefaultValues()
	{
		global $db,$user;

		$o = new MgShippingMethod($db);
		$o->fetchBy('flatrate', 'code');
		if (empty($o->id))
		{
			$o->code = 'flatrate';
			$o->libelle = 'Flat rate';
			$o->active = 1;
			$o->create($user);
		}

		$o = new MgShippingMethod($db);
		$o->fetchBy('tablerate', 'code');
		if (empty($o->id))
		{
			$o->code = 'tablerate';
			$o->libelle = 'Table rate';
			$o->active = 1;
			$o->create($user);
		}

		$o = new MgShippingMethod($db);
		$o->fetchBy('freeshipping', 'code');
		if (empty($o->id))
		{
			$o->code = 'freeshipping';
			$o->libelle = 'Free shipping';
			$o->active = 0;
			$o->create($user);
		}
	}
}

/**
 * Class MgPaymentMethod Mirror of llx_c_paiement
 */
class MgPaymentMethod extends SeedObject
{
	/** @var array $fields */
	public $fields = array(
		'entity' => array('type' => 'integer')
		,'code' => array('type' => 'string', 'length' => '6') // default is 6 char u_U
		,'libelle' => array('type' => 'string', 'length' => '62')
		,'type' => array('type' => 'integer')
		,'active' => array('type' => 'integer')
	);

	/** @var string  */
	public $table_element = 'c_paiement';
	/** @var string  */
	public $element = 'paiement';

	/**
	 * MgShippingMethod constructor.
	 * @param $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->init();
	}

	protected function init()
	{
		parent::init();

		unset($this->fields['date_creation']);
		unset($this->fields['tms']);
	}

	/**
	 * https://github.com/magento-engcom/msi/wiki/Step-8.-Configure-shipping-and-payment-methods-%5BWeb-API-Tutorial%5D#set-the-payment-method
	 */
	static public function createDefaultValues()
	{
		global $db,$user,$conf;

		// Extend varchar limit, default is 6 and it's to short
		$sql = 'ALTER TABLE '.MAIN_DB_PREFIX.'c_paiement MODIFY code VARCHAR(30)';
		$db->query($sql);

		$o = new MgPaymentMethod($db);
		$o->entity = $conf->entity;
		$o->code = 'checkmo';
		$o->libelle = 'Check/Money Order';
		$o->type = 2;
		$o->active = 1;
		$o->create($user);

		$o = new MgPaymentMethod($db);
		$o->entity = $conf->entity;
		$o->code = 'banktransfer';
		$o->libelle = 'Bank Transfer Paymen';
		$o->type = 2;
		$o->active = 0;
		$o->create($user);

		$o = new MgPaymentMethod($db);
		$o->entity = $conf->entity;
		$o->code = 'cashondelivery';
		$o->libelle = 'Cash on Delivery';
		$o->type = 2;
		$o->active = 0;
		$o->create($user);

		$o = new MgPaymentMethod($db);
		$o->entity = $conf->entity;
		$o->code = 'purchaseorder';
		$o->libelle = 'Purchase Order';
		$o->type = 2;
		$o->active = 0;
		$o->create($user);

		$o = new MgPaymentMethod($db);
		$o->entity = $conf->entity;
		$o->code = 'free';
		$o->libelle = 'Zero Subtotal Checkout';
		$o->type = 2;
		$o->active = 0;
		$o->create($user);

	}

	/**
	 * Load object in memory from the database
	 *
	 * @param	int    $id				Id object
	 * @param	string $ref				Ref
	 * @param	string	$morewhere		More SQL filters (' AND ...')
	 * @return 	int         			<0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchCommon($id, $ref = null, $morewhere = '')
	{
		if (empty($id) && empty($ref) && empty($morewhere)) return -1;

		$sql = 'SELECT '.$this->getFieldList();
		$sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element;

		if (!empty($id))  $sql.= ' WHERE id = '.$id;
		elseif (!empty($ref)) $sql.= " WHERE ref = ".$this->quote($ref, $this->fields['ref']);
		else $sql.=' WHERE 1 = 1';	// usage with empty id and empty ref is very rare
		if ($morewhere)   $sql.= $morewhere;
		$sql.=' LIMIT 1';	// This is a fetch, to be sure to get only one record

		$res = $this->db->query($sql);
		if ($res)
		{
			$obj = $this->db->fetch_object($res);
			if ($obj)
			{
				$this->setVarsFromFetchObj($obj);
				return $this->id;
			}
			else
			{
				return 0;
			}
		}
		else
		{
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      	User that modifies
	 * @param  bool $notrigger 	false=launch triggers after, true=disable triggers
	 * @return int             	<0 if KO, >0 if OK
	 */
	public function updateCommon(User $user, $notrigger = false)
	{
		global $conf, $langs;

		$error = 0;

		$now=dol_now();

		$fieldvalues = $this->setSaveQuery();
		if (array_key_exists('date_modification', $fieldvalues) && empty($fieldvalues['date_modification'])) $fieldvalues['date_modification']=$this->db->idate($now);
		if (array_key_exists('fk_user_modif', $fieldvalues) && ! ($fieldvalues['fk_user_modif'] > 0)) $fieldvalues['fk_user_modif']=$user->id;
		unset($fieldvalues['rowid']);	// The field 'rowid' is reserved field name for autoincrement field so we don't need it into update.

		$keys=array();
		$values = array();
		foreach ($fieldvalues as $k => $v) {
			$keys[$k] = $k;
			$value = $this->fields[$k];
			$values[$k] = $this->quote($v, $value);
			$tmp[] = $k.'='.$this->quote($v, $this->fields[$k]);
		}

		// Clean and check mandatory
		foreach($keys as $key)
		{
			if (preg_match('/^integer:/i', $this->fields[$key]['type']) && $values[$key] == '-1') $values[$key]='';		// This is an implicit foreign key field
			if (! empty($this->fields[$key]['foreignkey']) && $values[$key] == '-1') $values[$key]='';					// This is an explicit foreign key field

			//var_dump($key.'-'.$values[$key].'-'.($this->fields[$key]['notnull'] == 1));
			/*
			if ($this->fields[$key]['notnull'] == 1 && empty($values[$key]))
			{
				$error++;
				$this->errors[]=$langs->trans("ErrorFieldRequired", $this->fields[$key]['label']);
			}*/
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET '.implode( ',', $tmp ).' WHERE id='.$this->id ;

		$this->db->begin();
		if (! $error)
		{
			$res = $this->db->query($sql);
			if ($res===false)
			{
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		// Update extrafield
		if (! $error && empty($conf->global->MAIN_EXTRAFIELDS_DISABLED) && is_array($this->array_options) && count($this->array_options)>0)
		{
			$result=$this->insertExtraFields();
			if ($result < 0)
			{
				$error++;
			}
		}

		// Triggers
		if (! $error && ! $notrigger)
		{
			// Call triggers
			$result=$this->call_trigger(strtoupper(get_class($this)).'_MODIFY',$user);
			if ($result < 0) { $error++; } //Do also here what you must do to rollback action if trigger fail
			// End call triggers
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();
			return -1;
		} else {
			$this->db->commit();
			return $this->id;
		}
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user       User that deletes
	 * @param bool $notrigger  false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, >0 if OK
	 */
	public function deleteCommon(User $user, $notrigger = false)
	{
		$error=0;

		$this->db->begin();

		if (! $error) {
			if (! $notrigger) {
				// Call triggers
				$result=$this->call_trigger(strtoupper(get_class($this)).'_DELETE', $user);
				if ($result < 0) { $error++; } // Do also here what you must do to rollback action if trigger fail
				// End call triggers
			}
		}

		if (! $error && ! empty($this->isextrafieldmanaged))
		{
			$sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element."_extrafields";
			$sql.= " WHERE fk_object=" . $this->id;

			$resql = $this->db->query($sql);
			if (! $resql)
			{
				$this->errors[] = $this->db->lasterror();
				$error++;
			}
		}

		if (! $error)
		{
			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE id='.$this->id;

			$res = $this->db->query($sql);
			if($res===false) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}
}
