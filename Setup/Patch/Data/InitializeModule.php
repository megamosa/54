<?php
/**
 * MagoArab OrderEnhancer Setup Patch
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Psr\Log\LoggerInterface;

class InitializeModule implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    
    /**
     * @var CacheManager
     */
    private $cacheManager;
    
    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;
    
    /**
     * @var WriterInterface
     */
    private $configWriter;
    
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CacheManager $cacheManager
     * @param TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CacheManager $cacheManager,
        TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->cacheManager = $cacheManager;
        $this->cacheTypeList = $cacheTypeList;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        
        try {
            // Set default configuration values
            $this->setDefaultConfiguration();
            
            // Clear specific cache types
            $this->clearCache();
            
            $this->logger->info('MagoArab OrderEnhancer: Module initialized successfully');
            
        } catch (\Exception $e) {
            $this->logger->error('MagoArab OrderEnhancer: Error during initialization - ' . $e->getMessage());
        }
        
        $this->moduleDataSetup->endSetup();
    }

    /**
     * Set default configuration values
     */
    private function setDefaultConfiguration()
    {
        // Enable main features by default
        $this->configWriter->save('order_enhancer/general/enable_excel_export', 1);
        $this->configWriter->save('order_enhancer/general/enable_customer_email', 1);
        $this->configWriter->save('order_enhancer/general/consolidate_orders', 1);
        $this->configWriter->save('order_enhancer/general/utf8_encoding', 1);
        $this->configWriter->save('order_enhancer/general/enable_governorate_filter', 1);
        $this->configWriter->save('order_enhancer/general/enable_product_columns', 1);
        
        // Set customer data priorities
        $this->configWriter->save('order_enhancer/customer_data/name_priority', 'shipping_only');
        $this->configWriter->save('order_enhancer/customer_data/phone_fallback', 1);
        $this->configWriter->save('order_enhancer/customer_data/address_fallback', 1);
        
        // Set export settings
        $this->configWriter->save('order_enhancer/export_settings/delimiter', ',');
        $this->configWriter->save('order_enhancer/export_settings/enclosure', '"');
        $this->configWriter->save('order_enhancer/export_settings/date_format', 'Y-m-d H:i:s');
        
        // Disable debug logging by default
        $this->configWriter->save('order_enhancer/debug/enable_logging', 0);
        $this->configWriter->save('order_enhancer/debug/log_export_details', 0);
    }

    /**
     * Clear cache
     */
    private function clearCache()
    {
        // Clear specific cache types
        $types = [
            'config',
            'layout',
            'block_html',
            'collections',
            'reflection',
            'db_ddl',
            'compiled_config',
            'eav',
            'full_page'
        ];
        
        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        
        $this->logger->info('MagoArab OrderEnhancer: Cache cleared');
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}