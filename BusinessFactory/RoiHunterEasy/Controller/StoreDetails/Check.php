<?php
namespace BusinessFactory\RoiHunterEasy\Controller\StoreDetails;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Check extends Action
{

    /** @var JsonFactory */
    private $jsonResultFactory;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;


    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        Logger $logger
    )
    {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->loggerMy = $logger;

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
        $this->loggerMy->info(__METHOD__ . "- Check called.");
        $resultPage = $this->jsonResultFactory->create();

        $resultPage->setHeader('Access-Control-Allow-Origin', '*', true);
        $resultPage->setHeader('Access-Control-Allow-Methods', 'OPTIONS,GET,POST', true);
        $resultPage->setHeader('Access-Control-Max-Age', '60', true);
        $resultPage->setHeader('Access-Control-Allow-Headers', 'X-Authorization', true);

        $resultPage->setData("rh-easy-active");
        return $resultPage;
    }
}
