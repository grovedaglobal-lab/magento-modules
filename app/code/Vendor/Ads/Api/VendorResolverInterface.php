<?php
namespace Vendor\Ads\Api;

interface VendorResolverInterface
{
    /**
     * Get vendor ID from a Magento customer ID
     * @param int $customerId
     * @return int|null
     */
    public function getVendorIdByCustomer(int $customerId): ?int;

    /**
     * Get vendor display name by vendor ID
     * @param int $vendorId
     * @return string|null
     */
    public function getVendorName(int $vendorId): ?string;

    /**
     * Check if a customer is a registered vendor
     * @param int $customerId
     * @return bool
     */
    public function isVendor(int $customerId): bool;
}
