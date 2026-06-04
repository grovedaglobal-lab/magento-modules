<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\Inventory\VendorSourceManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\VendorRepository;

class VendorProductSourceAssign implements ObserverInterface
{
    protected $vendorSourceManager;
    protected $vendorRepository;

    public function __construct(
        VendorSourceManager $vendorSourceManager,
        VendorRepository $vendorRepository
    ) {
        $this->vendorSourceManager = $vendorSourceManager;
        $this->vendorRepository = $vendorRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getProduct();

        // Check if product has a vendor_id
        // It might be set on the object itself during save
        $vendorId = $product->getData('vendor_id');

        if ($vendorId) {
            // Assign to Vendor Source
            // IMPORTANT: Only update if quantity is explicitly provided to avoid overwriting MSI stock with 0
            $qty = $product->getData('quantity_and_stock_status/qty')
                ?? $product->getData('stock_data/qty');

            if ($qty !== null) {
                $this->vendorSourceManager->assignProductToVendorSource($product->getSku(), $vendorId, (float) $qty);
            }
        }
    }
}
