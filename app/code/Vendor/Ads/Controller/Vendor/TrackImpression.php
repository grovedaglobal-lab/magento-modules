<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * AJAX endpoint: vendor_ads/vendor/trackimpression?bid_ids=X,Y
 * Called by the frontend (Next.js) whenever sponsored products are shown.
 * Writes to vendor_ads_stats (upsert) and vendor_ads_logs (raw events).
 */
class TrackImpression extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Accept bid_ids as comma-separated list or single value
        $param  = $this->getRequest()->getParam('bid_ids') ?: $this->getRequest()->getParam('bid_id');
        $bidIds = array_filter(array_map('intval', explode(',', (string)$param)));

        if (empty($bidIds)) {
            return $result->setData(['success' => false, 'message' => 'Missing bid_id(s)']);
        }

        try {
            $connection = $this->resource->getConnection();
            $statsTable = $this->resource->getTableName('vendor_ads_stats');
            $logsTable  = $this->resource->getTableName('vendor_ads_logs');
            $bidTable   = $this->resource->getTableName('vendor_ads_bid');
            $today      = date('Y-m-d');
            $now        = date('Y-m-d H:i:s');

            // Load vendor_id + campaign_id for all bids in one query
            $bidMeta = [];
            if (!empty($bidIds)) {
                $rows = $connection->fetchAll(
                    "SELECT bid_id, vendor_id, campaign_id FROM `{$bidTable}` WHERE bid_id IN (?)",
                    [$bidIds]
                );
                foreach ($rows as $r) {
                    $bidMeta[(int)$r['bid_id']] = $r;
                }
            }

            $logRows = [];
            foreach ($bidIds as $bidId) {
                // Upsert impression into stats
                $connection->query("
                    INSERT INTO `{$statsTable}` (bid_id, impressions, clicks, conversions, ctr, `date`)
                    VALUES (?, 1, 0, 0, 0, ?)
                    ON DUPLICATE KEY UPDATE
                        impressions = impressions + 1,
                        ctr = ROUND(clicks / NULLIF(impressions + 1, 0) * 100, 4)
                ", [$bidId, $today]);

                // Collect log rows for bulk insert
                $meta = $bidMeta[$bidId] ?? null;
                if ($meta) {
                    $logRows[] = [
                        'bid_id'      => $bidId,
                        'vendor_id'   => $meta['vendor_id'],
                        'campaign_id' => $meta['campaign_id'],
                        'event_type'  => 'impression',
                        'created_at'  => $now,
                    ];
                }
            }

            // Bulk-insert impression events into vendor_ads_logs
            if (!empty($logRows)) {
                $connection->insertMultiple($logsTable, $logRows);
            }

            return $result->setData(['success' => true, 'count' => count($bidIds)]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
