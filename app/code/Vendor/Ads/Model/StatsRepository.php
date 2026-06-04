<?php
namespace Vendor\Ads\Model;

use Vendor\Ads\Api\StatsRepositoryInterface;
use Vendor\Ads\Model\ResourceModel\Stats\CollectionFactory as StatsCollectionFactory;
use Vendor\Ads\Model\ResourceModel\Bid\CollectionFactory as BidCollectionFactory;

class StatsRepository implements StatsRepositoryInterface
{
    protected $statsCollectionFactory;
    protected $bidCollectionFactory;

    public function __construct(
        StatsCollectionFactory $statsCollectionFactory,
        BidCollectionFactory $bidCollectionFactory
    ) {
        $this->statsCollectionFactory = $statsCollectionFactory;
        $this->bidCollectionFactory = $bidCollectionFactory;
    }

    public function getVendorStats($vendorId, $fromDate = null, $toDate = null)
    {
        $bidCollection = $this->bidCollectionFactory->create();
        $bidCollection->addFieldToFilter('vendor_id', $vendorId);
        $bidIds = $bidCollection->getColumnValues('bid_id');

        if (empty($bidIds)) {
            return $this->getEmptyStats();
        }

        $statsCollection = $this->statsCollectionFactory->create();
        $statsCollection->addFieldToFilter('bid_id', ['in' => $bidIds]);

        if ($fromDate) {
            $statsCollection->addFieldToFilter('date', ['gteq' => $fromDate]);
        }
        if ($toDate) {
            $statsCollection->addFieldToFilter('date', ['lteq' => $toDate]);
        }

        $statsCollection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'impressions' => 'SUM(impressions)',
                'clicks' => 'SUM(clicks)',
                'conversions' => 'SUM(conversions)'
            ]);

        $data = $statsCollection->getConnection()->fetchRow($statsCollection->getSelect());
        
        $impressions = (int)($data['impressions'] ?? 0);
        $clicks = (int)($data['clicks'] ?? 0);
        $ctr = $impressions > 0 ? ($clicks / $impressions) : 0;

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => (int)($data['conversions'] ?? 0),
            'ctr' => $ctr
        ];
    }

    public function getBidStats($bidId)
    {
        $statsCollection = $this->statsCollectionFactory->create();
        $statsCollection->addFieldToFilter('bid_id', $bidId);

        $statsCollection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'impressions' => 'SUM(impressions)',
                'clicks' => 'SUM(clicks)',
                'conversions' => 'SUM(conversions)'
            ]);

        $data = $statsCollection->getConnection()->fetchRow($statsCollection->getSelect());
        
        $impressions = (int)($data['impressions'] ?? 0);
        $clicks = (int)($data['clicks'] ?? 0);
        $ctr = $impressions > 0 ? ($clicks / $impressions) : 0;

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => (int)($data['conversions'] ?? 0),
            'ctr' => $ctr
        ];
    }

    protected function getEmptyStats()
    {
        return [
            'impressions' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'ctr' => 0
        ];
    }
}
