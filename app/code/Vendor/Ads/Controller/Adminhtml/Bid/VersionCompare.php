<?php
namespace Vendor\Ads\Controller\Adminhtml\Bid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * AJAX endpoint: returns a JSON diff between two bid versions.
 * URL: vendor_ads/bid/versionCompare?bid_id_a=X&bid_id_b=Y
 */
class VersionCompare extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_bids';

    // Fields shown in the comparison table
    private const COMPARE_FIELDS = [
        'bid_amount'   => 'CPC Bid (₹)',
        'daily_budget' => 'Daily Budget (₹)',
        'total_budget' => 'Total Budget (₹)',
        'match_type'   => 'Match Type',
        'targeting_type' => 'Targeting Type',
        'start_date'   => 'Start Date',
        'end_date'     => 'End Date',
        'is_active'    => 'Status',
    ];

    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result  = $this->jsonFactory->create();
        $bidIdA  = (int)$this->getRequest()->getParam('bid_id_a');
        $bidIdB  = (int)$this->getRequest()->getParam('bid_id_b');

        if (!$bidIdA || !$bidIdB) {
            return $result->setData(['success' => false, 'message' => 'Missing bid IDs']);
        }

        $connection = $this->resource->getConnection();
        $bidTable   = $this->resource->getTableName('vendor_ads_bid');
        $queryTable = $this->resource->getTableName('search_query');

        $rows = $connection->fetchAll(
            "SELECT b.bid_id, b.bid_amount, b.daily_budget, b.total_budget, b.match_type,
                    b.targeting_type, b.start_date, b.end_date, b.is_active,
                    b.version, b.started_at, q.query_text AS keyword
             FROM `{$bidTable}` b
             LEFT JOIN `{$queryTable}` q ON q.query_id = b.query_id
             WHERE b.bid_id IN (?, ?)",
            [$bidIdA, $bidIdB]
        );

        if (count($rows) !== 2) {
            return $result->setData(['success' => false, 'message' => 'Could not load both versions']);
        }

        // Index by bid_id so order matches A/B
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[$r['bid_id']] = $r;
        }
        $a = $indexed[$bidIdA];
        $b = $indexed[$bidIdB];

        $diff = [];
        foreach (self::COMPARE_FIELDS as $field => $label) {
            $valA   = $a[$field] ?? null;
            $valB   = $b[$field] ?? null;
            $changed = ($valA != $valB);
            $pctChange = null;

            // Calculate % change for numeric fields
            if ($changed && is_numeric($valA) && is_numeric($valB) && (float)$valA > 0) {
                $pctChange = round(((float)$valB - (float)$valA) / (float)$valA * 100, 1);
            }

            // Format is_active display
            if ($field === 'is_active') {
                $valA = $valA ? 'Active' : 'Inactive';
                $valB = $valB ? 'Active' : 'Inactive';
            }

            $diff[] = [
                'field'     => $label,
                'value_a'   => $valA ?? '—',
                'value_b'   => $valB ?? '—',
                'changed'   => $changed,
                'pct_change'=> $pctChange,
            ];
        }

        // Add keyword comparison separately
        $kwA = $a['keyword'] ?? '—';
        $kwB = $b['keyword'] ?? '—';
        array_unshift($diff, [
            'field'      => 'Keyword',
            'value_a'    => $kwA,
            'value_b'    => $kwB,
            'changed'    => ($kwA !== $kwB),
            'pct_change' => null,
        ]);

        return $result->setData([
            'success'   => true,
            'version_a' => 'v' . $a['version'],
            'version_b' => 'v' . $b['version'],
            'started_a' => $a['started_at'],
            'started_b' => $b['started_at'],
            'diff'      => $diff,
        ]);
    }
}
