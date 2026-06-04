<?php
namespace Vendor\Marketplace\Model\Resolver\Product;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class VendorId implements ResolverInterface
{
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            return null;
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $value['model'];
        return $product->getData('vendor_id');
    }
}
