<?php
namespace Tax\IndianGST\Model\Service;

use Tax\IndianGST\Model\ResourceModel\VendorProfile\CollectionFactory as VendorCollectionFactory;
use Tax\IndianGST\Model\ResourceModel\Rates\CollectionFactory as RatesCollectionFactory;

class GSTCalculator
{
    protected $vendorCollectionFactory;

    /**
     * @var \Tax\IndianGST\Helper\Data
     */
    protected $gstHelper;

    public function __construct(
        VendorCollectionFactory $vendorCollectionFactory,
        \Tax\IndianGST\Helper\Data $gstHelper
    ) {
        $this->vendorCollectionFactory = $vendorCollectionFactory;
        $this->gstHelper = $gstHelper;
    }

    /**
     * Calculate GST for a specific line item
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @return array
     */
    public function calculateGST($item, $shippingAddress)
    {
        $product = $item->getProduct();
        $price = $item->getRowTotal();

        // 1. Identify Vendor State
        $vendorAttr = $this->gstHelper->getProductVendorAttribute() ?: 'vendor_id';
        $vendorId = $product->getData($vendorAttr);
        $vendorStateId = null;

        if ($vendorId) {
            $vendor = $this->vendorCollectionFactory->create()
                ->addFieldToFilter('vendor_code', (string) $vendorId)
                ->getFirstItem();

            if ($vendor->getId()) {
                $vendorStateId = $vendor->getRegionId();
            }
        }

        // Fallback: If no vendor, assume Admin/Warehouse State from config
        if (!$vendorStateId) {
            $vendorStateId = $this->gstHelper->getConfigValue('tax_indiangst/general/admin_state');
        }

        // 2. Identify Customer State
        $customerStateId = $shippingAddress->getRegionId();

        // 3. Determine Tax Type (IGST or CGST/SGST)
        $isInterState = ($vendorStateId != $customerStateId);

        // 4. Get Tax Rate
        // For simplicity, we assume generic 18% if not defined, or fetch from Tax Class
        // In a real module, we would map Product Tax Class ID to a GST % table.
        $gstPercent = 18.00; // Default example

        // Advanced: Fetch from tax_indiangst_rates table if you implemented it fully
        // $rateModel = ... fetch by product->getTaxClassId()

        $taxDetails = [
            'type' => $isInterState ? 'IGST' : 'CGST_SGST',
            'rate_percent' => $gstPercent,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'igst_amount' => 0,
            'total_tax' => 0
        ];

        $taxAmount = ($price * $gstPercent) / 100;

        if ($isInterState) {
            $taxDetails['igst_amount'] = $taxAmount;
        } else {
            $taxDetails['cgst_amount'] = $taxAmount / 2;
            $taxDetails['sgst_amount'] = $taxAmount / 2;
        }

        $taxDetails['total_tax'] = $taxAmount;

        return $taxDetails;
    }
}
