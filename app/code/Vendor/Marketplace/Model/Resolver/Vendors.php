<?php
namespace Vendor\Marketplace\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

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

        $items = [];
        foreach ($collection as $vendor) {
            $vendorData = $vendor->getData();

            // Add rating data
            $ratingData = $this->ratingHelper->getVendorRatingData($vendor->getId());
            $vendorData['average_rating'] = $ratingData['average_rating'];
            $vendorData['review_count'] = $ratingData['review_count'];

            // Add full URLs for logo and banner
            if ($vendor->getLogo()) {
                $vendorData['logo_url'] = $mediaUrl . 'vendor/logo/' . $vendor->getLogo();
            } else {
                $vendorData['logo_url'] = null;
            }

            if ($vendor->getBanner()) {
                $vendorData['banner_url'] = $mediaUrl . 'vendor/banner/' . $vendor->getBanner();
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
