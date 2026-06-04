<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Model\VendorFactory;

class Import extends Action
{
    protected $resultPageFactory;
    protected $customerSession;
    protected $vendorFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Session $customerSession,
        VendorFactory $vendorFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            $this->customerSession->logout();
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        if ($vendor->getStatus() != 1) {
            $this->messageManager->addErrorMessage(__('Your vendor account is pending approval. You cannot import products yet.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/product/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Import Products'));

        return $resultPage;
    }
}