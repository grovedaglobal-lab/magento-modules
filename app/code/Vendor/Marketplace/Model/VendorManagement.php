<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorManagementInterface;
use Vendor\Marketplace\Api\VendorRepositoryInterface;
use Vendor\Marketplace\Api\Data\VendorInterfaceFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile as ResourceVendorProfile;
use Magento\Framework\Exception\AlreadyExistsException;

class VendorManagement implements VendorManagementInterface
{
    protected $vendorRepository;
    protected $vendorFactory;
    protected $vendorProfileFactory;
    protected $resourceVendorProfile;

    public function __construct(
        VendorRepositoryInterface $vendorRepository,
        VendorInterfaceFactory $vendorFactory,
        VendorProfileFactory $vendorProfileFactory,
        ResourceVendorProfile $resourceVendorProfile
    ) {
        $this->vendorRepository = $vendorRepository;
        $this->vendorFactory = $vendorFactory;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->resourceVendorProfile = $resourceVendorProfile;
    }

    public function registerVendor($customerId, $shopUrl, $shopName)
    {
        // Check if exists
        try {
            $existing = $this->vendorRepository->getByCustomerId($customerId);
            if ($existing->getEntityId()) {
                throw new AlreadyExistsException(__('Vendor account already exists for this customer.'));
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Good, does not exist
        }

        $vendor = $this->vendorFactory->create();
        $vendor->setCustomerId($customerId);
        $vendor->setShopUrl($shopUrl);
        $vendor->setStatus(0); // Pending

        // Save Main Entity
        $this->vendorRepository->save($vendor);

        // Save Profile Data
        $profile = $this->vendorProfileFactory->create();
        $profile->setVendorId($vendor->getEntityId());
        $profile->setShopName($shopName);

        $this->resourceVendorProfile->save($profile);

        return $vendor;
    }

    public function approveVendor($vendorId)
    {
        $vendor = $this->vendorRepository->getById($vendorId);
        $vendor->setStatus(1); // Approved
        $this->vendorRepository->save($vendor);
        return true;
    }

    public function rejectVendor($vendorId)
    {
        $vendor = $this->vendorRepository->getById($vendorId);
        $vendor->setStatus(2); // Rejected
        $this->vendorRepository->save($vendor);
        return true;
    }
}
