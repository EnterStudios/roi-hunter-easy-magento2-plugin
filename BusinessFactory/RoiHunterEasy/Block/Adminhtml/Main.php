<?php

namespace BusinessFactory\RoiHunterEasy\Block\Adminhtml;

use BusinessFactory\RoiHunterEasy\Model\MainItemFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Main extends Template
{

    /**
     * @var MainItemFactory
     */
    private $mainItemFactory;
    protected $_moduleList;

    public function __construct(
        Context $context,
        MainItemFactory $mainItemFactory,
        ModuleListInterface $moduleList,
        array $data = []
    )
    {
        $this->mainItemFactory = $mainItemFactory;
        $this->_moduleList = $moduleList;
        parent::__construct($context, $data);
    }

    public function isStagingActive()
    {
        $path = $this->_filesystem->getDirectoryWrite(DirectoryList::ROOT)->getAbsolutePath()
            . 'roi_hunter_staging_active';

        if (file_exists($path)) {
            return true;
        } else {
            return false;
        }
    }

    public function getIFrameBaseUrl()
    {
        if ($this->isStagingActive()) {
            return '//goostav-fe-staging.roihunter.com/';
        } else {
            return '//magento.roihunter.com/';
        }
    }

    public function getStoreBaseUrl()
    {
        return $this->_storeManager->getDefaultStoreView()->getBaseUrl();
    }

    public function getStoreName()
    {
        return $this->getConfigValue('general/store_information/name');
    }

    public function getStoreLogo()
    {
        return $this->getConfigValue('design/header/logo_src');
    }

    public function getStoreCurrency()
    {
        return $this->_storeManager->getDefaultStoreView()->getBaseCurrencyCode();
    }

    public function getDevelopmentMode()
    {
        /** @var \Magento\Framework\App\ObjectManager $om */
        $om = ObjectManager::getInstance();
        $state = $om->get('Magento\Framework\App\State');
        /** @var bool $isDeveloperMode */
        return $state->getMode();
    }

    public function getStoreLanguage()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = ObjectManager::getInstance();
        /** @var \Magento\Framework\Locale\Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');

        $locale = explode('_', $resolver->getLocale());

        return $locale[0];
    }

    public function getStoreCountry()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = ObjectManager::getInstance();
        /** @var \Magento\Framework\Locale\Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');

        $locale = explode('_', $resolver->getLocale());
        if (is_array($locale) && count($locale) > 1) {
            return $locale[1];
        } else {
            return 'US';
        }
    }

    public function getPluginVersion()
    {
        return $this->_moduleList->getOne('BusinessFactory_RoiHunterEasy')['setup_version'];
    }

    private function getConfigValue($configPath)
    {
        return $this->_scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);
    }

    public function getMainItemEntry()
    {
        $mainItemCollection = $this->mainItemFactory->create()->getCollection();
        if ($mainItemCollection->count() <= 0) {
            return null;
        } else {
            return $mainItemCollection->getLastItem();
        }
    }
}
