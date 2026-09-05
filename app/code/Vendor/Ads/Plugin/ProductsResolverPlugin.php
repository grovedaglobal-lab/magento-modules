<?php
declare(strict_types=1);

namespace Vendor\Ads\Plugin;

use Magento\CatalogGraphQl\Model\Resolver\Products;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Stabilize the products GraphQL query to prevent crashes when search/filter are missing.
 */
class ProductsResolverPlugin
{
    /**
     * @param Products $subject
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function beforeResolve(
        Products $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        // Core Magento throws "search or filter is required" if both are missing.
        // We ensure $args['search'] is at least an empty string if both are absent, 
        // effectively allowing an empty search instead of an unhandled exception.
        if (is_array($args) && !isset($args['search']) && !isset($args['filter'])) {
            $args['search'] = '';
        }

        return [$field, $context, $info, $value, $args];
    }
}
