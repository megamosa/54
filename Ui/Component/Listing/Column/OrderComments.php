<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\App\ResourceConnection;

class OrderComments extends Column
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
                $comments = '';
                
                // First check customer_note
                if (!empty($item['customer_note'])) {
                    $comments = $item['customer_note'];
                } elseif (!empty($item['order_comments'])) {
                    $comments = $item['order_comments'];
                } else {
                    // Try to get from Amasty custom fields
                    $comments = $this->getAmastyOrderComments($item);
                }
                
                // Format for display
                $item[$this->getData('name')] = $this->formatComments($comments);
            }
        }
        
        return $dataSource;
    }
    
    /**
     * Get order comments from Amasty custom fields
     *
     * @param array $item
     * @return string
     */
    protected function getAmastyOrderComments($item)
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
                    ->where('name = ?', 'custom_field_2')
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
    
    /**
     * Format comments for display
     *
     * @param string $comments
     * @return string
     */
    protected function formatComments($comments)
    {
        if (empty($comments)) {
            return '';
        }
        
        // Truncate long comments for grid display
        if (strlen($comments) > 100) {
            return '<span title="' . htmlspecialchars($comments) . '">' . 
                   htmlspecialchars(substr($comments, 0, 97)) . '...</span>';
        }
        
        return htmlspecialchars($comments);
    }
}
