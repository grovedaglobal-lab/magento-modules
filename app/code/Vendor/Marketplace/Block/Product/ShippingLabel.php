<?php
namespace Vendor\Marketplace\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as ProfileCollectionFactory;

class ShippingLabel extends Template
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var ProfileCollectionFactory
     */
    protected $_profileCollectionFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

    /**
     * @param Template\Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ProductRepositoryInterface $productRepository,
        ProfileCollectionFactory $profileCollectionFactory,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_productRepository = $productRepository;
        $this->_profileCollectionFactory = $profileCollectionFactory;
        $this->_registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Catalog\Model\Product|null
     */
    public function getProduct()
    {
        return $this->_registry->registry('current_product');
    }

    /**
     * @return \Vendor\Marketplace\Model\VendorProfile|bool
     */
    public function getVendorProfile()
    {
        $product = $this->getProduct();
        if (!$product)
            return false;

        $vendorId = $product->getVendorId();
        if (!$vendorId)
            return false;

        $collection = $this->_profileCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        return $collection->getFirstItem();
    }
}
