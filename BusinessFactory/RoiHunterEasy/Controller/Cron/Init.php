<?php
namespace BusinessFactory\RoiHunterEasy\Controller\Cron;

use BusinessFactory\RoiHunterEasy\Logger\Logger;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem;
use Magento\Framework\Controller\Result\JsonFactory;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;

class Init extends Action
{
    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    /** @var JsonFactory */
    private $jsonResultFactory;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    private $filesystem;
    private $fileMy;

    public function __construct(
        Context $context,
        Logger $logger,
        MainItemFactory $mainItemFactory,
        Filesystem $filesystem,
        File $file,
        JsonFactory $resultJsonFactory
    )
    {
        $this->jsonResultFactory = $resultJsonFactory;
        $this->loggerMy = $logger;
        $this->mainItemFactory = $mainItemFactory;
        $this->filesystem = $filesystem;
        $this->fileMy = $file;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $this->loggerMy->info(__METHOD__ . "- FeedReset called.");
        $this->loggerMy->info($this->getRequest());

        $resultPage = $this->jsonResultFactory->create();

        $resultPage->setHeader('Access-Control-Allow-Origin', '*', true);
        $resultPage->setHeader('Access-Control-Allow-Methods', 'OPTIONS,GET', true);
        $resultPage->setHeader('Access-Control-Max-Age', '60', true);
        $resultPage->setHeader('Access-Control-Allow-Headers', 'X-Authorization', true);

        if ($this->getRequest()->getMethod() === 'GET') {
            $this->processGET($resultPage);
        }
        return $resultPage;
    }

    /**
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     */
    private function processGET($resultPage)
    {
        try {
            // If table not empty, require authorization.
            $mainItemCollection = $this->mainItemFactory->create()->getCollection();
            if ($mainItemCollection->count() > 0) {

                $authorizationHeader = $this->getRequest()->getHeader('X-Authorization');
                $dataEntity = $mainItemCollection->getLastItem();

                // If data exist check for client token.
                if ($dataEntity->getClientToken() != null && $dataEntity->getClientToken() !== $authorizationHeader) {
                    $resultPage->setData("Not authorized");
                    $resultPage->setHttpResponseCode(403);
                    return;
                }
            }

            $path = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)->getAbsolutePath()
                . "businessFactoryRoiHunterEasyFeedSign";

            if (!file_exists($path)) {
                $resultPage->setData("Reset already completed.");
            } else {
                // try delete feed generation sign.
                $this->fileMy->deleteFile($path);
                $resultPage->setData("Reset completed.");
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $resultPage->setHttpResponseCode(500);
            $resultPage->setData($exception);
        }
    }
}
