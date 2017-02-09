<?php

namespace BusinessFactory\RoiHunterEasy\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use BusinessFactory\RoiHunterEasy\Logger\Logger;

class ResetFeed extends Action
{
    protected $resultJsonFactory;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    private $filesystem;
    private $fileMy;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param MainItemFactory $mainItemFactory
     * @param Filesystem $filesystem
     * @param File $file
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        Logger $logger,
        MainItemFactory $mainItemFactory,
        Filesystem $filesystem,
        File $file,
        JsonFactory $resultJsonFactory
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
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
        $this->loggerMy->info(__METHOD__ . " invoked");

        /** @var \Magento\Framework\Controller\Result\Json $response */
        $response = $this->resultJsonFactory->create();

        try {
            $path = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT)->getAbsolutePath()
                . "businessFactoryRoiHunterEasyFeedSign";
            if (!file_exists($path)) {
                return $response->setData("Reset already completed.");
            } else {
                // try delete feed generation sign.
                $this->fileMy->deleteFile($path);
                return $response->setData("Reset completed.");
            }
        } catch (\Exception $e) {
            $this->loggerMy->info($e);
            return $response->setData("Restore feed failed. Please check logs and contact support at easy@roihunter.com.");
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('BusinessFactory_RoiHunterEasy::roi_hunter_easy');
    }
}
