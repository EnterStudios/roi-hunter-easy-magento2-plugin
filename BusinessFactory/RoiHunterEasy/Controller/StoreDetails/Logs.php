<?php
namespace BusinessFactory\RoiHunterEasy\Controller\StoreDetails;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Controller\Result\JsonFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;

class Logs extends Action
{

    private $fileFactory;
    /** @var JsonFactory */
    private $jsonResultFactory;

    private $filesystem;

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
        Logger $logger,
        FileFactory $fileFactory,
        JsonFactory $jsonResultFactory,
        MainItemFactory $mainItemFactory,
        Filesystem $filesystem
    )
    {
        $this->loggerMy = $logger;
        $this->fileFactory = $fileFactory;
        $this->jsonResultFactory = $jsonResultFactory;
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
        $this->loggerMy->info(__METHOD__ . "- Logs called.");
        $this->loggerMy->info($this->getRequest());

        $resultPage = $this->jsonResultFactory->create();

        $resultPage->setHeader('Access-Control-Allow-Origin', '*', true);
        $resultPage->setHeader('Access-Control-Allow-Methods', 'OPTIONS,GET', true);
        $resultPage->setHeader('Access-Control-Max-Age', '60', true);
        $resultPage->setHeader('Access-Control-Allow-Headers', 'X-Authorization', true);

        if ($this->getRequest()->getMethod() === 'GET') {
            return $this->processGET($resultPage);
        }
        return $resultPage;
    }

    /**
     * @param \Magento\Framework\Controller\Result\Json $resultPage
     * @return ResponseInterface|\Magento\Framework\Controller\Result\Json
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
                    return $resultPage;
                }
            }


            // Get real path for our folder
            $rootPath = $this->filesystem->getDirectoryWrite(DirectoryList::LOG)->getAbsolutePath();

            $zip_file = "all_logs.zip";
            $zip_file_path = $this->filesystem->getDirectoryWrite(DirectoryList::LOG)->getAbsolutePath() . $zip_file;

            // Initialize archive object
            $zip = new ZipArchive();
            $zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // Create recursive directory iterator
            /** @var SplFileInfo[] $files */
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath),
                RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($files as $name => $file) {
                // Skip directories (they would be added automatically)
                if (!$file->isDir() && strpos($file->getFilename(), 'all_logs.zip') === false) {
                    $this->loggerMy->info("ZIP file: " . $file);

                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath));

                    // Add current file to archive
                    $zip->addFile($filePath, $relativePath);
                }
            }
            // Zip archive will be created only after closing object
            $zip->close();

            return $this->fileFactory->create(
                $zip_file,
                [
                    'type' => "filename", //type has to be "filename"
                    'value' => "{$zip_file}", // path will append to the base dir
                    //'rm'    => true, // add this only if you would like to file to deleted after download from server
                ],
                $baseDir = DirectoryList::LOG,
                $contentType = 'application/octet-stream',
                $contentLength = null
            );
        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $resultPage->setHttpResponseCode(500);
            $resultPage->setData($exception);
            return $resultPage;
        }
    }
}
