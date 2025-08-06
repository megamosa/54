<?php
/**
 * MagoArab OrderEnhancer Governorate Dropdown Filter
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Block\Adminhtml\Order\Grid\Filter;

use Magento\Backend\Block\Widget\Grid\Column\Filter\Select;
use Magento\Framework\App\ResourceConnection;

class Governorate extends Select
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param \Magento\Backend\Block\Context $context
     * @param \Magento\Framework\DB\Helper $resourceHelper
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Framework\DB\Helper $resourceHelper,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $resourceHelper, $data);
    }

    /**
     * Get options for governorate dropdown
     *
     * @return array
     */
    protected function _getOptions()
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('sales_order_address');
            
            // Get unique governorates from database
            $select = $connection->select()
                ->from($tableName, ['region'])
                ->where('region IS NOT NULL')
                ->where('region != ?', '')
                ->group('region')
                ->order('region ASC');
            
            $governorates = $connection->fetchCol($select);
            
            foreach ($governorates as $governorate) {
                $options[] = [
                    'value' => $governorate,
                    'label' => $governorate
                ];
            }
            
        } catch (\Exception $e) {
            // If error, return Egyptian governorates as fallback
            $egyptianGovernorates = [
                'Cairo' => __('Cairo'),
                'Giza' => __('Giza'),
                'Alexandria' => __('Alexandria'),
                'Dakahlia' => __('Dakahlia'),
                'Red Sea' => __('Red Sea'),
                'Beheira' => __('Beheira'),
                'Fayoum' => __('Fayoum'),
                'Gharbiya' => __('Gharbiya'),
                'Ismailia' => __('Ismailia'),
                'Menofia' => __('Menofia'),
                'Minya' => __('Minya'),
                'Qaliubiya' => __('Qaliubiya'),
                'New Valley' => __('New Valley'),
                'Suez' => __('Suez'),
                'Aswan' => __('Aswan'),
                'Assiut' => __('Assiut'),
                'Beni Suef' => __('Beni Suef'),
                'Port Said' => __('Port Said'),
                'Damietta' => __('Damietta'),
                'Sharkia' => __('Sharkia'),
                'South Sinai' => __('South Sinai'),
                'Kafr el-Sheikh' => __('Kafr el-Sheikh'),
                'Matrouh' => __('Matrouh'),
                'Luxor' => __('Luxor'),
                'Qena' => __('Qena'),
                'North Sinai' => __('North Sinai'),
                'Sohag' => __('Sohag')
            ];
            
            foreach ($egyptianGovernorates as $value => $label) {
                $options[] = [
                    'value' => $value,
                    'label' => $label
                ];
            }
        }
        
        return $options;
    }

    /**
     * Get condition for filter
     *
     * @return array|null
     */
    public function getCondition()
    {
        if ($this->getValue() === null || $this->getValue() === '') {
            return null;
        }
        
        return ['like' => '%' . $this->getValue() . '%'];
    }
}