<?php
/**
 * Copyright © 2016 MagePal. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace BusinessFactory\RoiHunterEasy\Observer;

use Magento\Customer\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use BusinessFactory\RoiHunterEasy\Logger\Logger;

class AddedToCartObserver implements ObserverInterface
{
    private $loggerMy;

    protected $customerSession;
    /**
     * @var LayoutInterface
     */
    protected $_layout;

    public function __construct(
        Logger $logger,
        Session $customerSession,
        LayoutInterface $layout
    )
    {
        //Observer initialization code...
        //You can use dependency injection to get any class this observer may need.
        $this->loggerMy = $logger;
        $this->_layout = $layout;
        $this->customerSession = $customerSession;
    }

    public function execute(Observer $observer)
    {
        try {
            // get product and variants
//            $product = $observer->getEvent()->getData('product');
            $quoteItem = $observer->getEvent()->getData('quote_item');
            $quoteItemVariant = null;

            if (count($quoteItem->getChildren()) > 0) {
                $tempArray = $quoteItem->getChildren();
                $quoteItemVariant = reset($tempArray);
            }

//            $this->loggerMy->debug("QuoteItem data:", $quoteItemVariant->getData());
//            $this->loggerMy->debug("Event data:", $observer->getEvent()->getData());
//            $this->loggerMy->debug("quote methods", get_class_methods($quoteItem));

            if ($quoteItem->getProductType() === "configurable" && $quoteItemVariant !== null) {
                $id = "mag_" . $quoteItem->getProductId() . "_" . $quoteItemVariant->getProductId();
            } else {
                $id = "mag_" . $quoteItem->getProductId();
            }

            // set product as session data
            $product_remarketing_data = [
                'pagetype' => 'cart',
                'id' => $id,
                'price' => $quoteItem->getProduct()->getFinalPrice()
            ];
            $product_remarketing_json = json_encode($product_remarketing_data);
            $product_remarketing_base64 = base64_encode($product_remarketing_json);
            $this->customerSession->setMyValue($product_remarketing_base64);
        } catch (\Exception $e) {
            $this->loggerMy->info($e);
        }
    }
}
