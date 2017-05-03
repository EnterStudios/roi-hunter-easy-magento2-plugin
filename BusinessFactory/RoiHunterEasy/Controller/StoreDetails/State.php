<?php
namespace BusinessFactory\RoiHunterEasy\Controller\StoreDetails;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

class State extends Action
{

    /** @var JsonFactory */
    private $jsonResultFactory;
    private $storeManager;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        StoreManagerInterface $storeManager,
        Logger $logger,
        MainItemFactory $mainItemFactory
    )
    {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->storeManager = $storeManager;
        $this->loggerMy = $logger;
        $this->mainItemFactory = $mainItemFactory;

        parent::__construct($context);
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $resultPage = $this->jsonResultFactory->create();

        $resultPage->setHeader('Access-Control-Allow-Origin', '*', true);
        $resultPage->setHeader('Access-Control-Allow-Methods', 'OPTIONS,GET,POST', true);
        $resultPage->setHeader('Access-Control-Max-Age', '60', true);
        $resultPage->setHeader('Access-Control-Allow-Headers', 'X-Authorization', true);

        if ($this->getRequest()->getMethod() === 'GET') {
            $this->processGET($resultPage);
        } elseif ($this->getRequest()->getMethod() === 'POST') {
            $this->processPOST($resultPage);
        }

        return $resultPage;
    }

    /**
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     */
    private function processGET($resultPage)
    {
        try {
            $mainItemCollection = $this->mainItemFactory->create()->getCollection();
            // If table empty, then create new item.
            if ($mainItemCollection->count() <= 0) {
                $resultPage->setData("Entry not exist.");
                $resultPage->setHttpResponseCode(404);
            } else {
                $resultPage->setData($mainItemCollection->getLastItem()->getCreationState());
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($this->getRequest());
            $resultPage->setHttpResponseCode(500);
        }
    }

    /**
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     */
    private function processPOST($resultPage)
    {
        try {
            // Get request params
            $requestData = $this->getRequest()->getParams();
            $this->loggerMy->info('Process POST state request with data: ', $requestData);

            $authorizationHeader = $this->getRequest()->getHeader('X-Authorization');
            $newCreationState = $this->getRequest()->getParam('new_state');

            if ($newCreationState === null) {
                $resultPage->setData("Missing parameter.");
                $resultPage->setHttpResponseCode(422);
                return;
            } else {
//             Prepare database item. If table empty, then create new item.
                $mainItemCollection = $this->mainItemFactory->create()->getCollection();
                if ($mainItemCollection->count() <= 0) {
                    $dataEntity = $this->mainItemFactory->create();
                    $dataEntity->setDescription("New");
                } else {
                    $dataEntity = $mainItemCollection->getLastItem();
                    $dataEntity->setDescription("Updated");

//                    If data already exist check for client token.
                    if ($dataEntity->getClientToken() !== null &&
                        $dataEntity->getClientToken() !== $authorizationHeader
                    ) {
                        $resultPage->setData("Not authorized");
                        $resultPage->setHttpResponseCode(403);
                        return;
                    }
                }

                // Save data and send response success
                $dataEntity->setCreationState($newCreationState);
                $dataEntity->save();

                $resultPage->setData([
                    "data" => $requestData
                ]);
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($this->getRequest());
            $resultPage->setHttpResponseCode(500);
        }
    }
}
