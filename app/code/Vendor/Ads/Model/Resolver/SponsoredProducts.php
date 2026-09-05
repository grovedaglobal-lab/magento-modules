<?php
declare(strict_types=1);

namespace Vendor\Ads\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Vendor\Ads\Helper\SearchRanker;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Vendor\Ads\Model\ResourceModel\Bid\CollectionFactory as BidCollectionFactory;

class SponsoredProducts implements ResolverInterface
{
    /** @var SearchRanker */
    private $searchRanker;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ImageHelper */
    private $imageHelper;

    /** @var BidCollectionFactory */
    private $bidCollectionFactory;

    /** @var \Vendor\Ads\Service\BillingService */
    private $billingService;

    public function __construct(
        SearchRanker $searchRanker,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        ImageHelper $imageHelper,
        BidCollectionFactory $bidCollectionFactory,
        \Vendor\Ads\Service\BillingService $billingService
    ) {
        $this->searchRanker = $searchRanker;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->bidCollectionFactory = $bidCollectionFactory;
        $this->billingService = $billingService;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $keyword = trim($args['keyword'] ?? '');
        $limit = max(1, min((int)($args['limit'] ?? 4), 20));

        if (empty($keyword)) {
            throw new GraphQlInputException(__('keyword is required'));
        }

        // Get sponsored product IDs ranked by the ads engine
        $rawRanked = $this->searchRanker->getSponsoredProducts($keyword);
        $ranked = []; // [productId => score]
        $engineBidMap = []; // [productId => bidId]

        if (!empty($rawRanked)) {
            foreach ($rawRanked as $pId => $data) {
                // $data is ['bid_id' => X, 'score' => Y]
                $ranked[$pId] = (float)($data['score'] ?? 0);
                $engineBidMap[$pId] = (int)($data['bid_id'] ?? 0);
            }
        }

        $fallbackBidMap = [];

        if (empty($ranked)) {
            list($ranked, $fallbackBidMap) = $this->getTopActiveSponsoredProducts($limit);
        }

        if (empty($ranked)) {
            return ['items' => []];
        }

        // $ranked is [productId => score], sorted best-first; slice to limit
        arsort($ranked);
        $rankedSlice = array_slice($ranked, 0, $limit, true);
        $productIds = array_keys($rankedSlice);

        // Build a map: productId => bidId for click tracking
        $bidMap = [];
        if (!empty($engineBidMap)) {
            $bidMap = $engineBidMap;
        } elseif (!empty($fallbackBidMap)) {
            $bidMap = $fallbackBidMap;
        } else {
            $bidMap = $this->getBidMapForProducts($productIds, $keyword);
        }

        // Load products
        $this->searchCriteriaBuilder->addFilter('entity_id', $productIds, 'in');
        $criteria = $this->searchCriteriaBuilder->create();
        $products = $this->productRepository->getList($criteria)->getItems();

        // Build response preserving rank order
        $productMap = [];
        foreach ($products as $product) {
            $productMap[$product->getId()] = $product;
        }

        $baseMediaUrl = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );

        $items = [];
        foreach ($productIds as $productId) {
            if (!isset($productMap[$productId])) {
                continue;
            }
            $product = $productMap[$productId];
            $score = $rankedSlice[$productId] ?? 0;

            // Resolve image
            $imageUrl = '';
            $smallImage = $product->getSmallImage();
            if ($smallImage && $smallImage !== 'no_selection') {
                $imageUrl = $baseMediaUrl . 'catalog/product' . $smallImage;
            }

            // Resolve price
            $price = (float)$product->getFinalPrice();
            $currency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();

            // Resolve vendor data
            $vendorId = (int)$product->getData('vendor_id');
            $vendorName = (string)($product->getData('vendor_name') ?? '');

            $bidId = $bidMap[$productId] ?? 0;

            $items[] = [
                'product_id' => (int)$productId,
                'bid_id'     => $bidId,
                'url_key'    => $product->getUrlKey(),
                'name'       => $product->getName(),
                'sku'        => $product->getSku(),
                'image_url'  => $imageUrl,
                'price'      => $price,
                'currency'   => $currency,
                'vendor_id'  => $vendorId,
                'vendor_name' => $vendorName,
                'score'      => (float)$score,
            ];

            if ($bidId) {
                // Tracking the impression immediately as it's returned via GraphQL
                $this->billingService->trackStat((int)$bidId, 'impression');
            }
        }

        return ['items' => $items];
    }

    /**
     * Fallback: return top active sponsored products regardless of keyword.
     *
     * @return array [rankedProducts, bidMap]
     */
    private function getTopActiveSponsoredProducts(int $limit): array
    {
        $today = date('Y-m-d');
        $collection = $this->bidCollectionFactory->create();
        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.start_date', [['lteq' => $today], ['null' => true]]);
        $collection->addFieldToFilter('main_table.end_date', [['gteq' => $today], ['null' => true]]);

        // Guard: only serve bids whose parent campaign is active.
        $collection->getSelect()->join(
            ['camp' => $collection->getTable('vendor_ads_campaign')],
            'main_table.campaign_id = camp.campaign_id AND camp.status = 1',
            []
        );

        $collection->getSelect()->joinLeft(
            ['wlt' => $collection->getTable('vendor_ads_wallet')],
            'main_table.vendor_id = wlt.vendor_id',
            ['balance' => new \Zend_Db_Expr('IFNULL(wlt.balance, 0)')]
        );
        $collection->getSelect()->where('IFNULL(wlt.balance, 0) > 0');
        $collection->setOrder('bid_amount', 'DESC');

        $ranked = [];
        $bidMap = [];

        foreach ($collection as $bid) {
            $productId = (int)$bid->getProductId();
            if ($productId <= 0 || isset($ranked[$productId])) {
                continue;
            }

            $ranked[$productId] = (float)$bid->getBidAmount();
            $bidMap[$productId] = (int)$bid->getId();

            if (count($ranked) >= $limit) {
                break;
            }
        }

        return [$ranked, $bidMap];
    }

    /**
     * Returns a map of productId => bidId for the ranked products using the active bids.
     */
    private function getBidMapForProducts(array $productIds, string $keyword): array
    {
        if (empty($productIds)) {
            return [];
        }

        $today = date('Y-m-d');
        $collection = $this->bidCollectionFactory->create();
        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.product_id', ['in' => $productIds]);
        $collection->addFieldToFilter('main_table.start_date', [['lteq' => $today], ['null' => true]]);
        $collection->addFieldToFilter('main_table.end_date', [['gteq' => $today], ['null' => true]]);

        // Guard: only serve bids whose parent campaign is active.
        $collection->getSelect()->join(
            ['camp' => $collection->getTable('vendor_ads_campaign')],
            'main_table.campaign_id = camp.campaign_id AND camp.status = 1',
            []
        );

        $collection->getSelect()->join(
            ['kw' => $collection->getTable('search_query')],
            'main_table.query_id = kw.query_id',
            ['keyword' => 'query_text']
        );
        $collection->getSelect()->where(
            "(LOWER(kw.query_text) = ?) OR (kw.query_text LIKE ?)",
            strtolower($keyword),
            '%' . $keyword . '%'
        );

        $map = [];
        foreach ($collection as $bid) {
            $pid = (int)$bid->getProductId();
            // Keep the highest-bid for each product
            if (!isset($map[$pid])) {
                $map[$pid] = (int)$bid->getId();
            }
        }
        return $map;
    }
}
