<?php

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class ItemDetails extends Column
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
                $details = !empty($item['item_details']) ? $item['item_details'] : '';
                
                // Format for HTML display
                if (!empty($details)) {
                    $items = explode(' | ', $details);
                    if (count($items) > 2) {
                        // Show first 2 items and count of remaining
                        $display = implode('<br/>', array_slice($items, 0, 2));
                        $remaining = count($items) - 2;
                        $display .= '<br/><small>+' . $remaining . ' ' . __('more items') . '</small>';
                        $item[$this->getData('name')] = '<div title="' . htmlspecialchars($details) . '">' . $display . '</div>';
                    } else {
                        $item[$this->getData('name')] = str_replace(' | ', '<br/>', htmlspecialchars($details));
                    }
                } else {
                    $item[$this->getData('name')] = '';
                }
            }
        }
        
        return $dataSource;
    }
}