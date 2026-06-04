<?php
/**
 * Controller: Vendor Request Package Size Option
 * 
 * Allows vendors to request new package size options
 * Admin reviews and approves to add them globally
 */
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\PackageSizeRequestFactory;
use Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager;

class RequestPackageSize extends Action
{
    /**
     * @var Session
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
     * @var PackageSizeManager
     */
    protected $packageSizeManager;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param VendorFactory $vendorFactory
     * @param PackageSizeRequestFactory $requestFactory
     * @param PackageSizeManager $packageSizeManager
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        PackageSizeRequestFactory $requestFactory,
        PackageSizeManager $packageSizeManager
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->requestFactory = $requestFactory;
        $this->packageSizeManager = $packageSizeManager;
        parent::__construct($context);
    }

    /**
     * Execute request
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if (!$vendor->getId()) {
                throw new \Exception(__('Vendor not found.'));
            }

            $packageSize = isset($data['package_size']) ? trim($data['package_size']) : null;
            $reason = isset($data['reason']) ? trim($data['reason']) : '';

            if (!$packageSize) {
                $this->messageManager->addErrorMessage(__('Package size is required.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }

            // Validate format
            if (!$this->packageSizeManager->validateFormat($packageSize)) {
                $this->messageManager->addWarningMessage(
                    __('Format may not be standard. Examples: 3kg, 250ml, Pack of 12')
                );
            }

            // Check if already exists
            if ($this->packageSizeManager->optionExists($packageSize)) {
                $this->messageManager->addWarningMessage(
                    __('Package size "%1" already exists. You can use it now.', $packageSize)
                );
                return $this->resultRedirectFactory->create()->setPath('marketplace/product/index');
            }

            // Check if already requested
            $request = $this->requestFactory->create();
            if ($request->getResourceCollection()
                ->addFieldToFilter('vendor_id', $vendor->getId())
                ->addFieldToFilter('package_size', $packageSize)
                ->addFieldToFilter('status', ['in' => ['pending', 'approved']])
                ->getSize()) {
                $this->messageManager->addWarningMessage(
                    __('You already requested this package size.')
                );
                return $this->resultRedirectFactory->create()->setPath('marketplace/product/index');
            }

            // Create request
            $request->setVendorId($vendor->getId())
                ->setPackageSize($packageSize)
                ->setReason($reason)
                ->setStatus('pending')
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $request->save();

            $this->messageManager->addSuccessMessage(
                __('Your request for package size "%1" has been submitted. Admin will review and approve it.', $packageSize)
            );

            return $this->resultRedirectFactory->create()->setPath('marketplace/product/index');

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error: %1', $e->getMessage()));
            return $this->resultRedirectFactory->create()->setPath('marketplace/product/index');
        }
    }
}
