<?php
namespace Vendor\Marketplace\Plugin\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\ObjectManager;

class LoginPost
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

    public function afterExecute(\Magento\Customer\Controller\Account\LoginPost $subject, $result)
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomerId();
            
            $objectManager = ObjectManager::getInstance();
            $vendorFactory = $objectManager->create(\Vendor\Marketplace\Model\VendorFactory::class);
            $vendor = $vendorFactory->create()->load($customerId, 'customer_id');

            if ($vendor->getId()) {
                // If the user is a vendor (pending or active), intercept the regular redirect and send them to the vendor dashboard
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('marketplace/seller/dashboard');
                return $resultRedirect;
            }
        }
        
        return $result;
    }
}
