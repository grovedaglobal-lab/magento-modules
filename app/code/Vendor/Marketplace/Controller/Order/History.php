<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;

class History extends Action
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
        $customerId = $this->customerSession->getCustomerId();

        // Log basic session info
        $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
        $logger->info("VendorHistoryController: Security check for Customer ID: " . var_export($customerId, true));

        if (!$this->customerSession->isLoggedIn() || !$customerId) {
            $this->customerSession->logout(); // Ensure clean state
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        // Verify vendor identity
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        if (!$vendor->getId()) {
            $logger->warning("VendorHistoryController: Unauthorized access attempt or missing vendor record for Customer $customerId. Logging out.");
            $this->customerSession->logout();
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My Vendor Orders'));
        return $resultPage;
    }
}
