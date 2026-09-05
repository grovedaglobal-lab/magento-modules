<?php
namespace Vendor\Marketplace\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Helper\Rating as RatingHelper;

class Vendors implements ResolverInterface
{
    /**
     * @var CollectionFactory
     */
    private $vendorCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RatingHelper
     */
    private $ratingHelper;

    /**
     * @var array
     */
    private static $ratingCache = [];

    /**
     * @param CollectionFactory $vendorCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param RatingHelper $ratingHelper
     */
    public function __construct(
        CollectionFactory $vendorCollectionFactory,
        StoreManagerInterface $storeManager,
        RatingHelper $ratingHelper
    ) {
        $this->vendorCollectionFactory = $vendorCollectionFactory;
        $this->storeManager = $storeManager;
        $this->ratingHelper = $ratingHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $pageSize = $args['pageSize'] ?? 20;
        $currentPage = $args['currentPage'] ?? 1;

        $collection = $this->vendorCollectionFactory->create();
        $collection->addFieldToFilter('status', 1); // Only active vendors
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        // Check if expensive rating calculation or media URLs are requested in the query
        $fields = method_exists($info, 'getFieldSelection') ? $info->getFieldSelection(2) : [];
        $itemsFields = $fields['items'] ?? [];
        $needsRating = empty($itemsFields) || isset($itemsFields['average_rating']) || isset($itemsFields['review_count']);
        $needsLogo = empty($itemsFields) || isset($itemsFields['logo_url']);
        $needsBanner = empty($itemsFields) || isset($itemsFields['banner_url']);

        $items = [];
        foreach ($collection as $vendor) {
            $vendorData = $vendor->getData();
            $vendorId = (int)$vendor->getId();
            // Redact legal signatures and tax IDs from bulk vendor listings
            $vendorData['signature'] = null;
            $vendorData['signature_url'] = null;
            $vendorData['pan_number'] = null;
            $vendorData['business_license'] = null;

            if ($needsRating) {
                if (!isset(self::$ratingCache[$vendorId])) {
                    self::$ratingCache[$vendorId] = $this->ratingHelper->getVendorRatingData($vendorId);
                }
                $vendorData['average_rating'] = self::$ratingCache[$vendorId]['average_rating'];
                $vendorData['review_count'] = self::$ratingCache[$vendorId]['review_count'];
            } else {
                $vendorData['average_rating'] = 0;
                $vendorData['review_count'] = 0;
            }

            if ($needsLogo) {
                $vendorData['logo_url'] = $vendor->getLogo() ? $mediaUrl . 'vendor/logo/' . $vendor->getLogo() : null;
            } else {
                $vendorData['logo_url'] = null;
            }

            if ($needsBanner) {
                $vendorData['banner_url'] = $vendor->getBanner() ? $mediaUrl . 'vendor/banner/' . $vendor->getBanner() : null;
            } else {
                $vendorData['banner_url'] = null;
            }

            $items[] = $vendorData;
        }

        return [
            'items' => $items,
            'total_count' => $collection->getSize()
        ];
    }
}
