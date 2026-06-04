<?php
namespace Vendor\Marketplace\Api;

interface VendorProductManagementInterface
{
    /**
     * Get products for the currently logged in vendor.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     */
    public function getMyProducts(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Delete a product owned by the vendor.
     * 
     * @param string $sku
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteMyProduct($sku);
}
