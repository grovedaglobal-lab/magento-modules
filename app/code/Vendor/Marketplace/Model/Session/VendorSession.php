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
        return $this->customerSession->isLoggedIn() && (bool) $this->getVendorId();
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
        if ($vendor) {
            return $vendor->getEntityId() ?: $vendor->getId();
        }
        return null;
    }

    /**
     * @return \Vendor\Marketplace\Api\Data\VendorInterface|null
     */
    public function getVendor()
    {
        if ($this->vendor === null && $this->getCustomerId()) {
            try {
                $this->vendor = $this->vendorRepository->getByCustomerId($this->getCustomerId());
            } catch (\Exception $e) {
                // Direct fallback load if repository does not locate entity
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $vendorFactory = $objectManager->create(\Vendor\Marketplace\Model\VendorFactory::class);
                $loaded = $vendorFactory->create()->load($this->getCustomerId(), 'customer_id');
                $this->vendor = ($loaded && $loaded->getId()) ? $loaded : false;
            }
        }
        return $this->vendor ?: null;
    }
}
