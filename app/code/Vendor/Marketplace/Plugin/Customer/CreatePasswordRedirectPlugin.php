<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Plugin\Customer;

use Magento\Customer\Controller\Account\CreatePassword;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;
use Vendor\Marketplace\Model\VendorFactory;

class CreatePasswordRedirectPlugin
{
    public function __construct(
        private readonly VendorFactory $vendorFactory,
        private readonly Session $customerSession,
        private readonly RedirectFactory $resultRedirectFactory
    ) {
    }

    public function aroundExecute(CreatePassword $subject, callable $proceed)
    {
        $request = $subject->getRequest();
        $customerId = (int)$request->getParam('id');
        $token = (string)$request->getParam('token');

        if ($customerId <= 0) {
            $customerId = (int)$this->customerSession->getRpCustomerId();
            if ($token === '') {
                $token = (string)$this->customerSession->getRpToken();
            }
        }

        if ($customerId > 0 && $this->isVendorCustomer($customerId)) {
            $params = [];
            if ($token !== '') {
                $params['token'] = $token;
                $params['id'] = $customerId;
            }

            return $this->resultRedirectFactory->create()->setPath('marketplace/account/createpassword', $params);
        }

        return $proceed();
    }

    private function isVendorCustomer(int $customerId): bool
    {
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        return (bool)$vendor->getId();
    }
}