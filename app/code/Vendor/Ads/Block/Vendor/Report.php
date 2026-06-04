<?php
namespace Vendor\Ads\Block\Vendor;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Magento\Framework\App\ResourceConnection;

class Report extends Template
{
    public function __construct(
        Context $context,
        private CustomerSession $customerSession,
        private VendorResolverInterface $vendorResolver,
        private ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getVendorId(): ?int
    {
        return $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
    }

    // ── Filters from GET params ──────────────────────────────────────────

    public function getFilterDateFrom(): string
    {
        return $this->getRequest()->getParam('date_from', date('Y-m-d', strtotime('-30 days')));
    }

    public function getFilterDateTo(): string
    {
        return $this->getRequest()->getParam('date_to', date('Y-m-d'));
    }

    public function getFilterCampaignId(): ?int
    {
        $v = $this->getRequest()->getParam('campaign_id');
        return $v ? (int)$v : null;
    }

    // ── Campaign dropdown ────────────────────────────────────────────────

    public function getCampaigns(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return [];

        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName('vendor_ads_campaign');

        return $connection->fetchAll(
            $connection->select()
                ->from($table, ['campaign_id', 'name'])
                ->where('vendor_id = ?', $vendorId)
                ->order('name ASC')
        );
    }

    // ── Query builder ────────────────────────────────────────────────────

    private function buildBaseSelect(): \Magento\Framework\DB\Select
    {
        $vendorId   = $this->getVendorId();
        $connection = $this->resource->getConnection();
        $report     = $this->resource->getTableName('vendor_ads_report_daily');
        $campaign   = $this->resource->getTableName('vendor_ads_campaign');

        $select = $connection->select()
            ->from(['r' => $report])
            ->joinLeft(['c' => $campaign], 'r.campaign_id = c.campaign_id', ['campaign_name' => 'name'])
            ->where('r.vendor_id = ?', $vendorId)
            ->where('r.date >= ?', $this->getFilterDateFrom())
            ->where('r.date <= ?', $this->getFilterDateTo());

        if ($this->getFilterCampaignId()) {
            $select->where('r.campaign_id = ?', $this->getFilterCampaignId());
        }

        return $select;
    }

    // ── KPI Totals ───────────────────────────────────────────────────────

    public function getSummaryTotals(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'ctr' => 0];

        $connection = $this->resource->getConnection();
        $select     = $this->buildBaseSelect()->reset(\Zend_Db_Select::COLUMNS)->columns([
            'total_impressions' => new \Zend_Db_Expr('SUM(r.impressions)'),
            'total_clicks'      => new \Zend_Db_Expr('SUM(r.clicks)'),
            'total_spend'       => new \Zend_Db_Expr('SUM(r.spend)'),
        ]);

        $row = $connection->fetchRow($select);

        $impressions = (int)($row['total_impressions'] ?? 0);
        $clicks      = (int)($row['total_clicks'] ?? 0);
        $spend       = (float)($row['total_spend'] ?? 0);
        $ctr         = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;

        return compact('impressions', 'clicks', 'spend', 'ctr');
    }

    // ── Detailed table rows ──────────────────────────────────────────────

    public function getReportRows(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return [];

        $connection = $this->resource->getConnection();
        $select     = $this->buildBaseSelect()->order('r.date DESC');

        return $connection->fetchAll($select);
    }

    // ── Chart data (JSON) with change markers ────────────────────────────

    public function getChartDataJson(): string
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) {
            return json_encode(['labels' => [], 'impressions' => [], 'clicks' => [], 'spend' => [], 'change_markers' => []]);
        }

        $connection = $this->resource->getConnection();
        $select     = $this->buildBaseSelect()
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns([
                'date'             => 'r.date',
                'impressions'      => new \Zend_Db_Expr('SUM(r.impressions)'),
                'clicks'           => new \Zend_Db_Expr('SUM(r.clicks)'),
                'spend'            => new \Zend_Db_Expr('SUM(r.spend)'),
                'has_change_event' => new \Zend_Db_Expr('MAX(r.has_change_event)'),
            ])
            ->group('r.date')
            ->order('r.date ASC');

        $rows           = $connection->fetchAll($select);
        $labels         = $impressions = $clicks = $spend = $changeMarkers = [];

        foreach ($rows as $row) {
            $labels[]      = date('M d', strtotime($row['date']));
            $impressions[] = (int)$row['impressions'];
            $clicks[]      = (int)$row['clicks'];
            $spend[]       = round((float)$row['spend'], 2);

            // Dates with an ad update get added to change_markers for the graph annotation
            if ((int)$row['has_change_event'] === 1) {
                $changeMarkers[] = date('M d', strtotime($row['date']));
            }
        }

        return json_encode(compact('labels', 'impressions', 'clicks', 'spend', 'changeMarkers'));
    }

    // ── Smart Insights ───────────────────────────────────────────────────

    /**
     * Auto-generate actionable performance insights.
     * Compares last 7 days vs the prior 7 days from the aggregated report table.
     *
     * @return array [['type' => 'positive|warning|neutral', 'message' => '...']]
     */
    public function getSmartInsights(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return [];

        $connection  = $this->resource->getConnection();
        $reportTable = $this->resource->getTableName('vendor_ads_report_daily');

        $insights = [];

        try {
            // Period comparison: last 7 days vs prior 7 days
            $rows = $connection->fetchAll("
                SELECT
                    SUM(CASE WHEN `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN impressions ELSE 0 END) AS imp_recent,
                    SUM(CASE WHEN `date` >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                              AND `date`  <  DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN impressions ELSE 0 END) AS imp_prev,
                    SUM(CASE WHEN `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN clicks ELSE 0 END)      AS clk_recent,
                    SUM(CASE WHEN `date` >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                              AND `date`  <  DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN clicks ELSE 0 END)     AS clk_prev,
                    SUM(CASE WHEN `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN spend ELSE 0 END)       AS spend_recent,
                    SUM(CASE WHEN `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN has_change_event ELSE 0 END) AS recent_changes
                FROM `{$reportTable}`
                WHERE vendor_id = ?
            ", [$vendorId]);

            $data = $rows[0] ?? [];

            $impRecent  = (int)($data['imp_recent'] ?? 0);
            $impPrev    = (int)($data['imp_prev'] ?? 0);
            $clkRecent  = (int)($data['clk_recent'] ?? 0);
            $clkPrev    = (int)($data['clk_prev'] ?? 0);
            $spendRecent = (float)($data['spend_recent'] ?? 0);
            $recentChanges = (int)($data['recent_changes'] ?? 0);

            $ctrRecent = $impRecent > 0 ? ($clkRecent / $impRecent * 100) : 0;
            $ctrPrev   = $impPrev   > 0 ? ($clkPrev   / $impPrev   * 100) : 0;

            // Insight 1: CTR improvement after a change
            if ($recentChanges > 0 && $ctrRecent > $ctrPrev && $ctrPrev > 0) {
                $pct = round(($ctrRecent - $ctrPrev) / $ctrPrev * 100, 1);
                $insights[] = ['type' => 'positive', 'message' => "🚀 CTR improved by +{$pct}% after your recent update — keep it up!"];
            }

            // Insight 2: CTR dropped after a change
            if ($recentChanges > 0 && $ctrPrev > 0 && $ctrRecent < $ctrPrev) {
                $pct = round(($ctrPrev - $ctrRecent) / $ctrPrev * 100, 1);
                $insights[] = ['type' => 'warning', 'message' => "📉 CTR dropped by {$pct}% after the recent update. Consider revisiting your keyword or bid."];
            }

            // Insight 3: High spend, low clicks
            if ($spendRecent > 100 && $clkRecent < 5) {
                $insights[] = ['type' => 'warning', 'message' => "⚠️ High spend (₹" . number_format($spendRecent, 0) . ") with only {$clkRecent} click(s) this week. Consider reviewing your keyword targeting."];
            }

            // Insight 4: Impressions growing
            if ($impRecent > $impPrev && $impPrev > 0) {
                $pct = round(($impRecent - $impPrev) / $impPrev * 100, 1);
                if ($pct >= 20) {
                    $insights[] = ['type' => 'positive', 'message' => "👁 Impressions grew +{$pct}% this week vs last week — your ads are gaining visibility."];
                }
            }

            // Insight 5: No activity in last 7 days
            if ($impRecent === 0 && $clkRecent === 0) {
                $insights[] = ['type' => 'neutral', 'message' => "📊 No impressions or clicks in the last 7 days. Check that your campaign is active and your wallet has balance."];
            }

        } catch (\Exception $e) {
            // Non-fatal — insights are best-effort
        }

        return $insights;
    }

    public function getTrackClickUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/trackclick');
    }
}
