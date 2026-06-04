<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Transaction;

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
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Marketplace::sellers_transaction');
        $resultPage->addBreadcrumb(__('Vendor Marketplace'), __('Vendor Marketplace'));
        $resultPage->addBreadcrumb(__('Sellers Transaction'), __('Sellers Transaction'));
        $resultPage->getConfig()->getTitle()->prepend(__('Sellers Transaction'));

        return $resultPage;
    }

    /**
     * Check permissions for this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::sellers_transaction');
    }
}
