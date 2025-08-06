<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Governorate extends Column
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
                $governorate = '';
                
                if (!empty($item['governorate'])) {
                    $governorate = $item['governorate'];
                } elseif (!empty($item['billing_region'])) {
                    $governorate = $item['billing_region'];
                } elseif (!empty($item['shipping_region'])) {
                    $governorate = $item['shipping_region'];
                }
                
                $item[$this->getData('name')] = $governorate;
            }
        }
        
        return $dataSource;
    }
}