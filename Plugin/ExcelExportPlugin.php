<?php
/**
 * MagoArab OrderEnhancer Excel Export Plugin - FIXED VERSION
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Plugin;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use MagoArab\OrderEnhancer\Helper\Data as HelperData;

class ExcelExportPlugin
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * Required columns mapping - Updated with Order Name from Shipping Only
     */
    private const REQUIRED_COLUMNS = [
        'Order ID' => ['Order ID', 'increment_id', 'Increment Id', 'entity_id'],
        'Order Date' => ['Order Date', 'created_at', 'Created At'],
        'Order Name' => ['Order Name', 'order_name', 'enhanced_customer_name', 'shipping_firstname', 'shipping_lastname'],
        'Customer Email' => ['Customer Email', 'customer_email'],
        'Phone Number' => ['Phone Number', 'phone_number', 'billing_telephone', 'shipping_telephone'],
        'Alternative Phone' => ['Alternative Phone', 'alternative_phone'],
        'Order Comments' => ['Order Comments', 'order_comments', 'customer_note'],
        'Order Status' => ['Order Status', 'status', 'Status'],
        'Governorate' => ['Governorate', 'governorate', 'billing_region', 'shipping_region'],
        'City' => ['City', 'city', 'billing_city', 'shipping_city'],
        'Street Address' => ['Street Address', 'street_address', 'billing_street', 'shipping_street'],
        'Total Quantity Ordered' => ['Total Quantity Ordered', 'total_qty_ordered'],
        'Item Details' => ['Item Details', 'item_details'],
        'Item Price' => ['Item Price', 'item_prices'],
        'Subtotal' => ['Subtotal', 'subtotal', 'items_subtotal'],
        'Shipping Amount' => ['Shipping Amount', 'shipping_and_handling', 'Shipping and Handling'],
        'Discount Amount' => ['Discount Amount', 'discount_amount'],
        'Grand Total' => ['Grand Total', 'grand_total']
    ];

    /**
     * Constructor
     */
    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger,
        FileFactory $fileFactory,
        HelperData $helperData
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->fileFactory = $fileFactory;
        $this->helperData = $helperData;
    }

    /**
     * After get CSV file - ConvertToCsv
     */
    public function afterGetCsvFile($subject, $result)
    {
        if (!$this->helperData->isExcelExportEnabled()) {
            return $result;
        }

        try {
            $this->processExportResult($result);
        } catch (\Exception $e) {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->error('ExcelExportPlugin Error: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Process export result
     */
    protected function processExportResult($result)
    {
        $filePath = $this->extractFilePath($result);
        
        if ($filePath) {
            $this->enhanceOrderExport($filePath);
        }
    }

    /**
     * Extract file path from various result formats
     */
    protected function extractFilePath($result)
    {
        if (is_array($result)) {
            return $result['value'] ?? $result['file'] ?? null;
        }
        
        return is_string($result) ? $result : null;
    }

    /**
     * Enhance order export
     */
    protected function enhanceOrderExport($filePath)
    {
        try {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->info('EnhanceOrderExport: Starting with file path: ' . $filePath);
            }
            
            $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            
            $fullPath = $this->findValidFilePath($directory, $filePath);
            if (!$fullPath) {
                if ($this->helperData->isLoggingEnabled()) {
                    $this->logger->error('EnhanceOrderExport: Could not find valid file path for: ' . $filePath);
                }
                return;
            }

            $content = $directory->readFile($fullPath);
            if (empty($content)) {
                if ($this->helperData->isLoggingEnabled()) {
                    $this->logger->error('EnhanceOrderExport: File is empty: ' . $fullPath);
                }
                return;
            }
            
            // Remove BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            
            $lines = explode("\n", $content);
            if (empty($lines) || empty(trim($lines[0]))) {
                if ($this->helperData->isLoggingEnabled()) {
                    $this->logger->error('EnhanceOrderExport: No valid lines found in file');
                }
                return;
            }

            // Process and organize the CSV data
            $this->processOrderData($lines, $directory, $fullPath);
            
        } catch (\Exception $e) {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->error('Error enhancing order export: ' . $e->getMessage());
                $this->logger->error('Stack trace: ' . $e->getTraceAsString());
            }
        }
    }

    /**
     * Find valid file path
     */
    protected function findValidFilePath($directory, $filePath)
    {
        $paths = [
            $filePath,
            'export/' . basename($filePath),
            'tmp/' . basename($filePath)
        ];

        foreach ($paths as $path) {
            if ($directory->isExist($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Process and organize order data
     */
    protected function processOrderData($lines, $directory, $fullPath)
    {
        if ($this->helperData->isLoggingEnabled()) {
            $this->logger->info('ProcessOrderData: Starting with ' . count($lines) . ' lines');
        }
        
        $header = $this->parseCsvLine($lines[0]);
        $expectedColumns = count($header);
        
        // Map headers to required columns
        $columnMapping = $this->mapColumnsToRequired($header);
        
        if (empty($columnMapping)) {
            if ($this->helperData->isLoggingEnabled()) {
                $this->logger->warning('ProcessOrderData: No column mapping found, fixing encoding only');
            }
            $this->fixEncodingOnly($lines, $directory, $fullPath);
            return;
        }
        
        // Process all data
        $processedData = [];
        $skippedRows = 0;
        $multilineBuffer = '';
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }
            
            // Handle potential multiline records
            $fullLine = $multilineBuffer . $line;
            $row = $this->parseCsvLine($fullLine);
            
            if (count($row) < $expectedColumns) {
                // This might be a multiline record, buffer it
                $multilineBuffer = $fullLine . "\n";
                continue;
            } elseif (count($row) > $expectedColumns) {
                // Too many columns, try to merge excess columns
                $mergedRow = array_slice($row, 0, $expectedColumns - 1);
                $lastColumn = implode(' ', array_slice($row, $expectedColumns - 1));
                $mergedRow[] = $lastColumn;
                $row = $mergedRow;
            }
            
            // Reset buffer since we have a complete row
            $multilineBuffer = '';
            
            if (count($row) !== $expectedColumns) {
                $skippedRows++;
                continue;
            }
            
            $processedRow = $this->processRow($row, $header, $columnMapping);
            if (!empty($processedRow)) {
                $processedData[] = $processedRow;
            }
        }
        
        if ($this->helperData->isLoggingEnabled()) {
            $this->logger->info('ProcessOrderData: Processed ' . count($processedData) . ' orders, skipped ' . $skippedRows . ' rows');
        }
        
        // Create enhanced CSV
        $this->createEnhancedCsv($processedData, array_keys($columnMapping), $directory, $fullPath);
    }

    /**
     * Process individual row
     */
    protected function processRow($row, $header, $columnMapping)
    {
        $processedRow = [];
        
        foreach ($columnMapping as $displayName => $originalIndex) {
            $value = '';
            
            if ($originalIndex !== null && isset($row[$originalIndex])) {
                $value = $row[$originalIndex];
            }
            
            // Special handling for certain fields
            $value = $this->processFieldValue($displayName, $value, $row, $header);
            
            $processedRow[] = $value;
        }
        
        return $processedRow;
    }

    /**
     * Process individual field value - Order Name from SHIPPING ONLY
     */
    protected function processFieldValue($displayName, $value, $row, $header)
    {
        switch ($displayName) {
            case 'Order ID':
                if (empty($value)) {
                    $value = $this->getOrderIncrementId($row, $header);
                }
                break;
                
            case 'Order Name':
                // ONLY use shipping address name as requested
                $value = $this->getShippingName($row, $header);
                break;
                
            case 'Phone Number':
                $value = $this->cleanPhoneNumber($value);
                break;
                
            case 'Alternative Phone':
                if (empty($value)) {
                    $value = $this->getAmastyCustomField($row, $header, 'custom_field_1');
                }
                break;
                
            case 'Order Comments':
                if (empty($value)) {
                    $value = $this->getAmastyCustomField($row, $header, 'custom_field_2');
                }
                $value = $this->processOrderComments($value);
                break;
                
            case 'Item Details':
                if (empty($value)) {
                    $value = $this->constructItemDetails($row, $header);
                }
                break;
        }
        
        return $this->cleanFieldValue($value);
    }

    /**
     * Get shipping name ONLY (as requested)
     */
    protected function getShippingName($row, $header)
    {
        $firstName = '';
        $lastName = '';
        
        // ONLY check shipping fields
        $shippingFirstFields = ['shipping_firstname', 'Shipping First Name'];
        $shippingLastFields = ['shipping_lastname', 'Shipping Last Name'];
        
        foreach ($shippingFirstFields as $field) {
            $index = array_search($field, $header);
            if ($index !== false && isset($row[$index]) && !empty(trim($row[$index]))) {
                $firstName = trim($row[$index]);
                break;
            }
        }
        
        foreach ($shippingLastFields as $field) {
            $index = array_search($field, $header);
            if ($index !== false && isset($row[$index]) && !empty(trim($row[$index]))) {
                $lastName = trim($row[$index]);
                break;
            }
        }
        
        $fullName = trim($firstName . ' ' . $lastName);
        
        // Return shipping name or Guest Customer
        return !empty($fullName) ? $fullName : 'Guest Customer';
    }

    /**
     * Get Amasty custom field value
     */
    protected function getAmastyCustomField($row, $header, $fieldName)
    {
        try {
            $orderId = $this->getOrderIncrementId($row, $header);
            if (empty($orderId)) {
                return '';
            }
            
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get('\Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            
            // Get quote_id from sales_order
            $quoteIdQuery = $connection->select()
                ->from(['so' => $resource->getTableName('sales_order')], ['quote_id'])
                ->where('so.increment_id = ?', $orderId)
                ->orWhere('so.entity_id = ?', $orderId);
                
            $quoteId = $connection->fetchOne($quoteIdQuery);
            
            if (!$quoteId) {
                return '';
            }
            
            // Get custom field value from amasty table
            $customFieldQuery = $connection->select()
                ->from(['acqcf' => $resource->getTableName('amasty_amcheckout_quote_custom_fields')], 
                       ['billing_value', 'shipping_value'])
                ->where('acqcf.quote_id = ?', $quoteId)
                ->where('acqcf.name = ?', $fieldName);
                
            $customFieldData = $connection->fetchRow($customFieldQuery);
            
            if ($customFieldData) {
                $value = !empty($customFieldData['shipping_value']) ? 
                         $customFieldData['shipping_value'] : 
                         $customFieldData['billing_value'];
                return $value;
            }
            
            return '';
            
        } catch (\Exception $e) {
            return '';
        }
    }

    // ... Other helper methods remain the same ...

    /**
     * Get Order Increment ID
     */
    protected function getOrderIncrementId($row, $header)
    {
        $incrementIdFields = ['increment_id', 'Increment Id', 'Order ID'];
        
        foreach ($incrementIdFields as $field) {
            $index = array_search($field, $header);
            if ($index !== false && !empty($row[$index])) {
                return $row[$index];
            }
        }
        
        return '';
    }

    /**
     * Construct item details if missing
     */
    protected function constructItemDetails($row, $header)
    {
        $productFields = ['product_name', 'name', 'item_name'];
        $skuFields = ['sku', 'product_sku'];
        $qtyFields = ['qty_ordered', 'qty', 'quantity'];
        
        $productName = '';
        $sku = '';
        $qty = '';
        
        foreach ($productFields as $field) {
            $index = array_search($field, $header);
            if ($index !== false && !empty($row[$index])) {
                $productName = $row[$index];
                break;
            }
        }
        
        foreach ($skuFields as $field) {
            $index = array_search($field, $header);
            if ($index !== false && !empty($row[$index])) {
                $sku = $row[$index];
                break;
            }
        }
        
        foreach ($qtyFields as $field) {
            $index = array_search($field, $header);
            if ($index !== false && !empty($row[$index])) {
                $qty = $row[$index];
                break;
            }
        }
        
        if (!empty($productName) || !empty($sku)) {
            return sprintf(
                '%s (SKU: %s, Qty: %s)',
                $productName ?: 'Unknown Product',
                $sku ?: 'N/A',
                $qty ?: '1'
            );
        }
        
        return '';
    }

    /**
     * Clean phone number
     */
    protected function cleanPhoneNumber($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        $phone = preg_replace('/[^\d+\s-]/', '', $phone);
        return trim($phone);
    }

    /**
     * Map original columns to required columns
     */
    protected function mapColumnsToRequired($header)
    {
        $mapping = [];
        
        foreach (self::REQUIRED_COLUMNS as $displayName => $possibleNames) {
            $found = false;
            foreach ($header as $index => $columnName) {
                $trimmedName = trim($columnName);
                if (in_array($trimmedName, $possibleNames)) {
                    $mapping[$displayName] = $index;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $mapping[$displayName] = null;
            }
        }
        
        return $mapping;
    }

    /**
     * Clean field value
     */
    protected function cleanFieldValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        $value = (string)$value;
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
        
        // Handle multiline content
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        
        // Remove control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // Remove excessive whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        
        // Handle problematic characters for CSV
        $value = str_replace(['"'], ['\"'], $value);
        
        // Prevent Excel formula errors
        if (str_starts_with($value, '=') || str_starts_with($value, '#')) {
            $value = "'" . $value;
        }
        
        return trim($value);
    }

    /**
     * Process Order Comments
     */
    protected function processOrderComments($comments)
    {
        if (empty($comments)) {
            return '';
        }
        
        $comments = (string)$comments;
        $comments = str_replace(["\r\n", "\r", "\n"], ' ', $comments);
        $comments = preg_replace('/\s+/', ' ', $comments);
        $comments = trim($comments);
        $comments = str_replace(['"', "'"], ['\"', "\'"], $comments);
        
        if (str_starts_with($comments, '=') || str_starts_with($comments, '#')) {
            $comments = "'" . $comments;
        }
        
        return $comments;
    }

    /**
     * Create enhanced CSV with proper structure
     */
    protected function createEnhancedCsv($data, $headers, $directory, $fullPath)
    {
        $csvLines = [];
        
        // Create header
        $csvLines[] = $this->createCsvLine($headers);
        
        // Process each row
        foreach ($data as $row) {
            $csvLines[] = $this->createCsvLine($row);
        }
        
        // Write enhanced CSV
        $csvContent = implode("\n", $csvLines);
        $csvContent = "\xEF\xBB\xBF" . $csvContent; // Add BOM for UTF-8
        
        $directory->writeFile($fullPath, $csvContent);
        
        if ($this->helperData->isLoggingEnabled()) {
            $this->logger->info('Successfully created enhanced CSV with ' . count($data) . ' orders');
        }
    }

    /**
     * Fix encoding without column filtering
     */
    protected function fixEncodingOnly($lines, $directory, $fullPath)
    {
        $csvLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $row = $this->parseCsvLine($line);
            $csvLines[] = $this->createCsvLine($row);
        }
        
        $csvContent = implode("\n", $csvLines);
        $csvContent = "\xEF\xBB\xBF" . $csvContent;
        
        $directory->writeFile($fullPath, $csvContent);
    }

    /**
     * Parse CSV line properly
     */
    private function parseCsvLine($line)
    {
        $result = str_getcsv($line, ',', '"', '\\');
        
        // If we get unexpected results, try alternative parsing
        if (count($result) == 1 && strpos($line, ',') !== false) {
            $result = [];
            $current = '';
            $inQuotes = false;
            $length = strlen($line);
            
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                
                if ($char === '"') {
                    if ($inQuotes && $i + 1 < $length && $line[$i + 1] === '"') {
                        $current .= '"';
                        $i++;
                    } else {
                        $inQuotes = !$inQuotes;
                    }
                } elseif ($char === ',' && !$inQuotes) {
                    $result[] = $current;
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
            
            $result[] = $current;
        }
        
        // Clean each field
        foreach ($result as $key => $value) {
            if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value)-1] === '"') {
                $value = substr($value, 1, -1);
            }
            
            $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
            $value = preg_replace('/\s+/', ' ', $value);
            $value = str_replace(['""'], ['"'], $value);
            
            $result[$key] = trim($value);
        }
        
        return $result;
    }

    /**
     * Create CSV line with proper encoding
     */
    private function createCsvLine($row)
    {
        $csvRow = [];
        foreach ($row as $field) {
            $field = $this->cleanFieldValue($field);
            
            if (strlen($field) > 2000) {
                $field = substr($field, 0, 1997) . '...';
            }
            
            $field = str_replace('"', '""', $field);
            $csvRow[] = '"' . $field . '"';
        }
        return implode(',', $csvRow);
    }
}