<?php

namespace BusinessFactory\RoiHunterEasy\Block\Product\View;

use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\View;
use Magento\Catalog\Helper\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface;

class Analytics extends View
{

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    private $logger;

    public function __construct(
        Context $context,
        EncoderInterface $urlEncoder,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        StringUtils $string,
        Product $productHelper,
        ConfigInterface $productTypeConfig,
        FormatInterface $localeFormat,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        Logger $logger,
        MainItemFactory $mainItemFactory,
        array $data = []
    )
    {
        $this->logger = $logger;
        $this->mainItemFactory = $mainItemFactory;

        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );
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
