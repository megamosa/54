<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class ItemPrices extends Column
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
                $prices = !empty($item['item_prices']) ? $item['item_prices'] : '';
                $item[$this->getData('name')] = $prices;
            }
        }
        
        return $dataSource;
    }
}