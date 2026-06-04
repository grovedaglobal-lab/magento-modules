<?php
namespace Vendor\Ads\Block\Adminhtml\Stats;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Vendor\Ads\Api\VendorResolverInterface;

class Grid extends Template
{
    protected $resource;
    protected $vendorResolver;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        VendorResolverInterface $vendorResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->resource       = $resource;
        $this->vendorResolver = $vendorResolver;
    }

    /**
     * Get stats per bid. Vendor name is resolved dynamically via the configured vendor module.
     */
    public function getStatsData()
    {
        $connection   = $this->resource->getConnection();
        $statsTable   = $this->resource->getTableName('vendor_ads_stats');
        $bidTable     = $this->resource->getTableName('vendor_ads_bid');
        $queryTable   = $this->resource->getTableName('search_query');

        $select = $connection->select()
            ->from(['s' => $statsTable], ['bid_id', 'date', 'impressions', 'clicks', 'conversions', 'ctr'])
            ->joinLeft(['b' => $bidTable], 's.bid_id = b.bid_id', ['vendor_id', 'bid_amount'])
            ->joinLeft(['k' => $queryTable], 'b.query_id = k.query_id', ['keyword' => 'query_text'])
            ->order('s.date DESC');

        $rows = $connection->fetchAll($select);

        foreach ($rows as &$row) {
            $row['vendor_name'] = $this->vendorResolver->getVendorName((int)$row['vendor_id']) ?? 'N/A';
        }

        return $rows;
    }
}
