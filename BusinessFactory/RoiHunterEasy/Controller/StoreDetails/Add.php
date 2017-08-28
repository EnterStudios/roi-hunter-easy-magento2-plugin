<?php

namespace BusinessFactory\RoiHunterEasy\Controller\StoreDetails;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

class Add extends Action
{

    /**
     * @var  \Magento\Framework\View\Result\Page
     */
    private $jsonResultFactory;
    private $storeManager;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    private $filesystem;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    public function __construct(
        Context $context,
        Logger $logger,
        JsonFactory $jsonResultFactory,
        StoreManagerInterface $storeManager,
        MainItemFactory $mainItemFactory,
        Filesystem $filesystem
    )
    {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->storeManager = $storeManager;
        $this->loggerMy = $logger;
        $this->mainItemFactory = $mainItemFactory;
        $this->filesystem = $filesystem;

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
     * Return RoiHunterEasy table data.
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     */
    private function processGET($resultPage)
    {
        try {
            $authorizationHeader = $this->getRequest()->getHeader('X-Authorization');

            // Check if data exists.
            $mainItemCollection = $this->mainItemFactory->create()->getCollection();
            if ($mainItemCollection->count() <= 0) {
                $resultPage->setData('Entry not exist.');
                $resultPage->setHttpResponseCode(404);
                return;
            } else {
                $dataEntity = $mainItemCollection->getLastItem();

                // Check if we can access data.
                if ($dataEntity->getClientToken() == null || $dataEntity->getClientToken() !== $authorizationHeader) {
                    $resultPage->setData('Not authorized');
                    $resultPage->setHttpResponseCode(403);
                    return;
                }

                // If everything ok, return data
                $resultPage->setData($mainItemCollection->getLastItem()->getData());
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($this->getRequest());
            $resultPage->setHttpResponseCode(500);
        }
    }

    /**
     * Method should handle call after customer successful connection on goostav.
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     */
    private function processPOST($resultPage)
    {
        $request = $this->getRequest();
        try {
            // Get request params
            $requestData = $request->getParams();
            $this->loggerMy->info('Process add request with data: ', $requestData);

            $authorizationHeader = $request->getHeader('X-Authorization');

            // Prepare database item. If table empty, then create new item.
            $mainItemCollection = $this->mainItemFactory->create()->getCollection();
            if ($mainItemCollection->count() <= 0) {
                $dataEntity = $this->mainItemFactory->create();
                $dataEntity->setDescription('New');
            } else {
                $dataEntity = $mainItemCollection->getLastItem();
                $dataEntity->setDescription('Updated');

                // If data already exist check for client token.
                if ($dataEntity->getClientToken() != null && $dataEntity->getClientToken() !== $authorizationHeader) {
                    $resultPage->setData('Not authorized');
                    $resultPage->setHttpResponseCode(403);
                    return;
                }
            }

            // Save clientToken only if not exist
            if ($dataEntity->getClientToken() == null) {
                $client_token = $request->getParam('client_token');
                if ($client_token == null) {
                    $resultPage->setData('Missing client token.');
                    $resultPage->setHttpResponseCode(422);
                    return;
                } else {
                    $dataEntity->setClientToken($client_token);
                }
            }

            // Save AccessToken only if not exist
            if ($dataEntity->getAccessToken() == null) {
                $goostav_access_token = $request->getParam('access_token');
                if ($goostav_access_token == null) {
                    $resultPage->setData('Missing tokens.');
                    $resultPage->setHttpResponseCode(422);
                    return;
                } else {
                    $dataEntity->setAccessToken($goostav_access_token);
                }
            }

            // Save status and errors if something failed
            $status = $request->getParam('status');
            if ($status != null) {
                $dataEntity->setStatus($status);
            }
            $errors = $request->getParam('errors');
            if ($errors != null) {
                $dataEntity->setErrors($errors);
            }

            // Save customer id
            $customerId = $request->getParam('id');
            if ($customerId != null) {
                $dataEntity->setCustomerId($customerId);
            }
            // Save conversion id
            $conversionId = $request->getParam('conversion_id');
            if ($conversionId != null) {
                $dataEntity->setConversionId($conversionId);
            }

            // Set managed merchants
            $managedMerchants = $request->getParam('managed_merchants');
            if ($managedMerchants !== null) {
                $dataEntity->setManagedMerchants($managedMerchants === 'true');
            }
            // Set adult content
            $adultOriented = $request->getParam('adult_oriented');
            if ($adultOriented !== null) {
                $dataEntity->setAdultOriented($adultOriented === 'true');
            }
            // Set conversion label
            $conversionLabel = $request->getParam('conversion_label');
            if ($conversionLabel !== null) {
                $dataEntity->setConversionLabel($conversionLabel);
            }


            // Persist data
            $dataEntity->save();

            // Create verification file
            $filename = $request->getParam('site_verification_token');
            $this->createVerificationFile($filename);

            // Return response
            $resultPage->setData([
                'data' => $requestData
            ]);
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($request);
            $resultPage->setHttpResponseCode(500);
        }
    }

    /**
     * Create verification file.
     * @param $filename
     */
    private function createVerificationFile($filename)
    {
        try {
            if ($filename != null) {
                $path = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)->getAbsolutePath() . $filename;
                $content = 'google-site-verification: ' . $filename;

                $fp = fopen($path, 'wb');
                fwrite($fp, $content);
                fclose($fp);

                $path = $this->filesystem->getDirectoryWrite(DirectoryList::PUB)->getAbsolutePath() . $filename;
                $fp = fopen($path, 'wb');
                fwrite($fp, $content);
                fclose($fp);
            } else {
                $this->loggerMy->info('ERROR: Cannot create verification file. Missing filename.');
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
        }
    }
}
