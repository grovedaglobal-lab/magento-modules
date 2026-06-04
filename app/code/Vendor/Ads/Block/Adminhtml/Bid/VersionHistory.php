<?php
namespace Vendor\Ads\Block\Adminhtml\Bid;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Vendor\Ads\Api\VendorResolverInterface;

class VersionHistory extends Template
{
    public function __construct(
        Context $context,
        private ResourceConnection $resource,
        private VendorResolverInterface $vendorResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getParentAdId(): int
    {
        return (int)$this->getRequest()->getParam('parent_ad_id');
    }

    /**
     * Load all versions in the chain.
     * A chain is: all rows where bid_id = $parentAdId OR parent_ad_id = $parentAdId,
     * ordered oldest → newest (version ASC).
     */
    public function getVersionChain(): array
    {
        $parentAdId = $this->getParentAdId();
        $connection = $this->resource->getConnection();
        $bidTable   = $this->resource->getTableName('vendor_ads_bid');
        $queryTable = $this->resource->getTableName('search_query');

        $rows = $connection->fetchAll(
            "SELECT b.bid_id, b.vendor_id, b.version, b.bid_amount, b.daily_budget,
                    b.total_budget, b.match_type, b.targeting_type, b.is_active,
                    b.spent_amount, b.start_date, b.end_date, b.started_at, b.created_at,
                    q.query_text AS keyword
             FROM `{$bidTable}` b
             LEFT JOIN `{$queryTable}` q ON q.query_id = b.query_id
             WHERE b.bid_id = ? OR b.parent_ad_id = ?
             ORDER BY b.version ASC",
            [$parentAdId, $parentAdId]
        );

        // Annotate changed fields vs previous version
        $prev = null;
        foreach ($rows as &$row) {
            $row['changed_fields'] = [];
            if ($prev) {
                foreach (['bid_amount', 'daily_budget', 'total_budget', 'match_type',
                          'targeting_type', 'start_date', 'end_date', 'keyword'] as $f) {
                    if ($row[$f] != $prev[$f]) {
                        $row['changed_fields'][] = $f;
                    }
                }
            }
            $prev = $row;
        }
        unset($row);

        return $rows;
    }

    public function getVendorName(int $vendorId): string
    {
        return $this->vendorResolver->getVendorName($vendorId) ?? "Vendor #{$vendorId}";
    }

    public function getCompareUrl(): string
    {
        return $this->getUrl('vendor_ads/bid/versionCompare');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('vendor_ads/bid/index');
    }

    /**
     * Human-readable label for a changed field name.
     */
    public function fieldLabel(string $field): string
    {
        $map = [
            'bid_amount'     => 'Bid',
            'daily_budget'   => 'Daily Budget',
            'total_budget'   => 'Total Budget',
            'match_type'     => 'Match Type',
            'targeting_type' => 'Targeting',
            'start_date'     => 'Start Date',
            'end_date'       => 'End Date',
            'keyword'        => 'Keyword',
        ];
        return $map[$field] ?? $field;
    }
}
