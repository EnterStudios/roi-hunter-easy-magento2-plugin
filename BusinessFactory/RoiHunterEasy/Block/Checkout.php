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
    protected $conversionCurrency;

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


    /**
     * Render Remarketing tracking scripts
     *
     * @return string
     */
    protected function _toHtml()
    {
        try {
            // find out if session was set
            $checkoutRemarketingBase64 = $this->customerSession->getMyValue();
            $checkoutRemarketingJson = base64_decode($checkoutRemarketingBase64);
            $checkoutRemarketing = json_decode($checkoutRemarketingJson, true);

            if ($checkoutRemarketing && array_key_exists('pagetype', $checkoutRemarketing)) {
                $pageType = $checkoutRemarketing['pagetype'];

                // render template with remarketing tag
                if ($pageType === 'checkout') {
                    $this->prodId = $checkoutRemarketing['ids'];
                    $this->prodPrice = $checkoutRemarketing['price'];
                    $this->conversionCurrency = $checkoutRemarketing['currency'];

                    // unset session value
                    $this->customerSession->unsMyValue();

                    return parent::_toHtml();
                }
            }
        } catch (\Exception $exception) {
            $this->logger->info(__METHOD__ . ' exception.');
            $this->logger->info($exception);
        }

        return '';
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
                $this->logger->info('Conversion ID not found during ' . __METHOD__);
                return null;
            }
        } catch (\Exception $exception) {
            $this->logger->info(__METHOD__ . ' exception.');
            $this->logger->info($exception);
            return null;
        }
    }

    public function getProdId()
    {
        if (!$this->prodId) {
            $this->logger->info('Product ID not found during ' . __METHOD__);
            return null;
        }
        return json_encode($this->prodId);
    }

    public function getProdPrice()
    {
        if (!$this->prodPrice) {
            $this->logger->info('Product price not found during ' . __METHOD__);
            return null;
        }
        return $this->prodPrice;
    }

    public function getConversionLabel()
    {
        try {
            $collection = $this->mainItemFactory->create()->getCollection();
            if (($mainItem = ($collection->getLastItem())) === null) {
                $this->logger->info('Table record not found during ' . __METHOD__);
                return null;
            }
            if (($conversionLabel = $mainItem->getConversionLabel()) === null) {
                $this->logger->info('Conversion Label not found during ' . __METHOD__);
                return null;
            }
            return $conversionLabel;
        } catch (\Exception $exception) {
            $this->logger->info(__METHOD__ . ' exception');
            $this->logger->info($exception);
            return null;
        }
    }

    public function getConversionCurrency()
    {
        if (!$this->conversionCurrency) {
            $this->logger->info('Conversion currency not found during ' . __METHOD__);
            return null;
        }
        return $this->conversionCurrency;
    }

}
