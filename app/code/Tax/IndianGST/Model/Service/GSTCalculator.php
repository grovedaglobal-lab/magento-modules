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

        // 4. Get Tax Rate (Dynamically check product gst_rate attribute before default)
        $gstPercent = 18.00; // Default fallback
        if ($product) {
            $productGstRate = $product->getData('gst_rate');
            if ($productGstRate !== null && $productGstRate !== '') {
                $gstPercent = (float)$productGstRate;
            }
        }

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
