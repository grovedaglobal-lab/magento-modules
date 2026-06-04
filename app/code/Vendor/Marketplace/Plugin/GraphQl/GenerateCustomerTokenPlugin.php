<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Plugin\GraphQl;

use Magento\Customer\Model\CustomerFactory;
use Magento\CustomerGraphQl\Model\Resolver\GenerateCustomerToken;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Model\VendorFactory;

class GenerateCustomerTokenPlugin
{
    public function __construct(
        private readonly VendorFactory $vendorFactory,
        private readonly CustomerFactory $customerFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Block vendor-linked accounts from logging in via customer GraphQL token API.
     *
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return mixed
     */
    public function aroundResolve(
        GenerateCustomerToken $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $email = isset($args['email']) ? trim((string)$args['email']) : '';
        if ($email !== '') {
            $customerId = $this->getCustomerIdByEmail($email);
            if ($customerId > 0 && $this->isVendorCustomer($customerId)) {
                throw new GraphQlAuthenticationException(
                    __('Vendor accounts are not allowed here. Please use the vendor login portal.')
                );
            }
        }

        return $proceed($field, $context, $info, $value, $args);
    }

    private function getCustomerIdByEmail(string $email): int
    {
        try {
            $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($email);

            if (!$customer->getId()) {
                $customer = $this->customerFactory->create();
                $customer->loadByEmail($email);
            }

            return (int)$customer->getId();
        } catch (LocalizedException $e) {
            return 0;
        }
    }

    private function isVendorCustomer(int $customerId): bool
    {
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        return (bool)$vendor->getId();
    }
}
