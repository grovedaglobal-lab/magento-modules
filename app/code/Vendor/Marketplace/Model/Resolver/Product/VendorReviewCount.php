<?php
namespace Vendor\Marketplace\Model\Resolver\Product;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Helper\Rating as RatingHelper;

class VendorReviewCount implements ResolverInterface
{
    /**
     * @var RatingHelper
     */
    private $ratingHelper;

    /**
     * @param RatingHelper $ratingHelper
     */
    public function __construct(
        RatingHelper $ratingHelper
    ) {
        $this->ratingHelper = $ratingHelper;
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
            return 0;
        }

        $ratingData = $this->ratingHelper->getVendorRatingData($vendorId);
        return $ratingData['review_count'];
    }
}
