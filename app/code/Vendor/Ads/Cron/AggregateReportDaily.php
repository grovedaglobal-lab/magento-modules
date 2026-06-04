<?php
namespace Vendor\Ads\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Runs every 15 minutes.
 * 1. Aggregates vendor_ads_stats → vendor_ads_report_daily  (existing logic)
 * 2. Sets has_change_event = 1 on report_daily rows where a new bid version went live today
 */
class AggregateReportDaily
{
    public function __construct(
        private ResourceConnection $resource,
        private LoggerInterface    $logger
    ) {}

    public function execute(): void
    {
        try {
            $connection  = $this->resource->getConnection();
            $statsTable  = $this->resource->getTableName('vendor_ads_stats');
            $bidTable    = $this->resource->getTableName('vendor_ads_bid');
            $reportTable = $this->resource->getTableName('vendor_ads_report_daily');

            // ── Part 1: Aggregate stats → report_daily ──
            // Re-aggregate the last 30 days to ensure data accuracy and self-healing.
            $aggregateSql = "
                INSERT INTO `{$reportTable}`
                    (vendor_id, campaign_id, `date`, impressions, clicks, spend, ctr, has_change_event)
                SELECT
                    b.vendor_id,
                    b.campaign_id,
                    s.`date`                                                    AS `date`,
                    SUM(s.impressions)                                          AS impressions,
                    SUM(s.clicks)                                               AS clicks,
                    SUM(s.clicks * b.bid_amount)                                AS spend,
                    ROUND(
                        SUM(s.clicks) / NULLIF(SUM(s.impressions), 0) * 100,
                        4
                    )                                                           AS ctr,
                    0                                                           AS has_change_event
                FROM `{$statsTable}` s
                INNER JOIN `{$bidTable}` b ON b.bid_id = s.bid_id
                WHERE s.`date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY b.vendor_id, b.campaign_id, s.`date`
                ON DUPLICATE KEY UPDATE
                    impressions      = VALUES(impressions),
                    clicks           = VALUES(clicks),
                    spend            = VALUES(spend),
                    ctr              = VALUES(ctr),
                    updated_at       = NOW()
            ";
            $connection->query($aggregateSql);

            // ── Part 2: Set has_change_event = 1 where a new bid version started today ──
            // Matches report_daily rows by vendor_id + campaign_id + date of the version's started_at.
            $changeEventSql = "
                UPDATE `{$reportTable}` r
                INNER JOIN (
                    SELECT DISTINCT
                        b.vendor_id,
                        b.campaign_id,
                        DATE(b.started_at) AS change_date
                    FROM `{$bidTable}` b
                    WHERE b.started_at IS NOT NULL
                      AND b.version > 1
                      AND DATE(b.started_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) changes ON
                    r.vendor_id   = changes.vendor_id
                    AND r.campaign_id = changes.campaign_id
                    AND r.`date`      = changes.change_date
                SET r.has_change_event = 1
            ";
            $connection->query($changeEventSql);

            $this->logger->info('[Vendor_Ads] Daily report aggregation + change markers completed.');
        } catch (\Exception $e) {
            $this->logger->error('[Vendor_Ads] Aggregation error: ' . $e->getMessage());
        }
    }
}
