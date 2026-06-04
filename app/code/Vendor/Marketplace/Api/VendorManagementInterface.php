<?php
namespace Vendor\Marketplace\Api;

interface VendorManagementInterface
{
    /**
     * Register a new vendor
     *
     * @param int $customerId
     * @param string $shopUrl
     * @param string $shopName
     * @return \Vendor\Marketplace\Api\Data\VendorInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function registerVendor($customerId, $shopUrl, $shopName);

    /**
     * Approve a vendor
     * @param int $vendorId
     * @return bool
     */
    public function approveVendor($vendorId);

    /**
     * Reject a vendor
     * @param int $vendorId
     * @return bool
     */
    public function rejectVendor($vendorId);
}
