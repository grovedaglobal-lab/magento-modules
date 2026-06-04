<?php
namespace Vendor\Marketplace\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Vendor\Marketplace\Model\ResourceModel\VendorCommission\CollectionFactory;

class FeeCalculator extends AbstractHelper
{
    /**
     * @var CollectionFactory
     */
    protected $commissionCollectionFactory;

    /**
     * @param Context $context
     * @param CollectionFactory $commissionCollectionFactory
     */
    public function __construct(
        Context $context,
        CollectionFactory $commissionCollectionFactory
    ) {
        $this->commissionCollectionFactory = $commissionCollectionFactory;
        parent::__construct($context);
    }

    /**
     * Calculate Referral Fee for an Item
     * 
     * Logic:
     * Look for Category fee with Price Tier match
     *
     * @param float $price Item Price
     * @param int $categoryId Category ID
     * @return float Calculated Fee Amount
     */
    public function calculateReferralFee($price, $categoryId)
    {
        $fees = $this->getFeeRates($categoryId);
        $matchedFee = $this->matchPriceTier($fees, $price);
        if ($matchedFee) {
            return $this->applyFee($price, $matchedFee);
        }

        return 0.0;
    }

    /**
     * Get Fee Rates Collection
     * @param int|null $categoryId
     * @return \Vendor\Marketplace\Model\VendorCommission[]
     */
    protected function getFeeRates($categoryId)
    {
        $collection = $this->commissionCollectionFactory->create();

        if ($categoryId) {
            $collection->addFieldToFilter('category_id', $categoryId);
        } else {
             $collection->addFieldToFilter('category_id', ['null' => true]);
        }
        
        return $collection->getItems();
    }

    /**
     * Find matching fee based on price tier
     * @param array $fees
     * @param float $price
     * @return \Vendor\Marketplace\Model\VendorCommission|null
     */
    protected function matchPriceTier($fees, $price)
    {
        foreach ($fees as $fee) {
            $min = (float)$fee->getMinPrice();
            $max = $fee->getMaxPrice(); // Can be null

            if ($price >= $min) {
                if ($max === null || $price <= $max) {
                    return $fee;
                }
            }
        }
        return null;
    }

    /**
     * Apply Fee Logic
     * @param float $price
     * @param \Vendor\Marketplace\Model\VendorCommission $feeModel
     * @return float
     */
    protected function applyFee($price, $feeModel)
    {
        $amount = 0.0;
        
        // Percentage Fee
        if ($feeModel->getCommissionPercent() > 0) {
            $amount += ($price * ($feeModel->getCommissionPercent() / 100));
        }

        return $amount;
    }
}
