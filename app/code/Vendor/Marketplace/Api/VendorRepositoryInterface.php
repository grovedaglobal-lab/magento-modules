<?php
namespace Vendor\Marketplace\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Vendor\Marketplace\Api\Data\VendorInterface;

interface VendorRepositoryInterface
{
    /**
     * Save vendor
     * @param \Vendor\Marketplace\Api\Data\VendorInterface $vendor
     * @return \Vendor\Marketplace\Api\Data\VendorInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(VendorInterface $vendor);

    /**
     * Get vendor by ID
     * @param int $vendorId
     * @return \Vendor\Marketplace\Api\Data\VendorInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($vendorId);

    /**
     * Get vendor by Customer ID
     * @param int $customerId
     * @return \Vendor\Marketplace\Api\Data\VendorInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByCustomerId($customerId);

    /**
     * Get list
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Marketplace\Api\Data\VendorSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Get vendor by Shop URL (slug)
     * @param string $shopUrl
     * @return \Vendor\Marketplace\Api\Data\VendorInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByShopUrl($shopUrl);
}
