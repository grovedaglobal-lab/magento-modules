<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\VendorRepository;
use Magento\Authorization\Model\UserContextInterface;
use Vendor\Marketplace\Api\VendorCategoryPermissionManagementInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductSaveBefore implements ObserverInterface
{
    protected $userContext;
    protected $vendorRepository;
    protected $permissionManagement;

    public function __construct(
        UserContextInterface $userContext,
        VendorRepository $vendorRepository,
        VendorCategoryPermissionManagementInterface $permissionManagement
    ) {
        $this->userContext = $userContext;
        $this->vendorRepository = $vendorRepository;
        $this->permissionManagement = $permissionManagement;
    }

    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();

        // Check if the current user is a vendor (Customer Type)
        $userId = $this->userContext->getUserId();
        $userType = $this->userContext->getUserType();

        if ($userType == UserContextInterface::USER_TYPE_CUSTOMER) {
            try {
                $vendor = $this->vendorRepository->getByCustomerId($userId);
                if ($vendor->getEntityId() && $vendor->getStatus() == 1) { // Approved
                    // Force the vendor_id
                    $topLevelVendorId = $vendor->getEntityId();
                    $product->setData('vendor_id', $topLevelVendorId);

                    // CHECK CATEGORY PERMISSIONS
                    $categoryIds = $product->getCategoryIds();
                    if (!empty($categoryIds)) {
                        $isAllowed = $this->permissionManagement->isAllowed($topLevelVendorId, $categoryIds);

                        if (!$isAllowed) {
                            throw new LocalizedException(
                                __('You are not authorized to sell products in one or more selected categories. Please request approval first.')
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                // Propagate LocalizedException for UI feedback
                if ($e instanceof LocalizedException) {
                    throw $e;
                }
            }
        }
    }
}
