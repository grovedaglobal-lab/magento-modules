<?php
namespace Vendor\Ads\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Vendor\Ads\Model\ResourceModel\Bid\CollectionFactory as BidCollectionFactory;

use Vendor\Ads\Api\Data\BidInterface;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;

#[\AllowDynamicProperties]
class SearchRanker extends AbstractHelper
{
    /** @var BidCollectionFactory */
    protected $bidCollectionFactory;

    /** @var \Magento\Search\Model\QueryFactory */
    protected $queryFactory;

    /** @var \Magento\Framework\Stdlib\DateTime\DateTime */
    protected $date;

    /** @var \Vendor\Ads\Model\ResourceModel\Stats\CollectionFactory */
    protected $statsCollectionFactory;

    /** @var \Magento\Framework\App\CacheInterface */
    protected $cache;

    /** @var \Magento\Framework\Serialize\SerializerInterface */
    protected $serializer;

    /** @var \Magento\Framework\Session\SessionManagerInterface */
    protected $session;

    /** @var \Vendor\Ads\Service\RankCalculator */
    protected $rankCalculator;

    /** @var \Vendor\Ads\Service\SynonymResolver */
    protected $synonymResolver;

    public function __construct(
        Context $context,
        BidCollectionFactory $bidCollectionFactory,
        \Magento\Search\Model\QueryFactory $queryFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Vendor\Ads\Model\ResourceModel\Stats\CollectionFactory $statsCollectionFactory,
        \Vendor\Ads\Service\RankCalculator $rankCalculator,
        \Vendor\Ads\Service\SynonymResolver $synonymResolver,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Session\SessionManagerInterface $session
    ) {
        parent::__construct($context);
        $this->bidCollectionFactory = $bidCollectionFactory;
        $this->queryFactory = $queryFactory;
        $this->date = $date;
        $this->statsCollectionFactory = $statsCollectionFactory;
        $this->rankCalculator = $rankCalculator;
        $this->synonymResolver = $synonymResolver;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->session = $session;
    }

    /**
     * Get ranked sponsored product IDs for a search string
     *
     * @param string $searchQuery
     * @return array [productId => score]
     */
    public function getSponsoredProducts($searchQuery)
    {
        $searchQuery = strtolower(trim($searchQuery));
        if (empty($searchQuery)) {
            return [];
        }

        try {
            // Expand with synonyms
            $searchTerms = $this->synonymResolver->getSynonyms($searchQuery);
            
            $cacheKey = 'VNDR_ADS_SEARCH_EXP_' . md5(implode('|', $searchTerms));
            $cachedData = $this->cache->load($cacheKey);
            
            if ($cachedData) {
                $results = $this->serializer->unserialize($cachedData);
            } else {
                $results = $this->calculateBaseRankings($searchTerms);
                $this->cache->save(
                    $this->serializer->serialize($results),
                    $cacheKey,
                    [\Magento\Framework\App\Cache\Type\Config::CACHE_TAG, 'VNDR_ADS'],
                    300
                );
            }

            return $this->applyTieBreaker($results);
        } catch (\Exception $e) {
            $this->_logger->error('Vendor Ads SearchRanker Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate base rankings (cacheable part)
     */
    protected function calculateBaseRankings($searchTerms)
    {
        $today = $this->date->gmtDate('Y-m-d');
        
        $collection = $this->bidCollectionFactory->create();
        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.start_date', [['lteq' => $today], ['null' => true]]);
        $collection->addFieldToFilter('main_table.end_date', [['gteq' => $today], ['null' => true]]);

        // Guard: only serve bids whose parent campaign is active.
        // This is a safety net for any data inconsistency.
        $collection->getSelect()->join(
            ['camp' => $collection->getTable('vendor_ads_campaign')],
            'main_table.campaign_id = camp.campaign_id AND camp.status = 1',
            []
        );

        // Join with search_query
        $collection->getSelect()->join(
            ['sq' => $collection->getTable('search_query')],
            'main_table.query_id = sq.query_id',
            ['query_text', 'popularity']
        );

        // Join with search_link to ensure ads are enabled for this term
        $collection->getSelect()->join(
            ['sl' => $collection->getTable('vendor_ads_search_link')],
            'main_table.query_id = sl.query_id',
            []
        )->where('sl.is_ads_enabled = 1');

        // Join with wallet
        $collection->getSelect()->joinLeft(
            ['wlt' => $collection->getTable('vendor_ads_wallet')],
            'main_table.vendor_id = wlt.vendor_id',
            ['balance' => new \Zend_Db_Expr('IFNULL(wlt.balance, 0)')]
        );
        $collection->getSelect()->where('IFNULL(wlt.balance, 0) > 0');

        $collection->getSelect()->where(
            "sq.query_text IN (?)",
            $searchTerms
        );

        $results = [];
        foreach ($collection as $bid) {
            if ($bid->getTotalBudget() > 0 && $bid->getSpentAmount() >= $bid->getTotalBudget()) {
                continue;
            }

            if ($bid->getDailyBudget() > 0) {
                $spentToday = $this->getSpentToday($bid->getId(), (float)$bid->getBidAmount(), $today);
                if ($spentToday >= $bid->getDailyBudget()) {
                    continue;
                }
            }

            // Get CTR for this bid
            $ctr = $this->getBidCtr($bid->getId());
            
            $score = $this->rankCalculator->calculate(
                (float)$bid->getBidAmount(),
                (int)$bid->getData('popularity'),
                $ctr
            );
            
            $results[$bid->getProductId()] = [
                'bid_id' => $bid->getId(),
                'base_score' => $score
            ];
        }

        return $results;
    }

    /**
     * Get CTR for a bid
     */
    protected function getBidCtr($bidId)
    {
        $statsCollection = $this->statsCollectionFactory->create();
        $statsCollection->addFieldToFilter('bid_id', $bidId);
        
        $statsCollection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'impressions' => 'SUM(impressions)',
                'clicks' => 'SUM(clicks)'
            ]);

        $data = $statsCollection->getConnection()->fetchRow($statsCollection->getSelect());
        $impressions = (int)($data['impressions'] ?? 0);
        $clicks = (int)($data['clicks'] ?? 0);

        return $impressions > 0 ? ($clicks / $impressions) : 0;
    }

    /**
     * Apply session-based tie-breaker and sort
     */
    protected function applyTieBreaker($results)
    {
        $sessionId = $this->session->getSessionId() ?: 'guest';

        foreach ($results as $productId => &$data) {
            $hash = crc32($sessionId . $productId);
            $tieBreaker = ($hash % 100) / 10000;
            $data['score'] = $data['base_score'] + $tieBreaker;
        }

        uasort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    /**
     * Get spent amount for a bid today
     */
    protected function getSpentToday($bidId, $bidAmount, $today)
    {
        $statsCollection = $this->statsCollectionFactory->create();
        $statsCollection->addFieldToFilter('bid_id', $bidId);
        $statsCollection->addFieldToFilter('date', $today);
        $stat = $statsCollection->getFirstItem();
        
        if ($stat->getId()) {
            return (float)$stat->getClicks() * $bidAmount;
        }
        return 0.0;
    }
}
