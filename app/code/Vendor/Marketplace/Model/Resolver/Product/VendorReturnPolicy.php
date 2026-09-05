<?php
namespace Vendor\Marketplace\Model\Resolver\Product;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Model\VendorProfileFactory;

class VendorReturnPolicy implements ResolverInterface
{
    /**
     * @var VendorProfileFactory
     */
    protected $vendorProfileFactory;

    /**
     * @param VendorProfileFactory $vendorProfileFactory
     */
    public function __construct(
        VendorProfileFactory $vendorProfileFactory
    ) {
        $this->vendorProfileFactory = $vendorProfileFactory;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
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

        $profile = $this->vendorProfileFactory->create()->load($vendorId, 'vendor_id');
        if ($profile && $profile->getId()) {
            return $profile->getData('return_policy');
        }

        return null;
    }
}
