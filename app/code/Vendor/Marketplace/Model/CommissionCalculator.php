<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Model\ResourceModel\VendorCommission\CollectionFactory as CommissionCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CommissionCalculator
{
    protected $commissionCollectionFactory;
    protected $scopeConfig;
    protected $categoryRepository;

    // Fallback default if no config is set
    const FALLBACK_PERCENT = 10.00;

    public function __construct(
        CommissionCollectionFactory $commissionCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository
    ) {
        $this->commissionCollectionFactory = $commissionCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Calculate commission for an order item
     * Priority: Category + Price Range specific commission > System default commission
     */
    public function calculate($vendorId, $item)
    {
        $amount = $this->getBasisAmount($item);
        $product = $item->getProduct();
        $categoryIds = $product->getCategoryIds();
        $productPrice = $item->getPrice();

        if (!empty($categoryIds)) {
            foreach ($categoryIds as $categoryId) {
                $commissionRule = $this->getCommissionRule($categoryId, $productPrice);
                if ($commissionRule) {
                    $categoryName = "Default";
                    try {
                        $category = $this->categoryRepository->get($categoryId);
                        $categoryName = $category->getName();
                    } catch (\Exception $e) {
                    }

                    return [
                        'amount' => $this->applyFormula(
                            $amount,
                            $commissionRule['commission_percent'],
                            $commissionRule['fixed_amount']
                        ),
                        'percent' => $commissionRule['commission_percent'],
                        'category' => $categoryName
                    ];
                }
            }
        }

        $defaultPercent = $this->getDefaultCommission($item->getStoreId());
        return [
            'amount' => $this->applyFormula($amount, $defaultPercent, 0),
            'percent' => $defaultPercent,
            'category' => 'Default'
        ];
    }

    /**
     * Get the basis amount for commission based on settings
     */
    protected function getBasisAmount($item)
    {
        $storeId = $item->getStoreId();
        $basis = $this->scopeConfig->getValue(
            'vendor_marketplace/commission/calculation_basis',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $afterDiscount = $this->scopeConfig->getValue(
            'vendor_marketplace/commission/after_discount',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // Basis Amount: Row Total Excl. Tax or Incl. Tax
        $amount = ($basis === 'include_tax') ? $item->getRowTotalInclTax() : $item->getRowTotal();

        // Apply Before/After Discount
        if ($afterDiscount) {
            $amount -= (float) $item->getDiscountAmount();
        }

        return max(0, $amount);
    }

    /**
     * Get commission rule based on category and price range
     * Returns the first matching rule
     */
    protected function getCommissionRule($categoryId, $productPrice)
    {
        $collection = $this->commissionCollectionFactory->create();
        $collection->addFieldToFilter('category_id', $categoryId);

        // Filter by price range
        // Product price must be >= min_price
        $collection->getSelect()->where(
            'min_price <= ?',
            $productPrice
        );

        // Product price must be <= max_price (or max_price is NULL for unlimited)
        $collection->getSelect()->where(
            'max_price IS NULL OR max_price >= ?',
            $productPrice
        );

        $collection->setPageSize(1);
        $collection->setOrder('commission_percent', 'DESC'); // Highest commission first

        $rule = $collection->getFirstItem();
        if ($rule->getId()) {
            return [
                'commission_percent' => $rule->getData('commission_percent'),
                'fixed_amount' => $rule->getData('fixed_amount') ?: 0
            ];
        }

        return null;
    }

    /**
     * Get default commission from system configuration
     */
    protected function getDefaultCommission($storeId = null)
    {
        $defaultPercent = $this->scopeConfig->getValue(
            'vendor_marketplace/commission/default_percent',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $defaultPercent !== null ? (float) $defaultPercent : self::FALLBACK_PERCENT;
    }

    /**
     * Apply commission formula: (amount * percent / 100) + fixed_amount
     */
    private function applyFormula($amount, $percent, $fixedAmount = 0)
    {
        return ($amount * ($percent / 100)) + $fixedAmount;
    }
}
