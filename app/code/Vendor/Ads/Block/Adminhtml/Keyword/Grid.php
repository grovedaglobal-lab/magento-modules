<?php
namespace Vendor\Ads\Block\Adminhtml\Keyword;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class Grid extends Template
{
    public function __construct(
        Context $context,
        private ResourceConnection $resource,
        private StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * All keywords in search_query that have num_results > 0,
     * with bid stats and impression data joined in.
     * Sorted: keywords WITH bids first, then by popularity.
     */
    public function getKeywordData(): array
    {
        $connection = $this->resource->getConnection();
        $bidTable   = $this->resource->getTableName('vendor_ads_bid');
        $queryTable = $this->resource->getTableName('search_query');
        $statsTable = $this->resource->getTableName('vendor_ads_stats');
        $storeId    = (int)$this->storeManager->getStore()->getId();

        $select = $connection->select()
            ->from(['q' => $queryTable], [
                'query_id', 'query_text', 'popularity', 'num_results', 'store_id'
            ])
            ->joinLeft(
                ['b' => $bidTable],
                'q.query_id = b.query_id AND b.is_active = 1',
                [
                    'active_bids'  => new \Zend_Db_Expr('COUNT(DISTINCT b.bid_id)'),
                    'avg_bid'      => new \Zend_Db_Expr('ROUND(AVG(b.bid_amount), 2)'),
                    'max_bid'      => new \Zend_Db_Expr('ROUND(MAX(b.bid_amount), 2)'),
                ]
            )
            ->joinLeft(
                ['s' => $statsTable],
                's.bid_id = b.bid_id',
                [
                    'total_impressions' => new \Zend_Db_Expr('COALESCE(SUM(s.impressions), 0)'),
                    'total_clicks'      => new \Zend_Db_Expr('COALESCE(SUM(s.clicks), 0)'),
                ]
            )
            ->where('q.num_results > 0')
            ->group('q.query_id')
            ->order([
                new \Zend_Db_Expr('COUNT(DISTINCT b.bid_id) DESC'),
                'q.popularity DESC',
            ]);

        return $connection->fetchAll($select);
    }

    /**
     * Opportunity badge logic:
     *  - Untapped: no bids and good popularity → prime opportunity
     *  - High Demand: high popularity
     *  - Low Competition: 0 or 1 bids
     *  - Competitive: many bids
     */
    public function getOpportunity(array $row): array
    {
        $bids       = (int)$row['active_bids'];
        $popularity = (int)$row['popularity'];

        if ($bids === 0 && $popularity >= 10) {
            return ['label' => '💡 Untapped', 'color' => '#2d7a2d', 'bg' => '#e0f5e0'];
        }
        if ($popularity >= 100) {
            return ['label' => '🔥 High Demand', 'color' => '#8b3a00', 'bg' => '#fff0e0'];
        }
        if ($bids === 0) {
            return ['label' => '🔵 Low Traffic', 'color' => '#004a8b', 'bg' => '#e0f0ff'];
        }
        if ($bids >= 5) {
            return ['label' => '⚔️ Competitive', 'color' => '#6b0000', 'bg' => '#ffe0e0'];
        }
        return ['label' => '✅ Normal', 'color' => '#555', 'bg' => '#f5f5f5'];
    }

    public function getDetailsUrl(int $queryId): string
    {
        return $this->getUrl('vendor_ads/keyword/details', ['query_id' => $queryId]);
    }
}
