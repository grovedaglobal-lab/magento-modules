<?php
namespace Vendor\Marketplace\Controller\Seller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;

class Dashboard extends Action
{
    protected $resultPageFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Session $customerSession
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        // Check if the logged-in customer is actually a vendor
        $customerId = $this->customerSession->getCustomerId();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $vendorFactory = $objectManager->create(\Vendor\Marketplace\Model\VendorFactory::class);
        $vendor = $vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            $this->messageManager->addErrorMessage(__('You do not have a vendor account. Please register as a vendor.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/account/create');
        } elseif ($vendor->getStatus() == 0) {
            // Allow them to see the dashboard but we keep the notice. 
            // The template will handle the 'pending' state visually.
            $this->messageManager->addNoticeMessage(__('Your vendor account is pending approval. You will have full access once approved.'));
        } elseif ($vendor->getStatus() != 1) {
            $this->messageManager->addErrorMessage(__('Your vendor account is inactive.'));
            return $this->resultRedirectFactory->create()->setPath('customer/account/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Vendor Dashboard'));
        return $resultPage;
    }
}
