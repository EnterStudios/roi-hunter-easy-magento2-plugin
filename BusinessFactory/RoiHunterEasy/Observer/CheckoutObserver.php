<?php
/**
 * Copyright © 2016 MagePal. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace BusinessFactory\RoiHunterEasy\Observer;

use Exception;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use BusinessFactory\RoiHunterEasy\Logger\Logger;

class CheckoutObserver implements ObserverInterface
{
    private $loggerMy;
    /**
     * @var LayoutInterface
     */
    protected $_layout;

    /**
     * @var Collection
     */
    private $collection;

    private $productFactory;

    protected $customerSession;

    public function __construct(
        Logger $logger,
        LayoutInterface $layout,
        Collection $collection,
        Session $customerSession,
        ProductFactory $_productFactory
    )
    {
        //Observer initialization code...
        //You can use dependency injection to get any class this observer may need.
        $this->loggerMy = $logger;
        $this->_layout = $layout;
        $this->collection = $collection;
        $this->productFactory = $_productFactory;
        $this->customerSession = $customerSession;
    }

    public function execute(Observer $observer)
    {
        try {
            $orderIds = $observer->getEvent()->getOrderIds();

            if (!$orderIds || !is_array($orderIds)) {
                return $this;
            }

            $conversionValue = 0;
            $productIds = [];

            $this->collection->addFieldToFilter('entity_id', ['in' => $orderIds]);

            /** @var $order \Magento\Sales\Model\Order */
            foreach ($this->collection as $order) {
                $conversionValue += $order->getBaseGrandTotal();

                $products = $order->getAllVisibleItems();
                foreach ($products as $product) {
                    array_push($productIds, $product->getSku());
                }
            }

            $checkout_remarketing_data = [
                'pagetype' => 'checkout',
                'ids' => $productIds,
                'price' => $conversionValue
            ];
            $checkout_remarketing_json = json_encode($checkout_remarketing_data);
            $checkout_remarketing_base64 = base64_encode($checkout_remarketing_json);
            $this->customerSession->setMyValue($checkout_remarketing_base64);

            return $this;
        } catch (Exception $e) {
            $this->loggerMy->info($e);
            return $this;
        }
    }
}
