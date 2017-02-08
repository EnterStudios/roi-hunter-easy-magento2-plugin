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
class Checkout extends Template
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
            $checkout_remarketing_base64 = $this->customerSession->getMyValue();

            $checkout_remarketing_json = base64_decode($checkout_remarketing_base64);
            $checkout_remarketing = json_decode($checkout_remarketing_json, true);

            if ($checkout_remarketing && array_key_exists('pagetype', $checkout_remarketing)) {
                $pagetype = $checkout_remarketing['pagetype'];

                // render template with remarketing tag
                if ($pagetype === "checkout" && $checkout_remarketing) {
                    $this->prodId = json_encode($checkout_remarketing['ids']);
                    $this->prodPrice = $checkout_remarketing['price'];

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
