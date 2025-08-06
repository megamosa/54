<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class StreetAddress extends Column
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
                $street = '';
                
                if (!empty($item['street_address'])) {
                    $street = $item['street_address'];
                } elseif (!empty($item['billing_street'])) {
                    $street = $item['billing_street'];
                } elseif (!empty($item['shipping_street'])) {
                    $street = $item['shipping_street'];
                }
                
                // Format street address (remove extra line breaks)
                $street = str_replace(["\r\n", "\r", "\n"], ', ', $street);
                
                $item[$this->getData('name')] = $street;
            }
        }
        
        return $dataSource;
    }
}
