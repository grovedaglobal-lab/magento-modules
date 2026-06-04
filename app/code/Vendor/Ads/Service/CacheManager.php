<?php
namespace Vendor\Ads\Service;


use Vendor\Ads\Helper\SearchRanker;
use Magento\Framework\App\CacheInterface;

class CacheManager
{
    /** @var \Vendor\Ads\Model\ResourceModel\Bid\CollectionFactory */
    protected $bidCollectionFactory;

    /** @var SearchRanker */
    protected $searchRanker;

    /** @var CacheInterface */
    protected $cache;

    public function __construct(
        \Vendor\Ads\Model\ResourceModel\Bid\CollectionFactory $bidCollectionFactory,
        SearchRanker $searchRanker,
        CacheInterface $cache
    ) {
        $this->bidCollectionFactory = $bidCollectionFactory;
        $this->searchRanker = $searchRanker;
        $this->cache = $cache;
    }

    /**
     * Precompute rankings for all active keywords
     */
    public function precomputeAll()
    {
        $bids = $this->bidCollectionFactory->create();
        $bids->addFieldToFilter('main_table.is_active', 1);

        // Only precompute for bids whose parent campaign is active.
        $bids->getSelect()->joinInner(
            ['camp' => $bids->getTable('vendor_ads_campaign')],
            'main_table.campaign_id = camp.campaign_id AND camp.status = 1',
            []
        );

        $bids->getSelect()->joinInner(
            ['kw' => $bids->getTable('search_query')],
            'main_table.query_id = kw.query_id',
            ['keyword' => 'query_text']
        )->distinct(true);

        foreach ($bids as $bid) {
            $this->precomputeKeyword($bid->getData('keyword'));
        }
    }

    /**
     * Precompute rankings for a single keyword
     */
    public function precomputeKeyword($keyword)
    {
        // This will trigger the getSponsoredProducts logic which handles caching internally
        $this->searchRanker->getSponsoredProducts($keyword);
    }
}
