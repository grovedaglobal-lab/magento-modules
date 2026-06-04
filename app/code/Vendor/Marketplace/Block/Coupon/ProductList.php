<?php
namespace Vendor\Marketplace\Block\Coupon;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Customer\Model\Session;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class ProductList extends Template
{
    protected $vendorFactory;
    protected $customerSession;
    protected $productCollectionFactory;
    protected $ruleRegistry;

    public function __construct(
        Context $context,
        VendorFactory $vendorFactory,
        Session $customerSession,
        ProductCollectionFactory $productCollectionFactory,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->customerSession = $customerSession;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->ruleRegistry = $registry;
        parent::__construct($context, $data);
    }

    public function getVendorProducts()
    {
        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'thumbnail', 'visibility', 'status']);
        $collection->addAttributeToFilter('vendor_id', $vendor->getId());

        // Filter to show only parents (Visible in Catalog/Search) in main list list
        // 1 = Not Visible Individually
        $collection->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);

        return $collection;
    }

    public function getChildProducts($product)
    {
        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            // Get children that are enabled and belong to this vendor (usually implicit)
            // Function returns collection of used products
            return $product->getTypeInstance()->getUsedProducts($product);
        }
        return [];
    }

    public function getSelectedProductIds()
    {
        $rule = $this->ruleRegistry->registry('current_promo_quote_rule');
        if (!$rule) {
            return [];
        }

        // Parse rule conditions to find selected SKUs
        // This is tricky because conditions are serialized.
        // We look for: Product attribute combination -> SKU is one of ...

        $pIds = [];
        $conditions = $rule->getActions()->getConditions();
        foreach ($conditions as $condition) {
            if ($condition->getType() == 'Magento\SalesRule\Model\Rule\Condition\Product' && $condition->getAttribute() == 'sku') {
                $skus = explode(',', $condition->getValue());
                // Map SKUs back to IDs? Or just use SKUs in the form? 
                // Using SKUs is safer for the rule condition, but we need to check checkboxes.
                // Let's return SKUs and use SKUs in the checkbox value.
                return array_map('trim', $skus);
            }
        }

        return [];
    }
}
