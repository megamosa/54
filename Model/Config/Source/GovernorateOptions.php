<?php
/**
 * MagoArab OrderEnhancer Governorate Options Source Model
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class GovernorateOptions implements OptionSourceInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var array
     */
    protected $options;

    /**
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Get options array for governorate dropdown
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $this->options = $this->getGovernorateOptions();
        }
        return $this->options;
    }

    /**
     * Get governorate options from database
     *
     * @return array
     */
    protected function getGovernorateOptions()
    {
        $options = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('sales_order_address');
            
            // Get unique governorates/regions from the actual database
            $select = $connection->select()
                ->from($tableName, ['region'])
                ->where('region IS NOT NULL')
                ->where('region != ?', '')
                ->group('region')
                ->order('region ASC');
            
            $governorates = $connection->fetchCol($select);
            
            // Add empty option first
            $options[] = [
                'value' => '',
                'label' => __('-- All Governorates/Regions --')
            ];
            
            // Add governorates from database
            foreach ($governorates as $governorate) {
                if (!empty(trim($governorate))) {
                    $options[] = [
                        'value' => $governorate,
                        'label' => $governorate
                    ];
                }
            }
            
            // If no governorates found in database, return text filter message
            if (count($options) <= 1) {
                $options[] = [
                    'value' => '',
                    'label' => __('Type to filter by region...')
                ];
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error loading governorate options: ' . $e->getMessage());
            // Return basic options
            $options = [
                ['value' => '', 'label' => __('-- All Governorates/Regions --')]
            ];
        }
        
        return $options;
    }

    /**
     * Get Egyptian governorates as fallback
     * Not used anymore - keeping for backward compatibility
     *
     * @return array
     */
    protected function getEgyptianGovernorates()
    {
        return [];
    }
}