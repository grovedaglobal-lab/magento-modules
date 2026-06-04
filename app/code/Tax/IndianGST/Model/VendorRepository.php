<?php
namespace Tax\IndianGST\Model;

use Tax\IndianGST\Api\VendorRepositoryInterface;
use Tax\IndianGST\Api\Data\VendorProfileInterface;
use Tax\IndianGST\Model\ResourceModel\VendorProfile as ResourceModel;
use Tax\IndianGST\Model\VendorProfileFactory;

class VendorRepository implements VendorRepositoryInterface
{
    protected $resource;
    protected $vendorFactory;

    public function __construct(
        ResourceModel $resource,
        VendorProfileFactory $vendorFactory
    ) {
        $this->resource = $resource;
        $this->vendorFactory = $vendorFactory;
    }

    public function getById($id)
    {
        $vendor = $this->vendorFactory->create();
        $this->resource->load($vendor, $id);
        if (!$vendor->getId()) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('Vendor not found.'));
        }
        return $vendor;
    }

    public function save(VendorProfileInterface $vendor)
    {
        $this->resource->save($vendor);
        return $vendor;
    }
}
