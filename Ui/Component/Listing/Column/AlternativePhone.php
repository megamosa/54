<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\App\ResourceConnection;

class AlternativePhone extends Column
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                // Get from calculated field or fetch from Amasty table
                if (!empty($item['alternative_phone'])) {
                    $item[$this->getData('name')] = $item['alternative_phone'];
                } else {
                    $item[$this->getData('name')] = $this->getAmastyAlternativePhone($item);
                }
            }
        }
        
        return $dataSource;
    }
    
    /**
     * Get alternative phone from Amasty custom fields
     *
     * @param array $item
     * @return string
     */
    protected function getAmastyAlternativePhone($item)
    {
        if (empty($item['quote_id'])) {
            return '';
        }
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('amasty_amcheckout_quote_custom_fields');
            
            if ($connection->isTableExists($tableName)) {
                $select = $connection->select()
                    ->from($tableName, ['shipping_value', 'billing_value'])
                    ->where('quote_id = ?', $item['quote_id'])
                    ->where('name = ?', 'custom_field_1')
                    ->limit(1);
                
                $result = $connection->fetchRow($select);
                
                if ($result) {
                    return !empty($result['shipping_value']) ? 
                           $result['shipping_value'] : 
                           $result['billing_value'];
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
        
        return '';
    }
}