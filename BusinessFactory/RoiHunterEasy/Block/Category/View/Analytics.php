<?php

/**
 * Copyright © 2016 Business Factory. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace BusinessFactory\RoiHunterEasy\Block\Category\View;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Catalog\Block\Category\View;
use Magento\Catalog\Helper\Category;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;

/**
 * Google Tag Manager Page Block
 */
class Analytics extends View
{

    private $logger;
    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    /**
     * @param Context $context
     * @param Resolver $layerResolver
     * @param Registry $registry
     * @param Category $categoryHelper
     * @param Logger $logger
     * @param MainItemFactory $mainItemFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Resolver $layerResolver,
        Registry $registry,
        Category $categoryHelper,
        Logger $logger,
        MainItemFactory $mainItemFactory,
        array $data = []
    )
    {
        $this->logger = $logger;
        $this->mainItemFactory = $mainItemFactory;

        parent::__construct(
            $context,
            $layerResolver,
            $registry,
            $categoryHelper,
            $data
        );
    }

    public function getProductIds()
    {
        try {
            $products = $this->getCurrentCategory()
                ->getProductCollection()
                ->addAttributeToSelect('id')
                ->load();
//                ->addAttributeToSelect('sku')


            // slice array not to list all the products
            $limit = 10;
            $count = 0;
            $productIds = [];
            foreach ($products as $product) {
                array_push($productIds, 'mag_' . $product->getId());
                if (count($productIds) >= $limit) {
                    break;
                }
                $count++;
            }
            return json_encode($productIds);
        } catch (\Exception $exception) {
            $this->logger->info(__METHOD__ . " exception.");
            $this->logger->info($exception);
            return null;
        }
    }

    public function getConversionId()
    {
        try {
            // load the data from the DB
            $collection = $this->mainItemFactory->create()->getCollection();

            $conversionId = $collection->getLastItem()->getConversionId();

            if ($conversionId != null) {
                return $conversionId;
            } else {
                $this->logger->info("Conversion ID not found during " . __METHOD__);
                return null;
            }
        } catch (\Exception $exception) {
            $this->logger->info(__METHOD__ . " exception.");
            $this->logger->info($exception);
            return null;
        }
    }
}
