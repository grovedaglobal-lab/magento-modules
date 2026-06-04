<?php
namespace Vendor\Marketplace\Api;

interface VendorOrderManagementInterface
{
    /**
     * Get orders for the logged-in vendor.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Marketplace\Api\Data\VendorOrderSearchResultsInterface
     */
    public function getMyOrders(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}
