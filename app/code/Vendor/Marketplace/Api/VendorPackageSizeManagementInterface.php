<?php
/**
 * API: Vendor Package Size Management
 * 
 * Rest endpoint for vendors to request package sizes via API
 * Path: /rest/V1/vendor/package-size/request
 */
namespace Vendor\Marketplace\Api;

interface VendorPackageSizeManagementInterface
{
    /**
     * Request new package size option
     *
     * @param string $packageSize The package size (e.g., "3kg", "250ml")
     * @param string $reason Optional reason for request
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestPackageSize($packageSize, $reason = '');

    /**
     * Get vendor's package size requests
     *
     * @param string|null $status Filter by status (pending, approved, rejected)
     * @return array
     */
    public function getMyRequests($status = null);

    /**
     * Get all available package sizes
     *
     * @return array
     */
    public function getAvailableSizes();
}
