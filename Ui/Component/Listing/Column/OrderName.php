<?php
/**
 * MagoArab OrderEnhancer Order Name Column
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderAddressRepositoryInterface;

class OrderName extends Column
{
    /**
     * @var OrderAddressRepositoryInterface
     */
    protected $orderAddressRepository;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderAddressRepositoryInterface $orderAddressRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderAddressRepositoryInterface $orderAddressRepository,
        array $components = [],
        array $data = []
    ) {
        $this->orderAddressRepository = $orderAddressRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source - Get Order Name from Shipping Address ONLY
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                // Get order name from shipping address fields if available
                $orderName = '';
                
                // Priority: Use shipping address name ONLY
                if (!empty($item['shipping_firstname']) || !empty($item['shipping_lastname'])) {
                    $orderName = trim($item['shipping_firstname'] . ' ' . $item['shipping_lastname']);
                } elseif (!empty($item['order_name'])) {
                    // Use calculated order_name from query
                    $orderName = $item['order_name'];
                } elseif (!empty($item['enhanced_customer_name'])) {
                    // Fallback to enhanced_customer_name
                    $orderName = $item['enhanced_customer_name'];
                } else {
                    // Final fallback
                    $orderName = __('Guest Customer');
                }
                
                $item[$this->getData('name')] = $orderName;
            }
        }
        
        return $dataSource;
    }
}