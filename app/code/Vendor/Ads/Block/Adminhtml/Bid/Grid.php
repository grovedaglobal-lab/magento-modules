<?php
namespace Vendor\Ads\Block\Adminhtml\Bid;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Helper\Config as AdsConfig;

class Grid extends Template
{
    protected $resource;
    protected $vendorResolver;
    protected $adsConfig;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        VendorResolverInterface $vendorResolver,
        AdsConfig $adsConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->resource       = $resource;
        $this->vendorResolver = $vendorResolver;
        $this->adsConfig      = $adsConfig;
    }

    /**
     * Get all bids. Vendor name is resolved dynamically via the configured vendor module.
     */
    public function getBidData()
    {
        $connection   = $this->resource->getConnection();
        $bidTable     = $this->resource->getTableName('vendor_ads_bid');
        $queryTable   = $this->resource->getTableName('search_query');

        $select = $connection->select()
            ->from(['b' => $bidTable], [
                'bid_id', 'vendor_id', 'product_id', 'bid_amount',
                'match_type', 'daily_budget', 'total_budget', 'spent_amount',
                'start_date', 'end_date', 'is_active', 'created_at',
                'version', 'parent_ad_id'
            ])
            ->joinLeft(['k' => $queryTable], 'b.query_id = k.query_id', ['keyword' => 'query_text'])
            ->order('b.bid_id DESC');

        $rows = $connection->fetchAll($select);

        // Resolve vendor name dynamically from the configured module
        foreach ($rows as &$row) {
            $row['vendor_name'] = $this->vendorResolver->getVendorName((int)$row['vendor_id']) ?? 'N/A';
        }

        return $rows;
    }

    public function getToggleStatusUrl($bidId, $isActive)
    {
        $action = $isActive ? 'deactivate' : 'activate';
        return $this->getUrl("vendor_ads/bid/{$action}", ['bid_id' => $bidId]);
    }

    /**
     * URL for the version history page.
     * The root of the version chain is either the bid itself (v1) or its parent_ad_id.
     */
    public function getVersionHistoryUrl(array $bid): string
    {
        $rootId = $bid['parent_ad_id'] ?: $bid['bid_id'];
        return $this->getUrl('vendor_ads/bid/versionHistory', ['parent_ad_id' => $rootId]);
    }

    /**
     * True if this bid has more than one version in its chain.
     */
    public function hasVersionHistory(array $bid): bool
    {
        return (int)($bid['version'] ?? 1) > 1 || !empty($bid['parent_ad_id']);
    }
}
