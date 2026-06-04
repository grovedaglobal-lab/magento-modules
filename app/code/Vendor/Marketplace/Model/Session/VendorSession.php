<?php
namespace Vendor\Marketplace\Model\Session;

use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Api\VendorRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class VendorSession
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var VendorRepositoryInterface
     */
    protected $vendorRepository;

    /**
     * @var \Vendor\Marketplace\Api\Data\VendorInterface
     */
    protected $vendor;

    /**
     * @param CustomerSession $customerSession
     * @param VendorRepositoryInterface $vendorRepository
     */
    public function __construct(
        CustomerSession $customerSession,
        VendorRepositoryInterface $vendorRepository
    ) {
        $this->customerSession = $customerSession;
        $this->vendorRepository = $vendorRepository;
    }

    /**
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn() && $this->getVendorId();
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->customerSession->getCustomerId();
    }

    /**
     * @return int|null
     */
    public function getVendorId()
    {
        $vendor = $this->getVendor();
        return $vendor ? $vendor->getEntityId() : null;
    }

    /**
     * @return \Vendor\Marketplace\Api\Data\VendorInterface|null
     */
    public function getVendor()
    {
        if ($this->vendor === null && $this->getCustomerId()) {
            try {
                $this->vendor = $this->vendorRepository->getByCustomerId($this->getCustomerId());
            } catch (NoSuchEntityException $e) {
                $this->vendor = false;
            }
        }
        return $this->vendor ?: null;
    }
}
