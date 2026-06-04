<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\Inventory\VendorSourceManager;

class VendorSourceSync implements ObserverInterface
{
    protected $vendorSourceManager;

    public function __construct(
        VendorSourceManager $vendorSourceManager
    ) {
        $this->vendorSourceManager = $vendorSourceManager;
    }

    public function execute(Observer $observer)
    {
        /** @var \Vendor\Marketplace\Model\VendorProfile $profile */
        $profile = $observer->getEvent()->getObject();

        // Ensure it's a vendor profile object
        if ($profile && $profile->getVendorId()) {
            // Check if pickup address data is present (optional, but good for perf)
            // But we want to sync updates too, so just run it.
            $this->vendorSourceManager->processVendorSource($profile);
        }
    }
}
