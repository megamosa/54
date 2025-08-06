<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class City extends Column
{
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
                $city = '';
                
                if (!empty($item['city'])) {
                    $city = $item['city'];
                } elseif (!empty($item['billing_city'])) {
                    $city = $item['billing_city'];
                } elseif (!empty($item['shipping_city'])) {
                    $city = $item['shipping_city'];
                }
                
                $item[$this->getData('name')] = $city;
            }
        }
        
        return $dataSource;
    }
}