<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;
use Vendor\Ads\Service\BillingService;

class TrackClick extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private ResourceConnection $resource,
        private BillingService $billingService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $bidId = (int)$this->getRequest()->getParam('bid_id');
        if (!$bidId) {
            return $result->setData(['success' => false, 'message' => 'Missing bid_id']);
        }

        try {
            $connection = $this->resource->getConnection();
            $statsTable = $this->resource->getTableName('vendor_ads_stats');
            $today      = date('Y-m-d');

            // Fix: CTR uses post-increment value (clicks+1) to be correct after update
            $connection->query("
                INSERT INTO `{$statsTable}` (bid_id, impressions, clicks, ctr, `date`)
                VALUES (?, 0, 1, 0, ?)
                ON DUPLICATE KEY UPDATE
                    clicks = clicks + 1,
                    ctr    = IFNULL(ROUND((clicks + 1) / NULLIF(impressions, 0) * 100, 4), 0)
            ", [$bidId, $today]);

            // Deduct wallet and handle budget — uses atomic SQL inside BillingService
            $this->billingService->processClick($bidId);

            // Write raw event to vendor_ads_logs
            $this->writeLog($bidId, 'click');

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Append a raw event record to vendor_ads_logs.
     */
    private function writeLog(int $bidId, string $eventType): void
    {
        try {
            $connection = $this->resource->getConnection();
            $logsTable  = $this->resource->getTableName('vendor_ads_logs');
            $bidTable   = $this->resource->getTableName('vendor_ads_bid');

            // Fetch vendor_id + campaign_id from the bid in one query
            $bidData = $connection->fetchRow(
                "SELECT vendor_id, campaign_id FROM `{$bidTable}` WHERE bid_id = ?",
                [$bidId]
            );
            if (!$bidData) {
                return;
            }

            $connection->insert($logsTable, [
                'bid_id'      => $bidId,
                'vendor_id'   => $bidData['vendor_id'],
                'campaign_id' => $bidData['campaign_id'],
                'event_type'  => $eventType,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Non-fatal: log write failure must NOT break the click response
        }
    }
}
