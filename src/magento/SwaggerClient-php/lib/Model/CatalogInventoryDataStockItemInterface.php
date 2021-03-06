<?php
/**
 * CatalogInventoryDataStockItemInterface
 *
 * PHP version 5
 *
 * @category Class
 * @package  Swagger\Client
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */

/**
 * Magento Enterprise Edition 2.0
 *
 * No description provided (generated by Swagger Codegen https://github.com/swagger-api/swagger-codegen)
 *
 * OpenAPI spec version: 2.0
 * 
 * Generated by: https://github.com/swagger-api/swagger-codegen.git
 * Swagger Codegen version: 2.3.1
 */

/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace Swagger\Client\Model;

use \ArrayAccess;
use \Swagger\Client\ObjectSerializer;

/**
 * CatalogInventoryDataStockItemInterface Class Doc Comment
 *
 * @category Class
 * @description Interface StockItem
 * @package  Swagger\Client
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class CatalogInventoryDataStockItemInterface implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'catalog-inventory-data-stock-item-interface';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'item_id' => 'int',
        'product_id' => 'int',
        'stock_id' => 'int',
        'qty' => 'float',
        'is_in_stock' => 'bool',
        'is_qty_decimal' => 'bool',
        'show_default_notification_message' => 'bool',
        'use_config_min_qty' => 'bool',
        'min_qty' => 'float',
        'use_config_min_sale_qty' => 'int',
        'min_sale_qty' => 'float',
        'use_config_max_sale_qty' => 'bool',
        'max_sale_qty' => 'float',
        'use_config_backorders' => 'bool',
        'backorders' => 'int',
        'use_config_notify_stock_qty' => 'bool',
        'notify_stock_qty' => 'float',
        'use_config_qty_increments' => 'bool',
        'qty_increments' => 'float',
        'use_config_enable_qty_inc' => 'bool',
        'enable_qty_increments' => 'bool',
        'use_config_manage_stock' => 'bool',
        'manage_stock' => 'bool',
        'low_stock_date' => 'string',
        'is_decimal_divided' => 'bool',
        'stock_status_changed_auto' => 'int',
        'extension_attributes' => '\Swagger\Client\Model\CatalogInventoryDataStockItemExtensionInterface'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'item_id' => null,
        'product_id' => null,
        'stock_id' => null,
        'qty' => null,
        'is_in_stock' => null,
        'is_qty_decimal' => null,
        'show_default_notification_message' => null,
        'use_config_min_qty' => null,
        'min_qty' => null,
        'use_config_min_sale_qty' => null,
        'min_sale_qty' => null,
        'use_config_max_sale_qty' => null,
        'max_sale_qty' => null,
        'use_config_backorders' => null,
        'backorders' => null,
        'use_config_notify_stock_qty' => null,
        'notify_stock_qty' => null,
        'use_config_qty_increments' => null,
        'qty_increments' => null,
        'use_config_enable_qty_inc' => null,
        'enable_qty_increments' => null,
        'use_config_manage_stock' => null,
        'manage_stock' => null,
        'low_stock_date' => null,
        'is_decimal_divided' => null,
        'stock_status_changed_auto' => null,
        'extension_attributes' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'item_id' => 'itemId',
        'product_id' => 'productId',
        'stock_id' => 'stockId',
        'qty' => 'qty',
        'is_in_stock' => 'isInStock',
        'is_qty_decimal' => 'isQtyDecimal',
        'show_default_notification_message' => 'showDefaultNotificationMessage',
        'use_config_min_qty' => 'useConfigMinQty',
        'min_qty' => 'minQty',
        'use_config_min_sale_qty' => 'useConfigMinSaleQty',
        'min_sale_qty' => 'minSaleQty',
        'use_config_max_sale_qty' => 'useConfigMaxSaleQty',
        'max_sale_qty' => 'maxSaleQty',
        'use_config_backorders' => 'useConfigBackorders',
        'backorders' => 'backorders',
        'use_config_notify_stock_qty' => 'useConfigNotifyStockQty',
        'notify_stock_qty' => 'notifyStockQty',
        'use_config_qty_increments' => 'useConfigQtyIncrements',
        'qty_increments' => 'qtyIncrements',
        'use_config_enable_qty_inc' => 'useConfigEnableQtyInc',
        'enable_qty_increments' => 'enableQtyIncrements',
        'use_config_manage_stock' => 'useConfigManageStock',
        'manage_stock' => 'manageStock',
        'low_stock_date' => 'lowStockDate',
        'is_decimal_divided' => 'isDecimalDivided',
        'stock_status_changed_auto' => 'stockStatusChangedAuto',
        'extension_attributes' => 'extensionAttributes'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'item_id' => 'setItemId',
        'product_id' => 'setProductId',
        'stock_id' => 'setStockId',
        'qty' => 'setQty',
        'is_in_stock' => 'setIsInStock',
        'is_qty_decimal' => 'setIsQtyDecimal',
        'show_default_notification_message' => 'setShowDefaultNotificationMessage',
        'use_config_min_qty' => 'setUseConfigMinQty',
        'min_qty' => 'setMinQty',
        'use_config_min_sale_qty' => 'setUseConfigMinSaleQty',
        'min_sale_qty' => 'setMinSaleQty',
        'use_config_max_sale_qty' => 'setUseConfigMaxSaleQty',
        'max_sale_qty' => 'setMaxSaleQty',
        'use_config_backorders' => 'setUseConfigBackorders',
        'backorders' => 'setBackorders',
        'use_config_notify_stock_qty' => 'setUseConfigNotifyStockQty',
        'notify_stock_qty' => 'setNotifyStockQty',
        'use_config_qty_increments' => 'setUseConfigQtyIncrements',
        'qty_increments' => 'setQtyIncrements',
        'use_config_enable_qty_inc' => 'setUseConfigEnableQtyInc',
        'enable_qty_increments' => 'setEnableQtyIncrements',
        'use_config_manage_stock' => 'setUseConfigManageStock',
        'manage_stock' => 'setManageStock',
        'low_stock_date' => 'setLowStockDate',
        'is_decimal_divided' => 'setIsDecimalDivided',
        'stock_status_changed_auto' => 'setStockStatusChangedAuto',
        'extension_attributes' => 'setExtensionAttributes'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'item_id' => 'getItemId',
        'product_id' => 'getProductId',
        'stock_id' => 'getStockId',
        'qty' => 'getQty',
        'is_in_stock' => 'getIsInStock',
        'is_qty_decimal' => 'getIsQtyDecimal',
        'show_default_notification_message' => 'getShowDefaultNotificationMessage',
        'use_config_min_qty' => 'getUseConfigMinQty',
        'min_qty' => 'getMinQty',
        'use_config_min_sale_qty' => 'getUseConfigMinSaleQty',
        'min_sale_qty' => 'getMinSaleQty',
        'use_config_max_sale_qty' => 'getUseConfigMaxSaleQty',
        'max_sale_qty' => 'getMaxSaleQty',
        'use_config_backorders' => 'getUseConfigBackorders',
        'backorders' => 'getBackorders',
        'use_config_notify_stock_qty' => 'getUseConfigNotifyStockQty',
        'notify_stock_qty' => 'getNotifyStockQty',
        'use_config_qty_increments' => 'getUseConfigQtyIncrements',
        'qty_increments' => 'getQtyIncrements',
        'use_config_enable_qty_inc' => 'getUseConfigEnableQtyInc',
        'enable_qty_increments' => 'getEnableQtyIncrements',
        'use_config_manage_stock' => 'getUseConfigManageStock',
        'manage_stock' => 'getManageStock',
        'low_stock_date' => 'getLowStockDate',
        'is_decimal_divided' => 'getIsDecimalDivided',
        'stock_status_changed_auto' => 'getStockStatusChangedAuto',
        'extension_attributes' => 'getExtensionAttributes'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$swaggerModelName;
    }

    

    

    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['item_id'] = isset($data['item_id']) ? $data['item_id'] : null;
        $this->container['product_id'] = isset($data['product_id']) ? $data['product_id'] : null;
        $this->container['stock_id'] = isset($data['stock_id']) ? $data['stock_id'] : null;
        $this->container['qty'] = isset($data['qty']) ? $data['qty'] : null;
        $this->container['is_in_stock'] = isset($data['is_in_stock']) ? $data['is_in_stock'] : null;
        $this->container['is_qty_decimal'] = isset($data['is_qty_decimal']) ? $data['is_qty_decimal'] : null;
        $this->container['show_default_notification_message'] = isset($data['show_default_notification_message']) ? $data['show_default_notification_message'] : null;
        $this->container['use_config_min_qty'] = isset($data['use_config_min_qty']) ? $data['use_config_min_qty'] : null;
        $this->container['min_qty'] = isset($data['min_qty']) ? $data['min_qty'] : null;
        $this->container['use_config_min_sale_qty'] = isset($data['use_config_min_sale_qty']) ? $data['use_config_min_sale_qty'] : null;
        $this->container['min_sale_qty'] = isset($data['min_sale_qty']) ? $data['min_sale_qty'] : null;
        $this->container['use_config_max_sale_qty'] = isset($data['use_config_max_sale_qty']) ? $data['use_config_max_sale_qty'] : null;
        $this->container['max_sale_qty'] = isset($data['max_sale_qty']) ? $data['max_sale_qty'] : null;
        $this->container['use_config_backorders'] = isset($data['use_config_backorders']) ? $data['use_config_backorders'] : null;
        $this->container['backorders'] = isset($data['backorders']) ? $data['backorders'] : null;
        $this->container['use_config_notify_stock_qty'] = isset($data['use_config_notify_stock_qty']) ? $data['use_config_notify_stock_qty'] : null;
        $this->container['notify_stock_qty'] = isset($data['notify_stock_qty']) ? $data['notify_stock_qty'] : null;
        $this->container['use_config_qty_increments'] = isset($data['use_config_qty_increments']) ? $data['use_config_qty_increments'] : null;
        $this->container['qty_increments'] = isset($data['qty_increments']) ? $data['qty_increments'] : null;
        $this->container['use_config_enable_qty_inc'] = isset($data['use_config_enable_qty_inc']) ? $data['use_config_enable_qty_inc'] : null;
        $this->container['enable_qty_increments'] = isset($data['enable_qty_increments']) ? $data['enable_qty_increments'] : null;
        $this->container['use_config_manage_stock'] = isset($data['use_config_manage_stock']) ? $data['use_config_manage_stock'] : null;
        $this->container['manage_stock'] = isset($data['manage_stock']) ? $data['manage_stock'] : null;
        $this->container['low_stock_date'] = isset($data['low_stock_date']) ? $data['low_stock_date'] : null;
        $this->container['is_decimal_divided'] = isset($data['is_decimal_divided']) ? $data['is_decimal_divided'] : null;
        $this->container['stock_status_changed_auto'] = isset($data['stock_status_changed_auto']) ? $data['stock_status_changed_auto'] : null;
        $this->container['extension_attributes'] = isset($data['extension_attributes']) ? $data['extension_attributes'] : null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if ($this->container['qty'] === null) {
            $invalidProperties[] = "'qty' can't be null";
        }
        if ($this->container['is_in_stock'] === null) {
            $invalidProperties[] = "'is_in_stock' can't be null";
        }
        if ($this->container['is_qty_decimal'] === null) {
            $invalidProperties[] = "'is_qty_decimal' can't be null";
        }
        if ($this->container['show_default_notification_message'] === null) {
            $invalidProperties[] = "'show_default_notification_message' can't be null";
        }
        if ($this->container['use_config_min_qty'] === null) {
            $invalidProperties[] = "'use_config_min_qty' can't be null";
        }
        if ($this->container['min_qty'] === null) {
            $invalidProperties[] = "'min_qty' can't be null";
        }
        if ($this->container['use_config_min_sale_qty'] === null) {
            $invalidProperties[] = "'use_config_min_sale_qty' can't be null";
        }
        if ($this->container['min_sale_qty'] === null) {
            $invalidProperties[] = "'min_sale_qty' can't be null";
        }
        if ($this->container['use_config_max_sale_qty'] === null) {
            $invalidProperties[] = "'use_config_max_sale_qty' can't be null";
        }
        if ($this->container['max_sale_qty'] === null) {
            $invalidProperties[] = "'max_sale_qty' can't be null";
        }
        if ($this->container['use_config_backorders'] === null) {
            $invalidProperties[] = "'use_config_backorders' can't be null";
        }
        if ($this->container['backorders'] === null) {
            $invalidProperties[] = "'backorders' can't be null";
        }
        if ($this->container['use_config_notify_stock_qty'] === null) {
            $invalidProperties[] = "'use_config_notify_stock_qty' can't be null";
        }
        if ($this->container['notify_stock_qty'] === null) {
            $invalidProperties[] = "'notify_stock_qty' can't be null";
        }
        if ($this->container['use_config_qty_increments'] === null) {
            $invalidProperties[] = "'use_config_qty_increments' can't be null";
        }
        if ($this->container['qty_increments'] === null) {
            $invalidProperties[] = "'qty_increments' can't be null";
        }
        if ($this->container['use_config_enable_qty_inc'] === null) {
            $invalidProperties[] = "'use_config_enable_qty_inc' can't be null";
        }
        if ($this->container['enable_qty_increments'] === null) {
            $invalidProperties[] = "'enable_qty_increments' can't be null";
        }
        if ($this->container['use_config_manage_stock'] === null) {
            $invalidProperties[] = "'use_config_manage_stock' can't be null";
        }
        if ($this->container['manage_stock'] === null) {
            $invalidProperties[] = "'manage_stock' can't be null";
        }
        if ($this->container['low_stock_date'] === null) {
            $invalidProperties[] = "'low_stock_date' can't be null";
        }
        if ($this->container['is_decimal_divided'] === null) {
            $invalidProperties[] = "'is_decimal_divided' can't be null";
        }
        if ($this->container['stock_status_changed_auto'] === null) {
            $invalidProperties[] = "'stock_status_changed_auto' can't be null";
        }
        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {

        if ($this->container['qty'] === null) {
            return false;
        }
        if ($this->container['is_in_stock'] === null) {
            return false;
        }
        if ($this->container['is_qty_decimal'] === null) {
            return false;
        }
        if ($this->container['show_default_notification_message'] === null) {
            return false;
        }
        if ($this->container['use_config_min_qty'] === null) {
            return false;
        }
        if ($this->container['min_qty'] === null) {
            return false;
        }
        if ($this->container['use_config_min_sale_qty'] === null) {
            return false;
        }
        if ($this->container['min_sale_qty'] === null) {
            return false;
        }
        if ($this->container['use_config_max_sale_qty'] === null) {
            return false;
        }
        if ($this->container['max_sale_qty'] === null) {
            return false;
        }
        if ($this->container['use_config_backorders'] === null) {
            return false;
        }
        if ($this->container['backorders'] === null) {
            return false;
        }
        if ($this->container['use_config_notify_stock_qty'] === null) {
            return false;
        }
        if ($this->container['notify_stock_qty'] === null) {
            return false;
        }
        if ($this->container['use_config_qty_increments'] === null) {
            return false;
        }
        if ($this->container['qty_increments'] === null) {
            return false;
        }
        if ($this->container['use_config_enable_qty_inc'] === null) {
            return false;
        }
        if ($this->container['enable_qty_increments'] === null) {
            return false;
        }
        if ($this->container['use_config_manage_stock'] === null) {
            return false;
        }
        if ($this->container['manage_stock'] === null) {
            return false;
        }
        if ($this->container['low_stock_date'] === null) {
            return false;
        }
        if ($this->container['is_decimal_divided'] === null) {
            return false;
        }
        if ($this->container['stock_status_changed_auto'] === null) {
            return false;
        }
        return true;
    }


    /**
     * Gets item_id
     *
     * @return int
     */
    public function getItemId()
    {
        return $this->container['item_id'];
    }

    /**
     * Sets item_id
     *
     * @param int $item_id item_id
     *
     * @return $this
     */
    public function setItemId($item_id)
    {
        $this->container['item_id'] = $item_id;

        return $this;
    }

    /**
     * Gets product_id
     *
     * @return int
     */
    public function getProductId()
    {
        return $this->container['product_id'];
    }

    /**
     * Sets product_id
     *
     * @param int $product_id product_id
     *
     * @return $this
     */
    public function setProductId($product_id)
    {
        $this->container['product_id'] = $product_id;

        return $this;
    }

    /**
     * Gets stock_id
     *
     * @return int
     */
    public function getStockId()
    {
        return $this->container['stock_id'];
    }

    /**
     * Sets stock_id
     *
     * @param int $stock_id Stock identifier
     *
     * @return $this
     */
    public function setStockId($stock_id)
    {
        $this->container['stock_id'] = $stock_id;

        return $this;
    }

    /**
     * Gets qty
     *
     * @return float
     */
    public function getQty()
    {
        return $this->container['qty'];
    }

    /**
     * Sets qty
     *
     * @param float $qty qty
     *
     * @return $this
     */
    public function setQty($qty)
    {
        $this->container['qty'] = $qty;

        return $this;
    }

    /**
     * Gets is_in_stock
     *
     * @return bool
     */
    public function getIsInStock()
    {
        return $this->container['is_in_stock'];
    }

    /**
     * Sets is_in_stock
     *
     * @param bool $is_in_stock Stock Availability
     *
     * @return $this
     */
    public function setIsInStock($is_in_stock)
    {
        $this->container['is_in_stock'] = $is_in_stock;

        return $this;
    }

    /**
     * Gets is_qty_decimal
     *
     * @return bool
     */
    public function getIsQtyDecimal()
    {
        return $this->container['is_qty_decimal'];
    }

    /**
     * Sets is_qty_decimal
     *
     * @param bool $is_qty_decimal is_qty_decimal
     *
     * @return $this
     */
    public function setIsQtyDecimal($is_qty_decimal)
    {
        $this->container['is_qty_decimal'] = $is_qty_decimal;

        return $this;
    }

    /**
     * Gets show_default_notification_message
     *
     * @return bool
     */
    public function getShowDefaultNotificationMessage()
    {
        return $this->container['show_default_notification_message'];
    }

    /**
     * Sets show_default_notification_message
     *
     * @param bool $show_default_notification_message show_default_notification_message
     *
     * @return $this
     */
    public function setShowDefaultNotificationMessage($show_default_notification_message)
    {
        $this->container['show_default_notification_message'] = $show_default_notification_message;

        return $this;
    }

    /**
     * Gets use_config_min_qty
     *
     * @return bool
     */
    public function getUseConfigMinQty()
    {
        return $this->container['use_config_min_qty'];
    }

    /**
     * Sets use_config_min_qty
     *
     * @param bool $use_config_min_qty use_config_min_qty
     *
     * @return $this
     */
    public function setUseConfigMinQty($use_config_min_qty)
    {
        $this->container['use_config_min_qty'] = $use_config_min_qty;

        return $this;
    }

    /**
     * Gets min_qty
     *
     * @return float
     */
    public function getMinQty()
    {
        return $this->container['min_qty'];
    }

    /**
     * Sets min_qty
     *
     * @param float $min_qty Minimal quantity available for item status in stock
     *
     * @return $this
     */
    public function setMinQty($min_qty)
    {
        $this->container['min_qty'] = $min_qty;

        return $this;
    }

    /**
     * Gets use_config_min_sale_qty
     *
     * @return int
     */
    public function getUseConfigMinSaleQty()
    {
        return $this->container['use_config_min_sale_qty'];
    }

    /**
     * Sets use_config_min_sale_qty
     *
     * @param int $use_config_min_sale_qty use_config_min_sale_qty
     *
     * @return $this
     */
    public function setUseConfigMinSaleQty($use_config_min_sale_qty)
    {
        $this->container['use_config_min_sale_qty'] = $use_config_min_sale_qty;

        return $this;
    }

    /**
     * Gets min_sale_qty
     *
     * @return float
     */
    public function getMinSaleQty()
    {
        return $this->container['min_sale_qty'];
    }

    /**
     * Sets min_sale_qty
     *
     * @param float $min_sale_qty Minimum Qty Allowed in Shopping Cart or NULL when there is no limitation
     *
     * @return $this
     */
    public function setMinSaleQty($min_sale_qty)
    {
        $this->container['min_sale_qty'] = $min_sale_qty;

        return $this;
    }

    /**
     * Gets use_config_max_sale_qty
     *
     * @return bool
     */
    public function getUseConfigMaxSaleQty()
    {
        return $this->container['use_config_max_sale_qty'];
    }

    /**
     * Sets use_config_max_sale_qty
     *
     * @param bool $use_config_max_sale_qty use_config_max_sale_qty
     *
     * @return $this
     */
    public function setUseConfigMaxSaleQty($use_config_max_sale_qty)
    {
        $this->container['use_config_max_sale_qty'] = $use_config_max_sale_qty;

        return $this;
    }

    /**
     * Gets max_sale_qty
     *
     * @return float
     */
    public function getMaxSaleQty()
    {
        return $this->container['max_sale_qty'];
    }

    /**
     * Sets max_sale_qty
     *
     * @param float $max_sale_qty Maximum Qty Allowed in Shopping Cart data wrapper
     *
     * @return $this
     */
    public function setMaxSaleQty($max_sale_qty)
    {
        $this->container['max_sale_qty'] = $max_sale_qty;

        return $this;
    }

    /**
     * Gets use_config_backorders
     *
     * @return bool
     */
    public function getUseConfigBackorders()
    {
        return $this->container['use_config_backorders'];
    }

    /**
     * Sets use_config_backorders
     *
     * @param bool $use_config_backorders use_config_backorders
     *
     * @return $this
     */
    public function setUseConfigBackorders($use_config_backorders)
    {
        $this->container['use_config_backorders'] = $use_config_backorders;

        return $this;
    }

    /**
     * Gets backorders
     *
     * @return int
     */
    public function getBackorders()
    {
        return $this->container['backorders'];
    }

    /**
     * Sets backorders
     *
     * @param int $backorders Backorders status
     *
     * @return $this
     */
    public function setBackorders($backorders)
    {
        $this->container['backorders'] = $backorders;

        return $this;
    }

    /**
     * Gets use_config_notify_stock_qty
     *
     * @return bool
     */
    public function getUseConfigNotifyStockQty()
    {
        return $this->container['use_config_notify_stock_qty'];
    }

    /**
     * Sets use_config_notify_stock_qty
     *
     * @param bool $use_config_notify_stock_qty use_config_notify_stock_qty
     *
     * @return $this
     */
    public function setUseConfigNotifyStockQty($use_config_notify_stock_qty)
    {
        $this->container['use_config_notify_stock_qty'] = $use_config_notify_stock_qty;

        return $this;
    }

    /**
     * Gets notify_stock_qty
     *
     * @return float
     */
    public function getNotifyStockQty()
    {
        return $this->container['notify_stock_qty'];
    }

    /**
     * Sets notify_stock_qty
     *
     * @param float $notify_stock_qty Notify for Quantity Below data wrapper
     *
     * @return $this
     */
    public function setNotifyStockQty($notify_stock_qty)
    {
        $this->container['notify_stock_qty'] = $notify_stock_qty;

        return $this;
    }

    /**
     * Gets use_config_qty_increments
     *
     * @return bool
     */
    public function getUseConfigQtyIncrements()
    {
        return $this->container['use_config_qty_increments'];
    }

    /**
     * Sets use_config_qty_increments
     *
     * @param bool $use_config_qty_increments use_config_qty_increments
     *
     * @return $this
     */
    public function setUseConfigQtyIncrements($use_config_qty_increments)
    {
        $this->container['use_config_qty_increments'] = $use_config_qty_increments;

        return $this;
    }

    /**
     * Gets qty_increments
     *
     * @return float
     */
    public function getQtyIncrements()
    {
        return $this->container['qty_increments'];
    }

    /**
     * Sets qty_increments
     *
     * @param float $qty_increments Quantity Increments data wrapper
     *
     * @return $this
     */
    public function setQtyIncrements($qty_increments)
    {
        $this->container['qty_increments'] = $qty_increments;

        return $this;
    }

    /**
     * Gets use_config_enable_qty_inc
     *
     * @return bool
     */
    public function getUseConfigEnableQtyInc()
    {
        return $this->container['use_config_enable_qty_inc'];
    }

    /**
     * Sets use_config_enable_qty_inc
     *
     * @param bool $use_config_enable_qty_inc use_config_enable_qty_inc
     *
     * @return $this
     */
    public function setUseConfigEnableQtyInc($use_config_enable_qty_inc)
    {
        $this->container['use_config_enable_qty_inc'] = $use_config_enable_qty_inc;

        return $this;
    }

    /**
     * Gets enable_qty_increments
     *
     * @return bool
     */
    public function getEnableQtyIncrements()
    {
        return $this->container['enable_qty_increments'];
    }

    /**
     * Sets enable_qty_increments
     *
     * @param bool $enable_qty_increments Whether Quantity Increments is enabled
     *
     * @return $this
     */
    public function setEnableQtyIncrements($enable_qty_increments)
    {
        $this->container['enable_qty_increments'] = $enable_qty_increments;

        return $this;
    }

    /**
     * Gets use_config_manage_stock
     *
     * @return bool
     */
    public function getUseConfigManageStock()
    {
        return $this->container['use_config_manage_stock'];
    }

    /**
     * Sets use_config_manage_stock
     *
     * @param bool $use_config_manage_stock use_config_manage_stock
     *
     * @return $this
     */
    public function setUseConfigManageStock($use_config_manage_stock)
    {
        $this->container['use_config_manage_stock'] = $use_config_manage_stock;

        return $this;
    }

    /**
     * Gets manage_stock
     *
     * @return bool
     */
    public function getManageStock()
    {
        return $this->container['manage_stock'];
    }

    /**
     * Sets manage_stock
     *
     * @param bool $manage_stock Can Manage Stock
     *
     * @return $this
     */
    public function setManageStock($manage_stock)
    {
        $this->container['manage_stock'] = $manage_stock;

        return $this;
    }

    /**
     * Gets low_stock_date
     *
     * @return string
     */
    public function getLowStockDate()
    {
        return $this->container['low_stock_date'];
    }

    /**
     * Sets low_stock_date
     *
     * @param string $low_stock_date low_stock_date
     *
     * @return $this
     */
    public function setLowStockDate($low_stock_date)
    {
        $this->container['low_stock_date'] = $low_stock_date;

        return $this;
    }

    /**
     * Gets is_decimal_divided
     *
     * @return bool
     */
    public function getIsDecimalDivided()
    {
        return $this->container['is_decimal_divided'];
    }

    /**
     * Sets is_decimal_divided
     *
     * @param bool $is_decimal_divided is_decimal_divided
     *
     * @return $this
     */
    public function setIsDecimalDivided($is_decimal_divided)
    {
        $this->container['is_decimal_divided'] = $is_decimal_divided;

        return $this;
    }

    /**
     * Gets stock_status_changed_auto
     *
     * @return int
     */
    public function getStockStatusChangedAuto()
    {
        return $this->container['stock_status_changed_auto'];
    }

    /**
     * Sets stock_status_changed_auto
     *
     * @param int $stock_status_changed_auto stock_status_changed_auto
     *
     * @return $this
     */
    public function setStockStatusChangedAuto($stock_status_changed_auto)
    {
        $this->container['stock_status_changed_auto'] = $stock_status_changed_auto;

        return $this;
    }

    /**
     * Gets extension_attributes
     *
     * @return \Swagger\Client\Model\CatalogInventoryDataStockItemExtensionInterface
     */
    public function getExtensionAttributes()
    {
        return $this->container['extension_attributes'];
    }

    /**
     * Sets extension_attributes
     *
     * @param \Swagger\Client\Model\CatalogInventoryDataStockItemExtensionInterface $extension_attributes extension_attributes
     *
     * @return $this
     */
    public function setExtensionAttributes($extension_attributes)
    {
        $this->container['extension_attributes'] = $extension_attributes;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


