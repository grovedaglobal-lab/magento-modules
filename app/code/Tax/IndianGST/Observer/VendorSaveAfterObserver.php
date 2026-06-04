<?php
declare(strict_types=1);

namespace Tax\IndianGST\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tax\IndianGST\Model\VendorProfileFactory;
use Tax\IndianGST\Model\ResourceModel\VendorProfile as VendorProfileResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Directory\Model\RegionFactory;
use Psr\Log\LoggerInterface;

class VendorSaveAfterObserver implements ObserverInterface
{
    /**
     * @var VendorProfileFactory
     */
    protected $gstProfileFactory;

    /**
     * @var VendorProfileResource
     */
    protected $gstProfileResource;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var \Tax\IndianGST\Helper\Data
     */
    protected $gstHelper;

    /**
     * @param VendorProfileFactory $gstProfileFactory
     * @param VendorProfileResource $gstProfileResource
     * @param ObjectManagerInterface $objectManager
     * @param RegionFactory $regionFactory
     * @param \Tax\IndianGST\Helper\Data $gstHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        VendorProfileFactory $gstProfileFactory,
        VendorProfileResource $gstProfileResource,
        ObjectManagerInterface $objectManager,
        RegionFactory $regionFactory,
        \Tax\IndianGST\Helper\Data $gstHelper,
        LoggerInterface $logger
    ) {
        $this->gstProfileFactory = $gstProfileFactory;
        $this->gstProfileResource = $gstProfileResource;
        $this->objectManager = $objectManager;
        $this->regionFactory = $regionFactory;
        $this->gstHelper = $gstHelper;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $event = $observer->getEvent();
            $vendorId = null;
            $profile = null;

            // Handle both Vendor and VendorProfile objects
            $object = $event->getData('data_object') ?: $event->getObject();

            if ($object instanceof \Vendor\Marketplace\Model\Vendor) {
                $vendorId = (int) $object->getId();
                // Load profile to get GST data
                $marketplaceProfileFactory = $this->objectManager->get('Vendor\Marketplace\Model\VendorProfileFactory');
                $profile = $marketplaceProfileFactory->create()->load($vendorId, 'vendor_id');
            } elseif ($object instanceof \Vendor\Marketplace\Model\VendorProfile) {
                $vendorId = (int) $object->getVendorId();
                $profile = $object;
            }

            if (!$vendorId) {
                return;
            }

            // Sync with GST Profile
            $gstProfile = $this->gstProfileFactory->create();
            $this->gstProfileResource->load($gstProfile, (string) $vendorId, 'vendor_code');

            if (!$gstProfile->getId()) {
                $gstProfile->setVendorCode((string) $vendorId);
            }

            if ($profile && $profile->getId()) {
                // Mapping: Use configured columns from Indian GST settings
                $gstinCol = $this->gstHelper->getGstinColumn() ?: 'tax_id';
                $panCol = $this->gstHelper->getPanColumn() ?: 'business_license';
                $nameCol = $this->gstHelper->getBusinessNameColumn() ?: 'shop_name';
                $postcodeCol = $this->gstHelper->getPostcodeColumn() ?: 'zip_code';

                if ($profile->getData($gstinCol)) {
                    $gstProfile->setGstin((string) $profile->getData($gstinCol));
                    $gstProfile->setIsRegistered(1);
                }

                if ($profile->getData($panCol)) {
                    $gstProfile->setPanNumber((string) $profile->getData($panCol));
                }

                if ($profile->getData($nameCol)) {
                    $gstProfile->setBusinessName((string) $profile->getData($nameCol));
                }

                if ($profile->getData($postcodeCol)) {
                    $gstProfile->setPostcode((string) $profile->getData($postcodeCol));
                }

                // Map State text to Region ID
                $stateCol = $this->gstHelper->getStateColumn() ?: 'state';
                $stateText = $profile->getData($stateCol);
                
                if ($stateText) {
                    $stateText = trim($stateText);
                    $region = $this->regionFactory->create()->loadByName($stateText, 'IN');
                    if ($region->getId()) {
                        $gstProfile->setRegionId((int) $region->getId());
                    }
                }
            }

            $this->gstProfileResource->save($gstProfile);

        } catch (\Exception $e) {
            $this->logger->error('Error syncing GST Vendor Profile: ' . $e->getMessage());
        }
    }
}
