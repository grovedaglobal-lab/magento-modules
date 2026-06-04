<?php
namespace Vendor\Marketplace\Block\Seller;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class View extends Template
{
    protected $vendorFactory;
    protected $productCollectionFactory;
    protected $catalogProductVisibility;

    protected $imageBuilder;

    public function __construct(
        Context $context,
        VendorFactory $vendorFactory,
        CollectionFactory $productCollectionFactory,
        Visibility $catalogProductVisibility,
        array $data = []
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->catalogProductVisibility = $catalogProductVisibility;

        // Use ObjectManager to avoid constructor mismatch in layout blocks
        $this->imageBuilder = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Catalog\Block\Product\ImageBuilder::class);

        parent::__construct($context, $data);
    }

    public function getImageUrl($product, $imageId, $attributes = [])
    {
        return $this->imageBuilder->setProduct($product)
            ->setImageId($imageId)
            ->setAttributes($attributes)
            ->create()
            ->toHtml();
    }

    public function getVendor()
    {
        $vendorId = $this->getRequest()->getParam('id');
        if (!$vendorId) {
            return null;
        }
        $vendor = $this->vendorFactory->create()->load($vendorId);
        if ($vendor->getId()) {
            return $vendor;
        }
        return null;
    }

    public function getProductCollection()
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents();
        $collection->addFieldToFilter('vendor_id', $vendor->getId());
        $collection->addAttributeToFilter('status', ['in' => [Status::STATUS_ENABLED]]);
        $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());

        return $collection;
    }
}
