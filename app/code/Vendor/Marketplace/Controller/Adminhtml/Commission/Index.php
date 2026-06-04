<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Commission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Marketplace::manage_commission');
        $resultPage->addBreadcrumb(__('Commission'), __('Commission'));
        $resultPage->addBreadcrumb(__('Manage Commission'), __('Manage Commission'));
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Commission'));

        return $resultPage;
    }

    /**
     * Check permission for action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::manage_commission');
    }
}
