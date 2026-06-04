<?php
namespace Vendor\Marketplace\Model\Resolver\Product;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as VendorProfileCollectionFactory;

class VendorName implements ResolverInterface
{
    /**
     * @var VendorProfileCollectionFactory
     */
    protected $vendorProfileCollectionFactory;

    /**
     * @param VendorProfileCollectionFactory $vendorProfileCollectionFactory
     */
    public function __construct(
        VendorProfileCollectionFactory $vendorProfileCollectionFactory
    ) {
        $this->vendorProfileCollectionFactory = $vendorProfileCollectionFactory;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            return null;
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $value['model'];
        $vendorId = $product->getData('vendor_id');

        if (!$vendorId) {
            return null;
        }

        $profile = $this->vendorProfileCollectionFactory->create()
            ->addFieldToFilter('vendor_id', $vendorId)
            ->getFirstItem();

        if ($profile && $profile->getId()) {
            return $profile->getShopName();
        }

        return null;
    }
}

