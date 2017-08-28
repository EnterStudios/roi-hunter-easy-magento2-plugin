<?php

namespace BusinessFactory\RoiHunterEasy\Controller\StoreDetails;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use BusinessFactory\RoiHunterEasy\Model\Cron;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Preview extends Action
{
    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    /**
     * Custom cron instance for the initial feed
     * @var Cron
     */
    private $cron;

    /** @var JsonFactory */
    private $jsonResultFactory;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    public function __construct(
        Context $context,
        Logger $logger,
        Cron $cron,
        JsonFactory $jsonResultFactory,
        MainItemFactory $mainItemFactory
    )
    {
        $this->loggerMy = $logger;
        $this->cron = $cron;
        $this->jsonResultFactory = $jsonResultFactory;
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
        $resultPage->setHeader('Access-Control-Allow-Methods', 'OPTIONS,GET', true);
        $resultPage->setHeader('Access-Control-Max-Age', '60', true);
        $resultPage->setHeader('Access-Control-Allow-Headers', 'X-Authorization', true);

        if ($this->getRequest()->getMethod() === 'GET') {
            $this->processGET($resultPage);
        } elseif ($this->getRequest()->getMethod() === 'OPTIONS') {

        } else {
            $resultPage->setHttpResponseCode(400);
        }

        return $resultPage;
    }

    /**
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     */
    private function processGET($resultPage)
    {
        try {
            $limit = $this->getRequest()->getParam('limit');
            if (!isset($limit) || trim($limit) === '') {
                $limit = 3;
            }

            $this->loggerMy->info('Preview generating started manually.');
            $resultPreviewArray = $this->cron->createView($limit);
            if ($resultPreviewArray == true) {
                $resultPage->setHeader('Content-type','application/json',true);
                $resultPage->setData($resultPreviewArray);
            } else {
                $resultPage->setData('Preview not generated. See logs for more info.');
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($this->getRequest());
            $resultPage->setHttpResponseCode(500);
            $resultPage->setData('Preview generation failed.');
        }
    }
}
