<?php
namespace Vendor\Ads\Service;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * CPC Billing Service
 *
 * Uses atomic SQL for all mutations:
 *  - Wallet deduction: UPDATE ... WHERE balance >= cost  (race-condition safe)
 *  - Stats upsert: INSERT ... ON DUPLICATE KEY UPDATE   (no N+1 ORM)
 */
class BillingService
{
    public function __construct(
        private ResourceConnection $resource,
        private LoggerInterface $logger
    ) {}

    /**
     * Process a CPC click: deduct from wallet, update spent amount, track stat.
     */
    public function processClick(int $bidId): bool
    {
        $connection = $this->resource->getConnection();

        try {
            // 1. Load bid to get bid_amount, vendor_id, budget info
            $bidTable = $this->resource->getTableName('vendor_ads_bid');
            $bid = $connection->fetchRow(
                "SELECT bid_id, vendor_id, bid_amount, spent_amount, total_budget, daily_budget, is_active
                 FROM `{$bidTable}` WHERE bid_id = ?",
                [$bidId]
            );

            if (!$bid || !$bid['is_active']) {
                return false;
            }

            $cost     = (float)$bid['bid_amount'];
            $vendorId = (int)$bid['vendor_id'];

            // 2. Atomic wallet deduction — only succeeds if balance >= cost
            $walletTable = $this->resource->getTableName('vendor_ads_wallet');
            $rows = $connection->query(
                "UPDATE `{$walletTable}` SET balance = balance - ? WHERE vendor_id = ? AND balance >= ?",
                [$cost, $vendorId, $cost]
            )->rowCount();

            if ($rows === 0) {
                // Insufficient balance — deactivate bid atomically
                $connection->query(
                    "UPDATE `{$bidTable}` SET is_active = 0 WHERE bid_id = ?",
                    [$bidId]
                );
                return false;
            }

            // 3. Update bid spent_amount and auto-disable if total_budget reached
            $connection->query("
                UPDATE `{$bidTable}`
                SET
                    spent_amount   = spent_amount + ?,
                    last_charged_at = NOW(),
                    is_active      = CASE
                        WHEN total_budget > 0 AND (spent_amount + ?) >= total_budget THEN 0
                        ELSE is_active
                    END
                WHERE bid_id = ?
            ", [$cost, $cost, $bidId]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[Vendor_Ads][BillingService] processClick error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Track an impression or click in vendor_ads_stats using an atomic upsert.
     * Kept for backward compatibility with any caller that still uses it.
     */
    public function trackStat(int $bidId, string $type): void
    {
        $connection = $this->resource->getConnection();
        $statsTable = $this->resource->getTableName('vendor_ads_stats');
        $today      = date('Y-m-d');

        try {
            if ($type === 'impression') {
                $connection->query("
                    INSERT INTO `{$statsTable}` (bid_id, impressions, clicks, ctr, `date`)
                    VALUES (?, 1, 0, 0, ?)
                    ON DUPLICATE KEY UPDATE
                        impressions = impressions + 1,
                        ctr = IFNULL(ROUND(clicks / NULLIF(impressions, 0) * 100, 4), 0)
                ", [$bidId, $today]);
            } elseif ($type === 'click') {
                $connection->query("
                    INSERT INTO `{$statsTable}` (bid_id, impressions, clicks, ctr, `date`)
                    VALUES (?, 0, 1, 0, ?)
                    ON DUPLICATE KEY UPDATE
                        clicks = clicks + 1,
                        ctr = IFNULL(ROUND(clicks / NULLIF(impressions, 0) * 100, 4), 0)
                ", [$bidId, $today]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[Vendor_Ads][BillingService] trackStat error: ' . $e->getMessage());
        }
    }

    /**
     * Track a conversion.
     */
    public function trackConversion(int $bidId): void
    {
        $connection = $this->resource->getConnection();
        $statsTable = $this->resource->getTableName('vendor_ads_stats');
        $today      = date('Y-m-d');

        try {
            $connection->query("
                INSERT INTO `{$statsTable}` (bid_id, impressions, clicks, conversions, ctr, `date`)
                VALUES (?, 0, 0, 1, 0, ?)
                ON DUPLICATE KEY UPDATE
                    conversions = conversions + 1
            ", [$bidId, $today]);
        } catch (\Exception $e) {
            $this->logger->error('[Vendor_Ads][BillingService] trackConversion error: ' . $e->getMessage());
        }
    }
}
