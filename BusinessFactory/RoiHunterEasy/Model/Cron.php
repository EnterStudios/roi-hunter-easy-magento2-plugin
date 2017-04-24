<?php

namespace BusinessFactory\RoiHunterEasy\Model;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use XMLWriter;

/**
 * Class FeedExportAdapter
 * Inspiration from native products export: @var \Magento\CatalogImportExport\Model\Export\Product.php
 * @package BusinessFactory\RoiHunterEasy\Model
 */
class Cron
{
    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;
    protected $date;

    private $fileMy;

    private $count = 0;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Status
     */
    private $productStatus;

    /**
     * @var Visibility
     */
    private $productVisibility;

    public function __construct(
        Logger $logger,
        Registry $registry,
        ResourceConnection $resource,
        CollectionFactory $productCollectionFactory,
        ProductFactory $productFactory,
        CategoryFactory $categoryFactory,
        Status $productStatus,
        Visibility $productVisibility,
        Configurable $catalogProductTypeConfigurable,
        StoreManagerInterface $storeManager,
        StockRegistryInterface $stockRegistry,
        Filesystem $filesystem,
        File $file,
        DateTime $date
    )
    {
        $this->loggerMy = $logger;
        $this->_coreRegistry = $registry;
        $this->_coreResource = $resource;
        $this->collectionFactory = $productCollectionFactory;
        $this->_productFactory = $productFactory;
        $this->_categoryFactory = $categoryFactory;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->_catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
        $this->_storeManager = $storeManager;
        $this->stockRegistry = $stockRegistry;
        $this->filesystem = $filesystem;
        $this->fileMy = $file;
        $this->date = $date;
    }


    /**
     * Method start new feed creation process, if not another feed creation process running.
     */
    public function createFeed()
    {
        $this->loggerMy->info(__METHOD__ . ' cron');
        $path = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)->getAbsolutePath()
            . 'businessFactoryRoiHunterEasyFeedSign';
        try {
            if (!file_exists($path)) {
                // Create file
                $fp = fopen($path, 'wb');
                fwrite($fp, 'Running');
                fclose($fp);

                // Generate feed
                $this->generateAndSaveFeed();

                // Delete file
                $this->fileMy->deleteFile($path);
                return true;
            } else {
                $this->loggerMy->info('Feed generation already running.');
                return false;
            }
        } catch (\Exception $e) {
            $this->loggerMy->info($e);

            // Try delete file also when exception occurred.
            try {
                $this->fileMy->deleteFile($path);
            } catch (\Exception $e) {
                $this->loggerMy->info($e);
            }
            return false;
        }
    }

    /**
     * Feed generation function
     *
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    private function generateAndSaveFeed()
    {
        $dirPath = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath();

        // Create dir
        $this->fileMy->createDirectory($dirPath . 'feeds', 0775);

        // Prepare paths
        $pathTempXml = $dirPath . 'feeds/roi_hunter_easy_feed_temp.xml';
        $pathTempCsv = $dirPath . 'feeds/roi_hunter_easy_feed_temp.csv';

        try {
            // Clear and file init XML
            $xmlFile = $this->fileMy->fileOpen($pathTempXml, 'w');
            // Clear and file init CSV
            $csvFile = $this->fileMy->fileOpen($pathTempCsv, 'w');


            // Prepare default store context
            $stores = $this->_storeManager->getStores();
            $this->loggerMy->info('Stores:', $stores);
            $defaultStore = $this->_storeManager->getDefaultStoreView();
            $this->loggerMy->info('DefStoreId: ' . $defaultStore->getId() . '. DefStoreName: ' . $defaultStore->getName());
            $this->_storeManager->setCurrentStore($defaultStore);

            // Load products and prepare time measuring
            $total_time_start = microtime(true);
            $time_start = microtime(true);
            $productArray = $this->getProductCollection($defaultStore);
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            $this->loggerMy->info('getProductCollection count: ' . count($productArray) . '. Execution time: '
                . $execution_time);

            // Init Xml Writer
            $xmlWriter = new XMLWriter();
            $this->initXmlWriter($xmlWriter, $defaultStore);
            // Init Csv writing
            $this->initCsvWriting($csvFile);


            // Cycle all products
            $this->count = 0;
            foreach ($productArray as $product) {
                switch ($product->getTypeId()) {
                    case 'downloadable':
                        if ($product->getPrice() <= 0) {
                            break;
                        }
//                      Else same processing as simple product
                    case 'simple':
                        $this->writeSimpleProductXml($product, $xmlWriter);

                        // Csv simple product write
                        $this->writeSimpleProductCsv($product, $csvFile);
                        break;
                    case 'configurable':
                        $this->writeConfigurableProductXml($product, $xmlWriter);

                        // Csv configurable product write
                        $this->writeConfigurableProductCsv($product, $csvFile);
                        break;
                }

                $this->count++;
                if ($this->count >= 256) {
                    $this->count = 0;

                    // After each 256 products flush memory to file.
                    $this->fileMy->fileWrite($xmlFile, $xmlWriter->flush());
                    $this->fileMy->fileFlush($xmlFile);
                }
            }

            $xmlWriter->endElement();
            $xmlWriter->endElement();
            $xmlWriter->endDocument();

            // Finish XML writing
            $this->fileMy->fileWrite($xmlFile, $xmlWriter->flush());
            $this->fileMy->fileFlush($xmlFile);
            // Finish CSV writing
            $this->fileMy->fileClose($csvFile);

            $pathFinalXml = $dirPath . 'feeds/roi_hunter_easy_feed_final.xml';
            $pathFinalCsv = $dirPath . 'feeds/roi_hunter_easy_feed_final.csv';
            if (rename($pathTempXml, $pathFinalXml) && rename($pathTempCsv, $pathFinalCsv)) {
                $this->loggerMy->info('Created feeds renamed successfully.');
            } else {
                $this->loggerMy->info('ERROR: Renaming feeds failed.');
            }

            $total_time_end = microtime(true);
            $total_execution_time = ($total_time_end - $total_time_start);
            $this->loggerMy->info('Total execution time: ' . $total_execution_time);
        } catch (\Exception $e) {
            $this->loggerMy->info($e);
            throw $e;
        }
        return true;
    }

    /**
     * @param $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductCollection($store)
    {
        $collection = $this->collectionFactory->create();

        // select necessary attributes
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToSelect('shortDescription');
        $collection->addAttributeToSelect('description');
        $collection->addAttributeToSelect('price');
        $collection->addAttributeToSelect('specialPrice');
        $collection->addAttributeToSelect('finalPrice');
        $collection->addAttributeToSelect('size');
        $collection->addAttributeToSelect('color');
        $collection->addAttributeToSelect('pattern');
        $collection->addAttributeToSelect('image');

        // Allow only visible products
        $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
        $collection->addAttributeToFilter('visibility', ['in' => $this->productVisibility->getVisibleInSiteIds()]);

        // setting correct Product URL
        $collection->addUrlRewrite();
        $collection->addStoreFilter($store);

        $collection->load();
        return $collection;
    }

    /**
     * @param XMLWriter $xmlWriter
     * @param \Magento\Store\Api\Data\StoreInterface $defaultStore
     */
    private function initXmlWriter($xmlWriter, $defaultStore)
    {
        $xmlWriter->openMemory();
        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->setIndent(true);

        $xmlWriter->startElement('rss');
        $xmlWriter->writeAttribute('version', '2.0');
        $xmlWriter->writeAttributeNs('xmlns', 'g', null, 'http://base.google.com/ns/1.0');
        $xmlWriter->startElement('channel');
        $xmlWriter->writeElement('title', 'ROI Hunter Easy - Magento 2 data feed');
        $xmlWriter->writeElement('description', 'Magento 2 data feed used in Google Merchants');
        $xmlWriter->writeElement('date', $this->date->gmtDate());
        $xmlWriter->writeElement('link', $defaultStore->getBaseUrl());
    }

    /**
     * @param resource $csvFile
     */
    private function initCsvWriting($csvFile)
    {
        // CSV headers
        $csvHeader = array(
            'ID',
            'Item title',
            'Final URL',
            'Image URL',
            'Item description',
            'Price',
            'Sale price'
        );
        // write headers to CSV file
        $this->fileMy->filePutCsv($csvFile, $csvHeader);
    }

    /**
     * @param $product
     * @param resource $csvFile
     */
    private function writeSimpleProductCsv($product, $csvFile)
    {
        $productDict = array(
            'ID' => $this->getId($product, null),
            'Item title' => $this->getTitle($product),
            'Final URL' => $this->getProductUrl($product),
            'Image URL' => $this->getImageUrl($product),
            'Item description' => $this->getDescription($product),
            'Price' => $this->getPrice($product, true),
            'Sale price' => $this->getSalePrice($product, true),
        );

        // Write product to file
        $this->fileMy->filePutCsv($csvFile, $productDict);
    }

    /**
     * @param Mixed $product
     * @param resource $csvFile
     */
    private function writeConfigurableProductCsv($product, $csvFile)
    {
        $childProductArray = $product->getTypeInstance()->getUsedProducts($product);
        foreach ($childProductArray as $childProduct) {
            $productDict = array(
                'ID' => $this->getId($product, $childProduct),
                'Item title' => $this->getTitle($product),
                'Final URL' => $this->getProductUrl($product),
                'Image URL' => $this->getImageUrl($childProduct),
                'Item description' => $this->getDescription($product),
                'Price' => $this->getPrice($childProduct, true),
                'Sale price' => $this->getSalePrice($childProduct, true),
            );

            // Write product to file
            $this->fileMy->filePutCsv($csvFile, $productDict);
        }
    }


    /**
     * @param Mixed $product
     * @param XMLWriter $xmlWriter
     */
    private function writeSimpleProductXml($product, $xmlWriter)
    {
        $xmlWriter->startElement('item');

        $xmlWriter->writeElement('g:id', $this->getId($product, null));
        $xmlWriter->writeElement('g:display_ads_id', $this->getDisplayAdsId($product, null));

        // process common attributes
        $this->writeParentProductAttributesXml($product, $xmlWriter);
        // process advanced attributes
        $this->writeChildProductAttributes($product, $xmlWriter);
        // categories
        $catCollection = $this->getProductTypes($product);
        $this->writeProductTypesXml($catCollection, $xmlWriter);

        $xmlWriter->endElement();
    }

    /**
     * @param Mixed $product
     * @param XMLWriter $xmlWriter
     */
    private function writeParentProductAttributesXml($product, $xmlWriter)
    {
        $xmlWriter->writeElement('g:title', $this->getTitle($product));
        $xmlWriter->writeElement('g:description', $this->getDescription($product));
        $xmlWriter->writeElement('g:link', $this->getProductUrl($product));

        // replaced getAttributeText with safer option
        $attributeCode = 'manufacturer';
        if ($product->getData($attributeCode) !== null) {
            $xmlWriter->writeElement('g:brand', $product->getAttributeText($attributeCode));
        }

        $xmlWriter->writeElement('g:condition', 'new');
    }

    /**
     * @param Mixed $product
     * @param XMLWriter $xmlWriter
     */
    private function writeChildProductAttributes($product, $xmlWriter)
    {
        $xmlWriter->writeElement('g:image_link', $this->getImageUrl($product));

        $xmlWriter->writeElement('g:mpn', $product->getSku());
        if (strlen($product->getEan()) > 7) {
            $xmlWriter->writeElement('g:gtin', $product->getEan());
        }

        $xmlWriter->writeElement('g:price', $this->getPrice($product));
        $xmlWriter->writeElement('g:sale_price', $this->getSalePrice($product));
        // replaced getAttributeText with safer option
        $attributeCode = 'size';
        if ($product->getData($attributeCode) !== null) {
            $xmlWriter->writeElement('g:size', $product->getAttributeText($attributeCode));
        }
        // replaced getAttributeText with safer option
        $attributeCode = 'color';
        if ($product->getData($attributeCode) !== null) {
            $xmlWriter->writeElement('g:color', $product->getAttributeText($attributeCode));
        }
        $xmlWriter->writeElement('g:availability', $this->doIsInStock($product));
    }


    /**
     * @param Mixed $catCollection
     * @param XMLWriter $xmlWriter
     */
    private function writeProductTypesXml($catCollection, $xmlWriter)
    {
        /** @var Mixed $category */
        foreach ($catCollection as $category) {
            $xmlWriter->writeElement('g:product_type', $category->getName());
        }
    }

    /**
     * @param Mixed $product
     * @param XMLWriter $xmlWriter
     */
    private function writeConfigurableProductXml($product, $xmlWriter)
    {
        $catCollection = $this->getProductTypes($product);

        $childProductArray = $product->getTypeInstance()->getUsedProducts($product);

        foreach ($childProductArray as $childProduct) {
            $xmlWriter->startElement('item');

            // ID belongs to the child product's ID to make this product unique
            $xmlWriter->writeElement('g:id', $this->getId($product, $childProduct));
            $xmlWriter->writeElement('g:item_group_id', $this->getItemGroupId($product));
            $xmlWriter->writeElement('g:display_ads_id', $this->getDisplayAdsId($product, $childProduct));

            // process common attributes
            $this->writeParentProductAttributesXml($product, $xmlWriter);
            // process advanced attributes
            $this->writeChildProductAttributes($childProduct, $xmlWriter);
            // categories
            $this->writeProductTypesXml($catCollection, $xmlWriter);

            $xmlWriter->endElement();
            $this->count++;
        }
    }


    /**
     * @param Mixed $product
     * @param Mixed $childProduct
     * @return string id
     */
    function getId($product, $childProduct = null)
    {
        if ($childProduct) {
            return 'mag_' . $product->getId() . '_' . $childProduct->getId();
        } else {
            return 'mag_' . $product->getId();
        }
    }

    /**
     * @param Mixed $product
     * @return string item_group_id
     */
    function getItemGroupId($product)
    {
        return 'mag_' . $product->getId();
    }

    /**
     * @param Mixed $product
     * @param Mixed $childProduct
     * @return string display_ads_id
     */
    function getDisplayAdsId($product, $childProduct = null)
    {
        if ($childProduct) {
            return 'mag_' . $product->getId() . '_' . $childProduct->getId();
        } else {
            return 'mag_' . $product->getId();
        }
    }

    /**
     * @param Mixed $product
     * @return string title
     */
    function getTitle($product)
    {
        return $product->getName();
    }

    /**
     * @param Mixed $product
     * @return mixed
     */
    private function getDescription($product)
    {
        $description = $product->getShortDescription();
        if (!$description) {
            $description = $product->getDescription();
        }
        return ($description);
    }

    /**
     * @param Mixed $product
     * @return string price
     */
    function getProductUrl($product)
    {
        return $product->getProductUrl();
    }

    /**
     * @param Mixed $product
     * @return string
     */
    private function getImageUrl($product)
    {
        $storeUrl = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $imageUrl = $product->getImage();
        return $storeUrl . 'catalog/product' . $imageUrl;

//      TODO magento 1 has different image loading, but still didn't work completely
    }

    /**
     * @return string currency code
     */
    function getCurrency()
    {
        return $currentCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
    }

    /**
     * @param Mixed $product
     * @param bool $withCurrency
     * @return string price
     */
    function getPrice($product, $withCurrency = false)
    {
        $price = $product->getPrice();
        if ($withCurrency) {
            $price = $price . ' ' . $this->getCurrency();
        }
        return $price;
    }

    /**
     * @param Mixed $product
     * @param bool $withCurrency
     * @return string salePrice
     */
    function getSalePrice($product, $withCurrency = false)
    {
        $salePrice = $product->getFinalPrice();
        if ($salePrice && $withCurrency) {
            $salePrice = $salePrice . ' ' . $this->getCurrency();
        }
        return $salePrice;
    }


    /**
     * @param Mixed $_product
     * @return mixed
     */
    private function getProductTypes($_product)
    {
        // SELECT name FROM category
        // if I want to load more attributes, I need to select them first
        // loading and selecting is processor intensive! Selecting more attributes will result in longer delay!
        return $_product->getCategoryCollection()->addAttributeToSelect('name')->load();
    }

    /**
     * @param Mixed $_product
     * @return string
     */
    private function doIsInStock($_product)
    {
        $_stockItem = $this->stockRegistry->getStockItem(
            $_product->getId(),
            $_product->getStore()->getWebsiteId()
        );

        if ($_stockItem && $_stockItem->getIsInStock()) {
            $stockVal = 'in stock';
        } else {
            $stockVal = 'out of stock';
        }
        return $stockVal;
    }

}
