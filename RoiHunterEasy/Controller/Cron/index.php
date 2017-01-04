<?php
namespace BusinessFactory\RoiHunterEasy\Controller\Cron;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use BusinessFactory\RoiHunterEasy\Model\Cron;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;

class Index extends Action
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
            $authorizationHeader = $this->getRequest()->getHeader('X-Authorization');

            $mainItemCollection = $this->mainItemFactory->create()->getCollection();
            $clientToken = $mainItemCollection->getLastItem()->getClientToken();
            if ($clientToken == null || $clientToken !== $authorizationHeader) {
                $resultPage->setData("Not authorized");
                $resultPage->setHttpResponseCode(403);
                return;
            }

            $this->loggerMy->info("Cron generating started manually.");
            $resultCode = $this->cron->createFeed();
            if($resultCode == true){
                $resultPage->setData('Feed generated.');
            } else {
                $resultPage->setData('Feed not generated.');
            }
        } catch (Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($this->getRequest());
            $resultPage->setHttpResponseCode(500);
            $resultPage->setData('Feed generation failed.');
        }

//            // buffer all upcoming output
//            ob_start();
//
//            echo "Cron is running.";
//
//            // get the size of the output
//            $size = ob_get_length();
//
//            // send headers to tell the browser to close the connection
//            header("Content-Length: $size");
//            header('Connection: close');
//
//            // flush all output
//            ob_end_flush();
//            ob_flush();
//            flush();
//
//            // if you're using sessions, this prevents subsequent requests
//            // from hanging while the background process executes
//            if (session_id()) session_write_close();
//
//            /******** background process starts here ********/
//            // start cron parser
//            $this->_cron->createFeed();
    }
}
