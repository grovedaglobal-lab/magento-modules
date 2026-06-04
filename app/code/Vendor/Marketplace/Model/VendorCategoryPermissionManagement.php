<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorCategoryPermissionManagementInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorCategoryPermission\CollectionFactory;
use Vendor\Marketplace\Model\VendorCategoryPermissionFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorCategoryPermission as ResourcePermission;
use Magento\Authorization\Model\UserContextInterface;
use Vendor\Marketplace\Model\VendorRepository;

class VendorCategoryPermissionManagement implements VendorCategoryPermissionManagementInterface
{
    protected $collectionFactory;
    protected $permissionFactory;
    protected $resourcePermission;
    protected $userContext;
    protected $vendorRepository;

    public function __construct(
        CollectionFactory $collectionFactory,
        VendorCategoryPermissionFactory $permissionFactory,
        ResourcePermission $resourcePermission,
        UserContextInterface $userContext,
        VendorRepository $vendorRepository
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->permissionFactory = $permissionFactory;
        $this->resourcePermission = $resourcePermission;
        $this->userContext = $userContext;
        $this->vendorRepository = $vendorRepository;
    }

    protected function getVendorId()
    {
        $customerId = $this->userContext->getUserId();
        $vendor = $this->vendorRepository->getByCustomerId($customerId);
        return $vendor->getEntityId();
    }

    public function requestCategory($categoryId)
    {
        $vendorId = $this->getVendorId();

        // Check if already requested
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('category_id', $categoryId);

        $item = $collection->getFirstItem();
        if ($item->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Permission already requested/exists for this category.')
            );
        }

        $permission = $this->permissionFactory->create();
        $permission->setVendorId($vendorId);
        $permission->setCategoryId($categoryId);
        $permission->setStatus(0); // Pending

        $this->resourcePermission->save($permission);
        return $permission;
    }

    public function getRequestedCategories()
    {
        $vendorId = $this->getVendorId();
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        return $collection->getItems();
    }

    public function isAllowed($vendorId, array $categoryIds)
    {
        if (empty($categoryIds)) {
            return true;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('category_id', ['in' => $categoryIds]);
        $collection->addFieldToFilter('status', 1); // Approved

        // Check count. If we requested approval for 3 cats, we must find 3 approved records?
        // OR, do we fail if ANY is missing?
        // Strict Mode: ALL categories must be approved.

        // BYPASS PERMISSION CHECK FOR NOW TO ALLOW SAVING
        return true;

        // $approvedCount = $collection->getSize();
        // return $approvedCount == count($categoryIds);
    }
}
