<?php

namespace BusinessFactory\RoiHunterEasy\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Zend\Http\Client;
use Zend\Http\Headers;
use Zend\Http\Request;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use BusinessFactory\RoiHunterEasy\Logger\Logger;

class Reset extends Action
{
    protected $resultJsonFactory;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param MainItemFactory $mainItemFactory
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        Logger $logger,
        MainItemFactory $mainItemFactory,
        JsonFactory $resultJsonFactory
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->loggerMy = $logger;
        $this->mainItemFactory = $mainItemFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $this->loggerMy->info(__METHOD__ . ' invoked');

        /** @var \Magento\Framework\Controller\Result\Json $response */
        $response = $this->resultJsonFactory->create();

        try {
            // If no items -> success
            $mainItemCollection = $this->mainItemFactory->create()->getCollection();
            if ($mainItemCollection->count() <= 0) {
                return $response->setData('Reset completed.');
            } else {
                // Delete all plugin data
                $accessToken = $mainItemCollection->getLastItem()->getAccessToken();

                // Send information request
                $httpHeaders = new Headers();
                $httpHeaders->addHeaders([
                    'X-Authorization' => '' . $accessToken
                ]);

                foreach ($mainItemCollection as $mainItem) {
                    $mainItem->delete();
                }

                $request = new Request();
                $request->setHeaders($httpHeaders);
                $request->setUri('https://goostav.roihunter.com/customers/disconnect');
                $request->setMethod(Request::METHOD_GET);

                $client = new Client();
                $options = [
                    'adapter' => 'Zend\Http\Client\Adapter\Curl',
                    'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
                    'maxredirects' => 5,
                    'timeout' => 60
                ];
                $client->setOptions($options);

                $result = $client->send($request);
                $this->loggerMy->info($result);
                if ($result->getStatusCode() >= 200 && $result->getStatusCode() < 300) {
                    return $response->setData('Reset completed.');
                } else {
                    return $response->setData('Remote reset failed. However new registration should be alright.');
                }
            }
        } catch (\Exception $e) {
            $this->loggerMy->info($e);
            return $response->setData('Reset failed. Please check logs and contact support at easy@roihunter.com.');
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('BusinessFactory_RoiHunterEasy::roi_hunter_easy');
    }
}
