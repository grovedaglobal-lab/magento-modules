<?php
namespace Vendor\Marketplace\Api;

interface VendorCategoryPermissionManagementInterface
{
    /**
     * Request permission for a category
     * @param int $categoryId
     * @return \Vendor\Marketplace\Api\Data\VendorCategoryPermissionInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestCategory($categoryId);

    /**
     * Get statuses for all requested categories
     * @return \Vendor\Marketplace\Api\Data\VendorCategoryPermissionInterface[]
     */
    public function getRequestedCategories();

    /**
     * Check if vendor is approved for specific categories
     * @param int $vendorId
     * @param array $categoryIds
     * @return bool
     */
    public function isAllowed($vendorId, array $categoryIds);
}
