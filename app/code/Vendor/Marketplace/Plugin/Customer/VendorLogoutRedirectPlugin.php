<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Plugin\Customer;

use Magento\Customer\Controller\Account\Logout;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Vendor\Marketplace\Model\VendorFactory;

class VendorLogoutRedirectPlugin
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly VendorFactory $vendorFactory
    ) {
    }

    public function aroundExecute(Logout $subject, callable $proceed)
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        $isVendor = false;

        if ($customerId > 0) {
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
            $isVendor = (bool)$vendor->getId();
        }

        $result = $proceed();

        if ($isVendor && $result instanceof Redirect) {
            $result->setPath('marketplace/account/login');
        }

        return $result;
    }
}
