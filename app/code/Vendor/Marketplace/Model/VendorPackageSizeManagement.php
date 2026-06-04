<?php
/**
 * API Implementation: Vendor Package Size Management
 */
namespace Vendor\Marketplace\Model;

use Magento\Framework\Exception\LocalizedException;
use Vendor\Marketplace\Api\VendorPackageSizeManagementInterface;

class VendorPackageSizeManagement implements VendorPackageSizeManagementInterface
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var VendorFactory
     */
    protected $vendorFactory;

    /**
     * @var PackageSizeRequestFactory
     */
    protected $requestFactory;

    /**
     * @var \Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager
     */
    protected $packageSizeManager;

    /**
     * @param \Magento\Customer\Model\Session $customerSession
     * @param VendorFactory $vendorFactory
     * @param PackageSizeRequestFactory $requestFactory
     * @param \Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager $packageSizeManager
     */
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        VendorFactory $vendorFactory,
        PackageSizeRequestFactory $requestFactory,
        \Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager $packageSizeManager
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->requestFactory = $requestFactory;
        $this->packageSizeManager = $packageSizeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function requestPackageSize($packageSize, $reason = '')
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer not logged in.'));
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            throw new LocalizedException(__('Vendor not found.'));
        }

        $packageSize = trim($packageSize);
        if (!$packageSize) {
            throw new LocalizedException(__('Package size cannot be empty.'));
        }

        // Check if already exists
        if ($this->packageSizeManager->optionExists($packageSize)) {
            return [
                'status' => 'available',
                'message' => __('Package size "%1" is already available.', $packageSize),
                'package_size' => $packageSize
            ];
        }

        // Check if already requested
        $existingRequest = $this->requestFactory->create()->getResourceCollection()
            ->addFieldToFilter('vendor_id', $vendor->getId())
            ->addFieldToFilter('package_size', $packageSize)
            ->addFieldToFilter('status', ['in' => ['pending', 'approved']]);

        if ($existingRequest->getSize()) {
            throw new LocalizedException(__('You already requested this package size.'));
        }

        // Create request
        $request = $this->requestFactory->create();
        $request->setVendorId($vendor->getId())
            ->setPackageSize($packageSize)
            ->setReason($reason)
            ->setStatus('pending')
            ->setCreatedAt(date('Y-m-d H:i:s'));

        $request->save();

        return [
            'status' => 'pending',
            'message' => __('Your request has been submitted for review.'),
            'package_size' => $packageSize,
            'request_id' => $request->getId()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMyRequests($status = null)
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer not logged in.'));
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            throw new LocalizedException(__('Vendor not found.'));
        }

        $collection = $this->requestFactory->create()->getResourceCollection()
            ->addFieldToFilter('vendor_id', $vendor->getId());

        if ($status) {
            $collection->addFieldToFilter('status', $status);
        }

        $results = [];
        foreach ($collection as $request) {
            $results[] = [
                'id' => $request->getId(),
                'package_size' => $request->getPackageSize(),
                'reason' => $request->getReason(),
                'status' => $request->getStatus(),
                'created_at' => $request->getCreatedAt(),
                'admin_notes' => $request->getAdminNotes() ?: ''
            ];
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableSizes()
    {
        return $this->packageSizeManager->getAllOptions();
    }
}
