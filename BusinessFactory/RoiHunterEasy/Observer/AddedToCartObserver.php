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
            // get product
            $product = $observer->getEvent()->getData('product');
            // set product as session data
            $product_remarketing_data = [
                'pagetype' => 'cart',
                'id' => $product->getSku(),
                'price' => $product->getFinalPrice()
            ];
            $product_remarketing_json = json_encode($product_remarketing_data);
            $product_remarketing_base64 = base64_encode($product_remarketing_json);
            $this->customerSession->setMyValue($product_remarketing_base64);
        } catch (\Exception $e) {
            $this->loggerMy->info($e);
        }
    }
}
