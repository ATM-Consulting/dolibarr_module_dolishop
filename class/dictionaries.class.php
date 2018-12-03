<?php

if (!class_exists('SeedObject'))
{
	define('INC_FROM_DOLIBARR', true);
	require_once __DIR__.'/../config.php';
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

class MgShippingMethod extends SeedObject
{
	/** @var array $fields */
	public $fields = array(
		'code' => array('type' => 'string', 'length' => '30')
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
}
