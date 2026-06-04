<?php
namespace Tax\IndianGST\Api;

interface VendorRepositoryInterface
{
    /**
     * Get Vendor by ID
     *
     * @param int $id
     * @return \Tax\IndianGST\Api\Data\VendorProfileInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * Save Vendor
     *
     * @param \Tax\IndianGST\Api\Data\VendorProfileInterface $vendor
     * @return \Tax\IndianGST\Api\Data\VendorProfileInterface
     */
    public function save(\Tax\IndianGST\Api\Data\VendorProfileInterface $vendor);
}
