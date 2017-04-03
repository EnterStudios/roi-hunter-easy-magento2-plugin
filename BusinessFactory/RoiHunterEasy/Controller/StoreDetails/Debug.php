<?php
namespace BusinessFactory\RoiHunterEasy\Controller\StoreDetails;

use BusinessFactory\RoiHunterEasy\Logger\Logger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Module\ModuleListInterface;
use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;

class Debug extends Action
{

    /** @var JsonFactory */
    private $jsonResultFactory;

    /**
     * Custom logging instance
     * @var Logger
     */
    private $loggerMy;

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;

    protected $_moduleList;
    private $_storeManager;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        MainItemFactory $mainItemFactory,
        ModuleListInterface $moduleList,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Logger $logger
    )
    {
        $this->jsonResultFactory = $jsonResultFactory;
        $this->mainItemFactory = $mainItemFactory;
        $this->loggerMy = $logger;
        $this->_moduleList = $moduleList;
        $this->_storeManager = $storeManager;

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
        $this->loggerMy->info(__METHOD__ . "- Check request.");
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

            /** @var \Magento\Framework\App\ObjectManager $om */
            $om = ObjectManager::getInstance();
            $state = $om->get('Magento\Framework\App\State');

            $resultData = $_SERVER;
            $resultData['Magento_Mode'] = $state->getMode();
            $resultData['Php_Version'] = phpversion();

            // Magento version
            $om = ObjectManager::getInstance();
            $productMetadata = $om->get('Magento\Framework\App\ProductMetadataInterface');
            $version = $productMetadata->getVersion(); //will return the magento version
            $resultData['Magento2_Version'] = $version;
            // ROI Hunter Easy version
            $resultData['ROI_Hunter_Easy_Version'] = $this->_moduleList->getOne('BusinessFactory_RoiHunterEasy')['setup_version'];
            $resultData['Stores array'] = $this->_storeManager->getStores();

            $resultPage->setData($resultData);

        } catch (\Exception $exception) {
            $this->loggerMy->info($exception);
            $resultPage->setHttpResponseCode(500);
            $resultPage->setData($exception);
        }
    }
}
