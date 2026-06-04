<?php
namespace Vendor\Marketplace\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;
use Vendor\Marketplace\Helper\Data as MarketplaceHelper;

class SoldBy extends Template
{
    protected $registry;
    protected $marketplaceHelper;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        MarketplaceHelper $marketplaceHelper,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->marketplaceHelper = $marketplaceHelper;
        parent::__construct($context, $data);
    }

    /**
     * Get current product
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    public function getProduct()
    {
        if (!$this->hasData('product')) {
            $this->setData('product', $this->registry->registry('current_product'));
        }
        return $this->getData('product');
    }

    /**
     * Get vendor name for current product
     *
     * @return string|null
     */
    public function getVendorName()
    {
        $product = $this->getProduct();
        if ($product) {
            return $this->marketplaceHelper->getVendorNameByProduct($product);
        }
        return null;
    }

    /**
     * Get vendor URL for current product
     *
     * @return string|null
     */
    public function getVendorUrl()
    {
        $product = $this->getProduct();
        if ($product) {
            return $this->marketplaceHelper->getVendorUrlByProduct($product);
        }
        return null;
    }
}
