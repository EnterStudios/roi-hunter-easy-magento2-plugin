<?php

namespace BusinessFactory\RoiHunterEasy\Controller\Feed;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;

class Feed extends Action
{
    private $fileFactory;

    /** @var JsonFactory */
    private $jsonResultFactory;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;
    private $filesystem;

    public function __construct(
        Context $context,
        Logger $logger,
        FileFactory $fileFactory,
        JsonFactory $jsonResultFactory,
        Filesystem $filesystem
    )
    {
        $this->loggerMy = $logger;
        $this->fileFactory = $fileFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->filesystem = $filesystem;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->loggerMy->info('Get product feed called.');

        try {
            $format = $this->getRequest()->getParam('format');
            if (!isset($format) || trim($format) === '') {
                $format = 'xml';
            }

            $dirPath = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath();
            $filename = 'roi_hunter_easy_feed_final.' . $format;

            $this->loggerMy->info('Get feed file: ' .$dirPath . 'feeds/' . $filename);
            if (file_exists($dirPath . 'feeds/' . $filename)) {
                return $this->fileFactory->create(
                    $filename,
                    [
                        'type' => 'filename', //type has to be 'filename'
                        'value' => "feeds/{$filename}", // path will append to the base dir
                        //'rm'    => true, // add this only if you would like to file to deleted after download from server
                    ],
                    $baseDir = DirectoryList::VAR_DIR,
                    $contentType = 'application/octet-stream',
                    $contentLength = null
                );
            } else {
                $this->loggerMy->info("Feed file doesn't exists");
                $resultPage = $this->jsonResultFactory->create();
                $resultPage->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND);
                $resultPage->setData(
                    ['error_message' => "Feed file doesn't exists"]
                );
                return $resultPage;
            }
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $this->loggerMy->info($this->getRequest());

            $resultPage = $this->jsonResultFactory->create();
            $resultPage->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
            $resultPage->setData(
                ['error_message' => 'Cannot return feed. Please look to log file for more information.']
            );
            return $resultPage;
        }
    }
}
