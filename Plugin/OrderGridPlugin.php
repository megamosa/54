<?php
/**
 * MagoArab OrderEnhancer Order Grid Plugin - FIXED VERSION
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Plugin;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\App\RequestInterface;

class OrderGridPlugin
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var array
     */
    protected $joinedTables = [];

    /**
     * @param HelperData $helperData
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     */
    public function __construct(
        HelperData $helperData,
        LoggerInterface $logger,
        RequestInterface $request
    ) {
        $this->helperData = $helperData;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * Before load to add required columns and fix filters
     *
     * @param Collection $subject
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     */
    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        if (!$subject->isLoaded()) {
            $this->addCustomColumns($subject);
            $this->applyCustomFilters($subject);
        }
        
        return [$printQuery, $logQuery];
    }

    /**
     * Add custom columns to order grid
     *
     * @param Collection $collection
     */
    protected function addCustomColumns(Collection $collection)
    {
        try {
            $select = $collection->getSelect();
            
            // Reset joined tables tracking
            $this->joinedTables = [];
            
            // Join with sales_order table for basic order data
            $this->joinSalesOrderTable($select, $collection);
            
            // Join billing address table once
            $this->joinBillingAddressTable($select, $collection);
            
            // Join shipping address table once
            $this->joinShippingAddressTable($select, $collection);
            
            // Add order name from SHIPPING ADDRESS ONLY as requested
            $this->addOrderNameColumn($select);
            
            // Add phone columns
            $this->addPhoneColumns($select);
            
            // Add address columns
            $this->addAddressColumns($select);
            
            // Add item details - optimized
            if ($this->helperData->isProductColumnsEnabled()) {
                $this->addItemDetailsColumns($select, $collection);
            }
            
            // Add alternative phone and order comments from Amasty custom fields
            $this->addAmastyCustomFields($select, $collection);
            
            // Log only if logging is enabled
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->info('OrderGridPlugin: Successfully added custom columns');
            }

        } catch (\Exception $e) {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->error('OrderGridPlugin Error: ' . $e->getMessage());
                $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            }
        }
    }

    /**
     * Apply custom filters to the collection
     *
     * @param Collection $collection
     */
    protected function applyCustomFilters(Collection $collection)
    {
        try {
            $params = $this->request->getParams();
            
            // Apply Order Name filter (from shipping address)
            if (!empty($params['order_name']) || !empty($params['enhanced_customer_name'])) {
                $filterValue = $params['order_name'] ?? $params['enhanced_customer_name'];
                $collection->addFieldToFilter(
                    ['shipping_addr.firstname', 'shipping_addr.lastname'],
                    [
                        ['like' => '%' . $filterValue . '%'],
                        ['like' => '%' . $filterValue . '%']
                    ]
                );
            }
            
            // Apply Phone Number filter
            if (!empty($params['phone_number'])) {
                $collection->addFieldToFilter(
                    ['billing_addr.telephone', 'shipping_addr.telephone'],
                    [
                        ['like' => '%' . $params['phone_number'] . '%'],
                        ['like' => '%' . $params['phone_number'] . '%']
                    ]
                );
            }
            
            // Remove governorate filter from here as it's handled by OrderGridFilterPlugin
            
            // Apply City filter
            if (!empty($params['city'])) {
                $collection->addFieldToFilter(
                    ['billing_addr.city', 'shipping_addr.city'],
                    [
                        ['like' => '%' . $params['city'] . '%'],
                        ['like' => '%' . $params['city'] . '%']
                    ]
                );
            }
            
            // Apply Street Address filter
            if (!empty($params['street_address'])) {
                $collection->addFieldToFilter(
                    ['billing_addr.street', 'shipping_addr.street'],
                    [
                        ['like' => '%' . $params['street_address'] . '%'],
                        ['like' => '%' . $params['street_address'] . '%']
                    ]
                );
            }
            
        } catch (\Exception $e) {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->error('Custom filters error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Join sales_order table
     */
    protected function joinSalesOrderTable($select, $collection)
    {
        if (!isset($this->joinedTables['sales_order'])) {
            $select->joinLeft(
                ['so' => $collection->getTable('sales_order')],
                'so.entity_id = main_table.entity_id',
                [
                    'order_id' => 'so.increment_id',
                    'customer_note' => 'so.customer_note',
                    'discount_amount' => 'so.discount_amount',
                    'total_qty_ordered' => 'so.total_qty_ordered',
                    'customer_email' => 'so.customer_email',
                    'customer_firstname' => 'so.customer_firstname',
                    'customer_lastname' => 'so.customer_lastname',
                    'quote_id' => 'so.quote_id'
                ]
            );
            $this->joinedTables['sales_order'] = true;
        }
    }

    /**
     * Join billing address table
     */
    protected function joinBillingAddressTable($select, $collection)
    {
        if (!isset($this->joinedTables['billing_address'])) {
            $select->joinLeft(
                ['billing_addr' => $collection->getTable('sales_order_address')],
                'billing_addr.parent_id = main_table.entity_id AND billing_addr.address_type = "billing"',
                [
                    'billing_firstname' => 'billing_addr.firstname',
                    'billing_lastname' => 'billing_addr.lastname',
                    'billing_telephone' => 'billing_addr.telephone',
                    'billing_region' => 'billing_addr.region',
                    'billing_city' => 'billing_addr.city',
                    'billing_street' => 'billing_addr.street'
                ]
            );
            $this->joinedTables['billing_address'] = true;
        }
    }

    /**
     * Join shipping address table
     */
    protected function joinShippingAddressTable($select, $collection)
    {
        if (!isset($this->joinedTables['shipping_address'])) {
            $select->joinLeft(
                ['shipping_addr' => $collection->getTable('sales_order_address')],
                'shipping_addr.parent_id = main_table.entity_id AND shipping_addr.address_type = "shipping"',
                [
                    'shipping_firstname' => 'shipping_addr.firstname',
                    'shipping_lastname' => 'shipping_addr.lastname',
                    'shipping_telephone' => 'shipping_addr.telephone',
                    'shipping_region' => 'shipping_addr.region',
                    'shipping_city' => 'shipping_addr.city',
                    'shipping_street' => 'shipping_addr.street'
                ]
            );
            $this->joinedTables['shipping_address'] = true;
        }
    }

    /**
     * Add order name column from SHIPPING ADDRESS ONLY
     */
    protected function addOrderNameColumn($select)
    {
        // Order Name from Shipping Address ONLY as requested
        $orderNameExpression = new \Zend_Db_Expr('
            TRIM(
                COALESCE(
                    NULLIF(TRIM(CONCAT(
                        IFNULL(shipping_addr.firstname, ""), 
                        " ", 
                        IFNULL(shipping_addr.lastname, "")
                    )), ""),
                    "Guest Customer"
                )
            )
        ');

        // Add both columns for compatibility
        $select->columns([
            'order_name' => $orderNameExpression,
            'enhanced_customer_name' => $orderNameExpression
        ]);
    }

    /**
     * Add phone columns
     */
    protected function addPhoneColumns($select)
    {
        // Primary phone from billing, fallback to shipping
        $phoneExpression = new \Zend_Db_Expr('
            COALESCE(
                NULLIF(billing_addr.telephone, ""),
                NULLIF(shipping_addr.telephone, ""),
                ""
            )
        ');

        $select->columns(['phone_number' => $phoneExpression]);
    }

    /**
     * Add address columns
     */
    protected function addAddressColumns($select)
    {
        // Governorate/Region
        $regionExpression = new \Zend_Db_Expr('
            COALESCE(
                NULLIF(billing_addr.region, ""),
                NULLIF(shipping_addr.region, ""),
                ""
            )
        ');

        // City
        $cityExpression = new \Zend_Db_Expr('
            COALESCE(
                NULLIF(billing_addr.city, ""),
                NULLIF(shipping_addr.city, ""),
                ""
            )
        ');

        // Street
        $streetExpression = new \Zend_Db_Expr('
            COALESCE(
                NULLIF(billing_addr.street, ""),
                NULLIF(shipping_addr.street, ""),
                ""
            )
        ');

        $select->columns([
            'governorate' => $regionExpression,
            'city' => $cityExpression,
            'street_address' => $streetExpression
        ]);
    }

    /**
     * Add Amasty custom fields (Alternative Phone and Order Comments)
     */
    protected function addAmastyCustomFields($select, $collection)
    {
        try {
            $amastyTable = $collection->getTable('amasty_amcheckout_quote_custom_fields');
            
            // Check if table exists
            $connection = $collection->getConnection();
            if ($connection->isTableExists($amastyTable)) {
                // Alternative Phone (custom_field_1)
                $alternativePhoneExpression = new \Zend_Db_Expr("
                    (SELECT COALESCE(acf1.shipping_value, acf1.billing_value, '')
                     FROM {$amastyTable} acf1
                     WHERE acf1.quote_id = so.quote_id 
                     AND acf1.name = 'custom_field_1'
                     LIMIT 1)
                ");
                
                // Order Comments (custom_field_2) - if not in customer_note
                $orderCommentsExpression = new \Zend_Db_Expr("
                    COALESCE(
                        NULLIF(so.customer_note, ''),
                        (SELECT COALESCE(acf2.shipping_value, acf2.billing_value, '')
                         FROM {$amastyTable} acf2
                         WHERE acf2.quote_id = so.quote_id 
                         AND acf2.name = 'custom_field_2'
                         LIMIT 1),
                        ''
                    )
                ");
                
                $select->columns([
                    'alternative_phone' => $alternativePhoneExpression,
                    'order_comments' => $orderCommentsExpression
                ]);
            } else {
                // Fallback if Amasty table doesn't exist
                $select->columns([
                    'alternative_phone' => new \Zend_Db_Expr('""'),
                    'order_comments' => 'so.customer_note'
                ]);
            }
        } catch (\Exception $e) {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->warning('Amasty custom fields not available: ' . $e->getMessage());
            }
            
            // Fallback columns
            $select->columns([
                'alternative_phone' => new \Zend_Db_Expr('""'),
                'order_comments' => 'so.customer_note'
            ]);
        }
    }

    /**
     * Add item details columns
     */
    protected function addItemDetailsColumns($select, $collection)
    {
        $itemsTable = $collection->getTable('sales_order_item');
        
        // Item details
        $itemDetailsExpression = new \Zend_Db_Expr("
            (SELECT GROUP_CONCAT(
                CONCAT(
                    IFNULL(name, 'Unknown'), 
                    ' (SKU: ', IFNULL(sku, 'N/A'), 
                    ', Qty: ', CAST(IFNULL(qty_ordered, 0) AS CHAR), ')'
                ) SEPARATOR ' | '
            ) 
            FROM {$itemsTable}
            WHERE order_id = main_table.entity_id 
            AND parent_item_id IS NULL
            GROUP BY order_id)
        ");

        // Item prices
        $itemPricesExpression = new \Zend_Db_Expr("
            (SELECT GROUP_CONCAT(
                CAST(ROUND(IFNULL(price, 0), 2) AS CHAR) SEPARATOR ', '
            ) 
            FROM {$itemsTable}
            WHERE order_id = main_table.entity_id 
            AND parent_item_id IS NULL
            GROUP BY order_id)
        ");

        // Items subtotal
        $itemSubtotalExpression = new \Zend_Db_Expr("
            (SELECT IFNULL(SUM(row_total), 0) 
            FROM {$itemsTable}
            WHERE order_id = main_table.entity_id 
            AND parent_item_id IS NULL)
        ");

        $select->columns([
            'item_details' => $itemDetailsExpression,
            'item_prices' => $itemPricesExpression,
            'items_subtotal' => $itemSubtotalExpression
        ]);
    }
}