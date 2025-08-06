<?php
namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class PhoneNumber extends Column
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
                $phone = '';
                
                if (!empty($item['phone_number'])) {
                    $phone = $item['phone_number'];
                } elseif (!empty($item['billing_telephone'])) {
                    $phone = $item['billing_telephone'];
                } elseif (!empty($item['shipping_telephone'])) {
                    $phone = $item['shipping_telephone'];
                }
                
                $item[$this->getData('name')] = $this->formatPhone($phone);
            }
        }
        
        return $dataSource;
    }
    
    /**
     * Format phone number
     *
     * @param string $phone
     * @return string
     */
    protected function formatPhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        // Remove non-numeric except + and spaces
        $phone = preg_replace('/[^\d+\s-]/', '', $phone);
        return trim($phone);
    }
}