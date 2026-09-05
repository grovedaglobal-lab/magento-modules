<?php
namespace Vendor\Marketplace\Block\Coupon;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Customer\Model\Session;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class ProductList extends Template
{
    protected $vendorFactory;
    protected $customerSession;
    protected $productCollectionFactory;
    protected $ruleRegistry;
    protected $imageHelper;
    protected $pricingHelper;

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
        $this->imageHelper = \Magento\Framework\App\ObjectManager::getInstance()->get(ImageHelper::class);
        $this->pricingHelper = \Magento\Framework\App\ObjectManager::getInstance()->get(PricingHelper::class);
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
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'thumbnail', 'small_image', 'image', 'visibility', 'status']);
        $collection->addAttributeToFilter('vendor_id', $vendor->getId());
        $collection->addAttributeToFilter('visibility', ['neq' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE]);
        $collection->setOrder('name', 'ASC');

        return $collection;
    }

    public function getChildProducts($product)
    {
        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return $product->getTypeInstance()->getUsedProducts($product);
        }
        return [];
    }

    public function getProductImageUrl($product, $width = 48, $height = 48)
    {
        try {
            return $this->imageHelper->init($product, 'product_thumbnail_image')
                ->resize($width, $height)
                ->getUrl();
        } catch (\Exception $e) {
            return $this->imageHelper->getDefaultPlaceholderUrl('thumbnail');
        }
    }

    public function getFormattedPrice($price)
    {
        return $this->pricingHelper->currency((float)$price, true, false);
    }

    public function getSelectedProductIds()
    {
        $rule = $this->ruleRegistry->registry('current_promo_quote_rule');
        if (!$rule) {
            return [];
        }

        $conditions = $rule->getActions()->getConditions();
        foreach ($conditions as $condition) {
            if ($condition->getType() == 'Magento\SalesRule\Model\Rule\Condition\Product' && $condition->getAttribute() == 'sku') {
                $skus = explode(',', $condition->getValue());
                return array_map('trim', $skus);
            }
        }

        return [];
    }
}
