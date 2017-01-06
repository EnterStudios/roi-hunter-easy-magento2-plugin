<?php
namespace BusinessFactory\RoiHunterEasy\Model;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Exception;
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

    /** @var \Magento\Framework\Filesystem\Io\File $io * */
    private $ioDir;

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
        \Magento\Framework\Filesystem\Io\File $ioFile
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
        $this->ioDir = $ioFile;
    }

    /**
     * Method start new feed creation process, if not another feed creation process running.
     */
    public function createFeed()
    {
        $this->loggerMy->info(__METHOD__ . " cron");
        $path = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)->getAbsolutePath()
            . "businessFactoryRoiHunterEasyFeedSign";
        try {
            if (!file_exists($path)) {
                // Create file
                $fp = fopen($path, "wb");
                fwrite($fp, "Running");
                fclose($fp);

                // Generate feed
                $this->generateAndSaveFeed();

                // Delete file
                $this->fileMy->deleteFile($path);
                return true;
            } else {
                $this->loggerMy->info("Feed generation already running.");
                return false;
            }
        } catch (Exception $e) {
            $this->loggerMy->info($e);

            // Try delete file also when exception occurred.
            try {
                $this->fileMy->deleteFile($path);
            } catch (Exception $e) {
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
        $this->ioDir->mkdir($dirPath . 'feeds', 0775);

        // Prepare paths
        $pathTemp = $dirPath . "feeds/roi_hunter_easy_feed_temp.xml";
        $pathFinal = $dirPath . "feeds/roi_hunter_easy_feed_final.xml";

        // Clear file
        file_put_contents($pathTemp, "");

        try {
            $xmlWriter = new XMLWriter();
            $xmlWriter->openMemory();
            $xmlWriter->startDocument('1.0', 'UTF-8');
            $xmlWriter->setIndent(true);

            $xmlWriter->startElement('rss');
            $xmlWriter->writeAttribute('version', '2.0');
            $xmlWriter->writeAttributeNs('xmlns', 'g', null, 'http://base.google.com/ns/1.0');
            $xmlWriter->startElement('channel');
            $xmlWriter->writeElement('title', 'ROI Hunter Easy - Magento data feed');
            $xmlWriter->writeElement('description', 'Magento data feed used in Google Merchants');
            $xmlWriter->writeElement('link', $this->_storeManager->getStore()->getBaseUrl());

            $total_time_start = microtime(true);
            $time_start = microtime(true);
            $products = $this->getProductCollection();
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            $this->loggerMy->info('getProductCollection count: ' . count($products) . '. Execution time: '
                . $execution_time);

            $this->count = 0;

            // debug variables
            $limit_enabled = false;
            $simple_products_count = 0;
            $configurable_products_count = 0;
            $simple_products_limit = 2;
            $configurable_products_limit = 1;

            foreach ($products as $_product) {
//              Determine product type. Log: $this->loggerMy->info("Type: " . $_product->getTypeId());
                switch ($_product->getTypeId()) {
                    case 'downloadable':
                        if ($_product->getPrice() <= 0) {
//                          Inform about empty downloadable product: $this->loggerMy->info("Skip this");
                            break;
                        }
//                        Else same processing as simple product
                    case 'simple':
                        if (!$limit_enabled || $simple_products_count < $simple_products_limit) {
                            $this->writeSimpleProduct($_product, $xmlWriter);
                            $simple_products_count++;
                        }
                        break;
                    case 'configurable':
                        if (!$limit_enabled || $configurable_products_count < $configurable_products_limit) {
                            $this->writeConfigurableProduct($_product, $xmlWriter);
                            $configurable_products_count++;
                        }
                        break;
                }
                if ($limit_enabled && $simple_products_count >= $simple_products_limit &&
                    $configurable_products_count >= $configurable_products_limit
                ) {
                    break;
                }

                $this->count++;
                if ($this->count >= 512) {
                    $this->count = 0;
                    // After each 512 products flush memory to file.
                    file_put_contents($pathTemp, $xmlWriter->flush(), FILE_APPEND);
                }
            }

            $xmlWriter->endElement();
            $xmlWriter->endElement();
            $xmlWriter->endDocument();

            // Final memory flush, rename temporary file and feed is done.
            file_put_contents($pathTemp, $xmlWriter->flush(), FILE_APPEND);
            if (rename($pathTemp, $pathFinal)) {
                $this->loggerMy->info("Created feed renamed successful");
            } else {
                $this->loggerMy->info("ERROR: Created feed renamed unsuccessful");
            }

            $total_time_end = microtime(true);
            $total_execution_time = ($total_time_end - $total_time_start);
            $this->loggerMy->info('total execution time: ' . $total_execution_time);
        } catch (Exception $e) {
            $this->loggerMy->info($e);
            throw $e;
        }
        return true;
    }

    /**
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function getProductCollection()
    {
        $collection = $this->collectionFactory->create();

        // select necessary attributes
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToSelect('shortDescription');
        $collection->addAttributeToSelect('description');
        $collection->addAttributeToSelect('price');
        $collection->addAttributeToSelect('specialPrice');
        $collection->addAttributeToSelect('size');
        $collection->addAttributeToSelect('color');
        $collection->addAttributeToSelect('pattern');
        $collection->addAttributeToSelect('image');

        // Allow only visible products
        $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
        $collection->addAttributeToFilter('visibility', ['in' => $this->productVisibility->getVisibleInSiteIds()]);

        $collection->load();
        return $collection;
    }

    /**
     * @param Mixed $_product
     * @param XMLWriter $xmlWriter
     */
    private function writeSimpleProduct($_product, $xmlWriter)
    {
        $xmlWriter->startElement('item');

        // process common attributes
        $this->writeParentProductAttributes($_product, $xmlWriter);
        // process advanced attributes
        $this->writeChildProductAttributes($_product, $xmlWriter);
        // categories
        $catCollection = $this->getProductTypes($_product);
        $this->writeProductTypes($catCollection, $xmlWriter);

        $xmlWriter->endElement();
    }

    /**
     * @param Mixed $_product
     * @param XMLWriter $xmlWriter
     */
    private function writeParentProductAttributes($_product, $xmlWriter)
    {
        $xmlWriter->writeElement('g:title', $_product->getName());
        $xmlWriter->writeElement('g:description', $this->getDescription($_product));
        $xmlWriter->writeElement('g:link', $_product->getProductUrl());
        $xmlWriter->writeElement('g:brand', $_product->getAttributeText('manufacturer'));

        $xmlWriter->writeElement('g:condition', 'new');
        // TODO add more attributes if needed.
//        $xmlWriter->writeElement('g:size_system', 'uk');
//        $xmlWriter->writeElement('g:age_group', 'adult');
//        $xmlWriter->writeElement('g:identifier_exists', 'TRUE');
//        $xmlWriter->writeElement('g:adult', $this->do_is_adult($_product));
    }

//    /**
//     * @param Mixed $_product
//     * @return string
//     */
//    function do_is_adult($_product)
//    {
//        // TODO add decision if needed.
////        switch ($_product->getAttributeText('familysafe')) {
////            case 'No':
////                $isadult = "FALSE";
////            default:
////                $isadult = "TRUE";
////        }
//        return ("FALSE");
//    }

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
     * @return string
     */
    private function getImageUrl($product)
    {
        $storeUrl = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $imageUrl = $product->getImage();
        return $storeUrl . 'catalog/product' . $imageUrl;
    }

    /**
     * @param Mixed $_product
     * @param XMLWriter $xmlWriter
     */
    private function writeChildProductAttributes($_product, $xmlWriter)
    {
        $xmlWriter->writeElement('g:id', $_product->getId());
        $xmlWriter->writeElement('g:image_link', $this->getImageUrl($_product));

//        $this->loggerMy->debug('gtin: ' . $_product->getEan());
        $xmlWriter->writeElement('g:mpn', $_product->getSku());
        $xmlWriter->writeElement('g:display_ads_id', $_product->getSku());
        if (strlen($_product->getEan()) > 7) {
            $xmlWriter->writeElement('g:gtin', $_product->getEan());
        }

        $xmlWriter->writeElement('g:price', $_product->getPrice());
        $xmlWriter->writeElement('g:sale_price', $_product->getSpecialPrice());
        $xmlWriter->writeElement('g:size', $_product->getAttributeText('size'));
        $xmlWriter->writeElement('g:color', $_product->getAttributeText('color'));
        $xmlWriter->writeElement('g:availability', $this->doIsInStock($_product));
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

    /**
     * @param Mixed $catCollection
     * @param XMLWriter $xmlWriter
     */
    private function writeProductTypes($catCollection, $xmlWriter)
    {
        /** @var Mixed $category */
        foreach ($catCollection as $category) {
            $xmlWriter->writeElement('g:product_type', $category->getName());
        }
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
     * @param XMLWriter $xmlWriter
     */
    private function writeConfigurableProduct($_product, $xmlWriter)
    {
        $_childProducts = $_product->getTypeInstance()->getUsedProducts($_product);
        $catCollection = $this->getProductTypes($_product);


        foreach ($_childProducts as $_childProduct) {
            $xmlWriter->startElement('item');

            $xmlWriter->writeElement('g:item_group_id', $_product->getSku());

            // process common attributes
            $this->writeParentProductAttributes($_product, $xmlWriter);
            // process advanced attributes
            $this->writeChildProductAttributes($_childProduct, $xmlWriter);
            // categories
            $this->writeProductTypes($catCollection, $xmlWriter);

            $xmlWriter->endElement();
            $this->count++;
        }
    }
}
