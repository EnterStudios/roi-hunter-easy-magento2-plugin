<?php
/**
 * Copyright © 2016 MagePal. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace BusinessFactory\RoiHunterEasy\Observer;

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
            $currency = null;
            $productIds = [];
            $parentItemIdToProductIdMap = [];
            $configurableChildItems = [];

            $this->collection->addFieldToFilter('entity_id', ['in' => $orderIds]);

            /** @var $order \Magento\Sales\Model\Order */
            foreach ($this->collection as $order) {
                $conversionValue += $order->getBaseGrandTotal();
                $currency = $order->getStoreCurrencyCode();

                // returns all order items
                // configurable items are divided into two items - one simple with parent_item_id and one configurable with item_id
                $items = $order->getAllItems();
                foreach ($items as $item) {
                    $parentItemId = $item->getParentItemId();
                    $productType = $item->getProductType();

                    if ($parentItemId === null) {
                        if ($productType === 'simple' || $productType === 'downloadable') {
                            // simple product - write directly to the result IDs array
                            array_push($productIds, 'mag_' . $item->getProductId());
                        } else if ($productType === 'configurable') {
                            // configurable parent product
                            // create map of parent IDS : parent objects
                            $parentItemIdToProductIdMap[$item['item_id']] = $item['product_id'];
                        } else {
                            $this->loggerMy->info('Unknown product type: ' . $productType);
                        }
                    } else {
                        // configurable child product
                        array_push($configurableChildItems, $item);
                    }
                }
            }

            // iterate over children items a find parent item in the map
            foreach ($configurableChildItems as $item) {
                $id = 'mag_' . $parentItemIdToProductIdMap[$item['parent_item_id']] . '_' . $item['product_id'];
                array_push($productIds, $id);
            }

            $checkoutRemarketingData = [
                'pagetype' => 'checkout',
                'ids' => $productIds,
                'price' => $conversionValue,
                'currency' => $currency
            ];

            $this->loggerMy->info('Setting temporary customer session value: ' . json_encode($checkoutRemarketingData));

            $checkoutRemarketingJson = json_encode($checkoutRemarketingData);
            $checkoutRemarketingBase64 = base64_encode($checkoutRemarketingJson);
            $this->customerSession->setMyValue($checkoutRemarketingBase64);

            return $this;
        } catch (\Exception $e) {
            $this->loggerMy->info(__METHOD__ . ' exception.');
            $this->loggerMy->info($e);
            return $this;
        }
    }
}
