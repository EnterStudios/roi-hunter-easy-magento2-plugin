<?php

/**
 * Copyright © 2016 Business Factory. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace BusinessFactory\RoiHunterEasy\Block;

use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use BusinessFactory\RoiHunterEasy\Logger\Logger;

/**
 * Google Tag Manager Page Block
 */
class AddedToCart extends Template
{

    private $logger;

    protected $customerSession;
    protected $prodId;
    protected $prodPrice;
    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param Session $customerSession
     * @param MainItemFactory $mainItemFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Logger $logger,
        Session $customerSession,
        MainItemFactory $mainItemFactory,
        array $data = []
    )
    {
        $this->logger = $logger;
        $this->mainItemFactory = $mainItemFactory;
        $this->customerSession = $customerSession;

        parent::__construct($context, $data);
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

    public function getProdId()
    {
        if (!$this->prodId) {
            $this->logger->info("Product ID not found during " . __METHOD__);
        }
        return $this->prodId;
    }

    public function getProdPrice()
    {
        if (!$this->prodPrice) {
            $this->logger->info("Product price not found during " . __METHOD__);
        }
        return $this->prodPrice;
    }

    /**
     * Render GA tracking scripts
     *
     * @return string
     */
    protected function _toHtml()
    {
        try {
            // find out if session was set
            $product_remarketing_base64 = $this->customerSession->getMyValue();

            $product_remarketing_json = base64_decode($product_remarketing_base64);
            $product_remarketing = json_decode($product_remarketing_json, true);

            if ($product_remarketing && array_key_exists('pagetype', $product_remarketing)) {
                $pagetype = $product_remarketing['pagetype'];

                // render template with remarketing tag
                if ($pagetype === "cart" && $product_remarketing) {
                    $this->prodId = $product_remarketing['id'];
                    $this->prodPrice = $product_remarketing['price'];

                    // unset session value
                    $this->customerSession->unsMyValue();

                    return parent::_toHtml();
                }
            }
        } catch (\Exception $exception) {
            $this->logger->info(__METHOD__ . " exception.");
            $this->logger->info($exception);
        }

        return '';
    }
}
