<?php
/**
 * MagoArab OrderEnhancer Filter Plugin
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Plugin;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

class OrderGridFilterPlugin
{
    /**
     * @var RequestInterface
     */
    protected $request;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var bool
     */
    protected $governorateFilterApplied = false;
    
    /**
     * @var bool
     */
    protected $phoneFilterApplied = false;
    
    /**
     * @var bool
     */
    protected $alternativePhoneFilterApplied = false;
    
    // Remove the createdAtFilterApplied flag completely
    /**
     * @var bool
     */
    protected $createdAtFilterApplied = false;
    
    /**
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->logger = $logger;
    }
    
    /**
     * Around addFieldToFilter to handle custom filters
     *
     * @param Collection $subject
     * @param \Closure $proceed
     * @param string|array $field
     * @param string|array $condition
     * @return Collection
     */
    public function aroundAddFieldToFilter(Collection $subject, \Closure $proceed, $field, $condition = null)
    {
        // Handle governorate filter
        if ($field === 'governorate' || (is_array($field) && in_array('governorate', $field))) {
            $filterValue = $this->extractFilterValue($condition);
            
            if (!empty($filterValue) && !$this->governorateFilterApplied) {
                $this->applyGovernorateFilter($subject, $filterValue);
                $this->governorateFilterApplied = true;
            }
            
            return $subject;
        }
        
        // Handle phone_number filter
        if ($field === 'phone_number' || (is_array($field) && in_array('phone_number', $field))) {
            $filterValue = $this->extractFilterValue($condition);
            
            if (!empty($filterValue) && !$this->phoneFilterApplied) {
                $this->applyPhoneFilter($subject, $filterValue);
                $this->phoneFilterApplied = true;
            }
            
            return $subject;
        }
        
        // Handle alternative_phone filter
        if ($field === 'alternative_phone' || (is_array($field) && in_array('alternative_phone', $field))) {
            $filterValue = $this->extractFilterValue($condition);
            
            if (!empty($filterValue) && !$this->alternativePhoneFilterApplied) {
                $this->applyAlternativePhoneFilter($subject, $filterValue);
                $this->alternativePhoneFilterApplied = true;
            }
            
            return $subject;
        }
        
        // For all other fields (including created_at), proceed with the original Magento method
        return $proceed($field, $condition);
    }
    
    /**
     * Apply governorate filter with correct JOIN conditions
     *
     * @param Collection $collection
     * @param string $filterValue
     * @return void
     */
    protected function applyGovernorateFilter(Collection $collection, $filterValue)
    {
        $select = $collection->getSelect();
        $connection = $collection->getConnection();
        $escapedValue = $connection->quote('%' . $filterValue . '%');
        
        // Ensure address tables are joined
        $this->ensureAddressTablesJoined($collection);
        
        // Apply the filter
        $select->where(
            'billing_addr.region LIKE ' . $escapedValue . ' OR shipping_addr.region LIKE ' . $escapedValue
        );
        
        $this->logger->info('OrderGridFilterPlugin: Applied governorate filter for: ' . $filterValue);
    }
    
    /**
     * Apply phone filter with correct JOIN conditions
     *
     * @param Collection $collection
     * @param string $filterValue
     * @return void
     */
    protected function applyPhoneFilter(Collection $collection, $filterValue)
    {
        $select = $collection->getSelect();
        $connection = $collection->getConnection();
        $escapedValue = $connection->quote('%' . $filterValue . '%');
        
        // Ensure address tables are joined
        $this->ensureAddressTablesJoined($collection);
        
        // Apply the filter - search in both billing and shipping phone numbers
        $select->where(
            'billing_addr.telephone LIKE ' . $escapedValue . ' OR shipping_addr.telephone LIKE ' . $escapedValue
        );
        
        $this->logger->info('OrderGridFilterPlugin: Applied phone filter for: ' . $filterValue);
    }
    
    /**
     * Apply alternative phone filter with Amasty table JOIN
     *
     * @param Collection $collection
     * @param string $filterValue
     * @return void
     */
    protected function applyAlternativePhoneFilter(Collection $collection, $filterValue)
    {
        $select = $collection->getSelect();
        $connection = $collection->getConnection();
        $escapedValue = $connection->quote('%' . $filterValue . '%');
        
        // Ensure sales_order table is joined first
        $this->ensureSalesOrderJoined($collection);
        
        // Ensure Amasty table is joined
        $this->ensureAmastyTableJoined($collection);
        
        // Apply the filter - search in Amasty custom field
        $select->where(
            'amasty_custom_fields.shipping_value LIKE ' . $escapedValue . ' OR amasty_custom_fields.billing_value LIKE ' . $escapedValue
        );
        
        $this->logger->info('OrderGridFilterPlugin: Applied alternative phone filter for: ' . $filterValue);
    }
    
    /**
     * Apply created_at filter with explicit table reference
     *
     * @param Collection $collection
     * @param mixed $condition
     * @return void
     */
    protected function applyCreatedAtFilter(Collection $collection, $condition)
    {
        $select = $collection->getSelect();
        $connection = $collection->getConnection();
        
        if (is_array($condition)) {
            // Handle date range filters
            if (isset($condition['from'])) {
                $fromDate = $connection->quote($condition['from']);
                $select->where('main_table.created_at >= ' . $fromDate);
            }
            
            if (isset($condition['to'])) {
                $toDate = $connection->quote($condition['to']);
                $select->where('main_table.created_at <= ' . $toDate);
            }
            
            // Handle gteq (greater than or equal)
            if (isset($condition['gteq'])) {
                $gteqDate = $connection->quote($condition['gteq']);
                $select->where('main_table.created_at >= ' . $gteqDate);
            }
            
            // Handle lteq (less than or equal)
            if (isset($condition['lteq'])) {
                $lteqDate = $connection->quote($condition['lteq']);
                $select->where('main_table.created_at <= ' . $lteqDate);
            }
        } else if (is_string($condition)) {
            // Handle single date
            $escapedDate = $connection->quote($condition);
            $select->where('main_table.created_at = ' . $escapedDate);
        }
        
        $this->logger->info('OrderGridFilterPlugin: Applied created_at filter with main_table reference');
    }
    
    /**
     * Ensure sales_order table is joined
     *
     * @param Collection $collection
     * @return void
     */
    protected function ensureSalesOrderJoined(Collection $collection)
    {
        $select = $collection->getSelect();
        $fromPart = $select->getPart(\Zend_Db_Select::FROM);
        
        if (!isset($fromPart['so'])) {
            $select->joinLeft(
                ['so' => $collection->getTable('sales_order')],
                'so.entity_id = main_table.entity_id',
                [] // No columns to avoid conflicts
            );
            $this->logger->info('OrderGridFilterPlugin: Joined sales_order table for filter');
        }
    }
    
    /**
     * Ensure Amasty custom fields table is joined
     *
     * @param Collection $collection
     * @return void
     */
    protected function ensureAmastyTableJoined(Collection $collection)
    {
        $select = $collection->getSelect();
        $fromPart = $select->getPart(\Zend_Db_Select::FROM);
        
        if (!isset($fromPart['amasty_custom_fields'])) {
            $amastyTable = $collection->getTable('amasty_amcheckout_quote_custom_fields');
            
            $select->joinLeft(
                ['amasty_custom_fields' => $amastyTable],
                'amasty_custom_fields.quote_id = so.quote_id AND amasty_custom_fields.name = "custom_field_1"',
                [] // No columns to avoid conflicts
            );
            $this->logger->info('OrderGridFilterPlugin: Joined Amasty custom fields table for filter');
        }
    }
    
    /**
     * Ensure address tables are joined
     *
     * @param Collection $collection
     * @return void
     */
    protected function ensureAddressTablesJoined(Collection $collection)
    {
        $select = $collection->getSelect();
        $fromPart = $select->getPart(\Zend_Db_Select::FROM);
        $billingJoined = isset($fromPart['billing_addr']);
        $shippingJoined = isset($fromPart['shipping_addr']);
        
        // Only join if not already joined
        if (!$billingJoined) {
            $select->joinLeft(
                ['billing_addr' => $collection->getTable('sales_order_address')],
                'billing_addr.parent_id = main_table.entity_id AND billing_addr.address_type = "billing"',
                [] // No columns to avoid conflicts
            );
            $this->logger->info('OrderGridFilterPlugin: Joined billing address table for filter');
        } else {
            $this->logger->info('OrderGridFilterPlugin: Billing address table already joined');
        }
        
        if (!$shippingJoined) {
            $select->joinLeft(
                ['shipping_addr' => $collection->getTable('sales_order_address')],
                'shipping_addr.parent_id = main_table.entity_id AND shipping_addr.address_type = "shipping"',
                [] // No columns to avoid conflicts
            );
            $this->logger->info('OrderGridFilterPlugin: Joined shipping address table for filter');
        } else {
            $this->logger->info('OrderGridFilterPlugin: Shipping address table already joined');
        }
    }
    
    /**
     * Extract filter value safely from condition
     *
     * @param mixed $condition
     * @return string|null
     */
    protected function extractFilterValue($condition)
    {
        // Handle null or empty conditions
        if ($condition === null || $condition === '' || $condition === []) {
            return null;
        }
        
        // Handle string conditions
        if (is_string($condition)) {
            $trimmed = trim($condition);
            return !empty($trimmed) ? $trimmed : null;
        }
        
        // Handle array conditions
        if (is_array($condition)) {
            // Handle 'like' condition
            if (isset($condition['like'])) {
                $value = $condition['like'];
                if (is_string($value) && !empty($value)) {
                    // Safely remove % characters
                    $cleaned = str_replace('%', '', $value);
                    $trimmed = trim($cleaned);
                    return !empty($trimmed) ? $trimmed : null;
                }
            }
            
            // Handle 'eq' condition
            if (isset($condition['eq'])) {
                $value = $condition['eq'];
                if (is_string($value)) {
                    $trimmed = trim($value);
                    return !empty($trimmed) ? $trimmed : null;
                }
            }
            
            // Handle nested array conditions
            if (isset($condition[0]) && is_array($condition[0])) {
                return $this->extractFilterValue($condition[0]);
            }
            
            // Handle direct array values
            foreach ($condition as $key => $value) {
                if (is_string($value) && !empty(trim($value))) {
                    return trim($value);
                }
            }
        }
        
        return null;
    }
}