<?php
namespace BusinessFactory\RoiHunterEasy\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    private $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory)
    {
        $this->resultPageFactory = $resultPageFactory;
        return parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('BusinessFactory_RoiHunterEasy::roi_hunter_easy');

//        Allow change page title. Function (__) -> “translate this”.
        $page->getConfig()->getTitle()->prepend('ROI Hunter Easy');

        return $page;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('BusinessFactory_RoiHunterEasy::roi_hunter_easy');
    }
}
