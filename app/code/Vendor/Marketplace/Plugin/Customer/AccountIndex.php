<?php
namespace Vendor\Marketplace\Plugin\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\ObjectManager;

class AccountIndex
{
    protected $customerSession;
    protected $resultRedirectFactory;

    public function __construct(
        Session $customerSession,
        RedirectFactory $resultRedirectFactory
    ) {
        $this->customerSession = $customerSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function aroundExecute(\Magento\Customer\Controller\Account\Index $subject, callable $proceed)
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomerId();
            
            $objectManager = ObjectManager::getInstance();
            $vendorFactory = $objectManager->create(\Vendor\Marketplace\Model\VendorFactory::class);
            $vendor = $vendorFactory->create()->load($customerId, 'customer_id');

            if ($vendor->getId()) {
                // If the user has a vendor entity (pending or active), redirect them to the vendor dashboard
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('marketplace/seller/dashboard');
                return $resultRedirect;
            }
        }
        
        return $proceed();
    }
}
