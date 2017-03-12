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
            $productIds = [];
            $parentItemIdToProductIdMap = [];
            $configurableChildItems = [];

            $this->collection->addFieldToFilter('entity_id', ['in' => $orderIds]);

            /** @var $order \Magento\Sales\Model\Order */
            foreach ($this->collection as $order) {
                $conversionValue += $order->getBaseGrandTotal();

                // returns all order items
                // configurable items are separated to two items - one simple with parent_item_id and one configurable with item_id
                $items = $order->getAllItems();
                foreach ($items as $item) {
                    $parent_item_id = $item->getParentItemId();
                    $product_type = $item->getProductType();

                    if ($parent_item_id === null) {
                        if ($product_type === "simple" || $product_type === "downloadable") {
                            // simple product - write directly to the result IDs array
                            array_push($productIds, "mag_" . $item->getProductId());
                        } else if ($product_type === "configurable") {
                            // configurable parent product
                            // create map of parent IDS : parent objects
                            $parentItemIdToProductIdMap[$item['item_id']] = $item['product_id'];
                        } else {
                            $this->loggerMy->info("Unknown product type: " . $product_type);
                        }
                    } else {
                        // configurable child product
                        array_push($configurableChildItems, $item);
                    }
                }
            }

            // iterate over children items a find parent item in the map
            foreach ($configurableChildItems as $item) {
                $id = "mag_" . $parentItemIdToProductIdMap[$item["parent_item_id"]] . "_" . $item["product_id"];
                array_push($productIds, $id);
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
        } catch (\Exception $e) {
            $this->loggerMy->info($e);
            return $this;
        }
    }
}
