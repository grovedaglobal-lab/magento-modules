<?php
namespace Vendor\Marketplace\Block\Seller;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\App\ObjectManager; // For robustness
use Vendor\Marketplace\Model\VendorProfileFactory;

class Dashboard extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $orderCollectionFactory;
    protected $pricingHelper;
    protected $currentVendor = null;
    protected $vendorProfileFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        OrderCollectionFactory $orderCollectionFactory,
        PricingHelper $pricingHelper,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->pricingHelper = $pricingHelper;

        try {
            // Using ObjectManager to avoid constructor breaking changes for existing code
            // and to keep this patch minimal. In a full refactor, inject this.
            $this->vendorProfileFactory = ObjectManager::getInstance()->get(VendorProfileFactory::class);
            $this->_storeManager = $context->getStoreManager();
        } catch (\Exception $e) {
            // Silently handle or log to system.log if needed
        }

        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getVendor()
    {
        if (!$this->currentVendor) {
            $customerId = $this->customerSession->getCustomerId();
            if ($customerId) {
                // Check if vendor with this customer_id exists
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
                if ($vendor->getId()) {
                    $this->currentVendor = $vendor;
                }
            }
        }
        return $this->currentVendor;
    }

    public function getStatistics()
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            return $this->getEmptyStats();
        }

        $connection = $this->orderCollectionFactory->create()->getResource()->getConnection();
        $tableName = $this->orderCollectionFactory->create()->getResource()->getMainTable();
        $vendorId = $vendor->getId();

        // Determine date range from request param
        $period = $this->getRequest()->getParam('period', '30');
        $endDate = new \DateTime();
        if ($period === 'year') {
            $startDate = new \DateTime(date('Y-01-01'));
            $days = (int) $startDate->diff($endDate)->days + 1;
        } elseif ($period === '7') {
            $startDate = (clone $endDate)->modify('-6 days');
            $days = 7;
        } else {
            // Default: last 30 days
            $period = '30';
            $startDate = (clone $endDate)->modify('-29 days');
            $days = 30;
        }
        $startDateStr = $startDate->format('Y-m-d 00:00:00');
        $endDateStr   = $endDate->format('Y-m-d 23:59:59');

        // 1. General Aggregations filtered by the selected period
        $select = $connection->select()
            ->from(['main_table' => $tableName], [
                'sales'             => new \Zend_Db_Expr('SUM(main_table.subtotal)'),
                'commission'        => new \Zend_Db_Expr('SUM(main_table.commission_amount)'),
                'earnings'          => new \Zend_Db_Expr('SUM(main_table.vendor_earnings)'),
                'orders_total'      => new \Zend_Db_Expr('COUNT(*)'),
                'orders_processing' => new \Zend_Db_Expr("SUM(IF(main_table.status = 'processing', 1, 0))"),
                'orders_pending'    => new \Zend_Db_Expr("SUM(IF(main_table.status = 'pending', 1, 0))"),
                'orders_complete'   => new \Zend_Db_Expr("SUM(IF(main_table.status = 'complete', 1, 0))"),
                'orders_cancelled'  => new \Zend_Db_Expr("SUM(IF(main_table.status IN ('canceled', 'cancelled'), 1, 0))")
            ])
            ->join(
                ['so' => $connection->getTableName('sales_order')],
                'main_table.order_id = so.entity_id',
                []
            )
            ->where('main_table.vendor_id = ?', $vendorId)
            ->where('main_table.created_at >= ?', $startDateStr)
            ->where('main_table.created_at <= ?', $endDateStr)
            ->where('so.is_wallet_recharge = 0 OR so.is_wallet_recharge IS NULL');

        $data = $connection->fetchRow($select);

        // Normalize nulls to 0
        $stats = [
            'sales' => (float) $data['sales'],
            'commission' => (float) $data['commission'],
            'earnings' => (float) $data['earnings'],
            'payouts' => 0, // Pending implementation
            'remaining' => 0,
            'orders_total' => (int) $data['orders_total'],
            'orders_processing' => (int) $data['orders_processing'],
            'orders_pending' => (int) $data['orders_pending'],
            'orders_complete' => (int) $data['orders_complete'],
            'orders_cancelled' => (int) $data['orders_cancelled'],
            'avg_order_value' => 0,
            'total_customers' => 0,
            'chart_data' => [],
            'denied' => 0 // Added for consistency
        ];

        // Calculate Remaining
        $stats['remaining'] = $stats['earnings'] - $stats['payouts'];
        $stats['avg_order_value'] = $stats['orders_total'] > 0 ? $stats['sales'] / $stats['orders_total'] : 0;

        // 2. Chart Data (Daily Aggregation for selected period)
        $chartSelect = $connection->select()
            ->from(['main_table' => $tableName], [
                'order_date'   => new \Zend_Db_Expr('DATE(main_table.created_at)'),
                'daily_sales'  => new \Zend_Db_Expr('SUM(main_table.subtotal)'),
                'daily_orders' => new \Zend_Db_Expr('COUNT(*)')
            ])
            ->join(
                ['so' => $connection->getTableName('sales_order')],
                'main_table.order_id = so.entity_id',
                []
            )
            ->where('main_table.vendor_id = ?', $vendorId)
            ->where('main_table.created_at >= ?', $startDateStr)
            ->where('main_table.created_at <= ?', $endDateStr)
            ->where('so.is_wallet_recharge = 0 OR so.is_wallet_recharge IS NULL')
            ->group(new \Zend_Db_Expr('DATE(main_table.created_at)'));

        $chartResults = $connection->fetchAll($chartSelect);

        // Map results by date for easy lookup
        $dbData = [];
        foreach ($chartResults as $row) {
            $dbData[$row['order_date']] = $row;
        }

        // Fill complete date range
        $chartData = [];
        $current = clone $startDate;
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $chartData[] = [
                'date'     => $current->format('M d'),
                'raw_date' => $dateKey,
                'sales'    => isset($dbData[$dateKey]) ? (float) $dbData[$dateKey]['daily_sales'] : 0,
                'orders'   => isset($dbData[$dateKey]) ? (int) $dbData[$dateKey]['daily_orders'] : 0
            ];
            $current->modify('+1 day');
        }
        $stats['chart_data'] = $chartData;
        $stats['period'] = $period;

        // 3. Trends (Month over Month)
        // Current Month
        $startCurrent = date('Y-m-01 00:00:00');
        $endCurrent = date('Y-m-t 23:59:59');
        $currentStats = $this->getRangeStats($connection, $tableName, $vendorId, $startCurrent, $endCurrent);

        // Previous Month
        $startPrev = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $endPrev = date('Y-m-t 23:59:59', strtotime('-1 month'));
        $prevStats = $this->getRangeStats($connection, $tableName, $vendorId, $startPrev, $endPrev);

        // Calculate Trends
        $stats['trends'] = [
            'sales' => $this->calculateTrend($currentStats['sales'], $prevStats['sales']),
            'orders' => $this->calculateTrend($currentStats['orders'], $prevStats['orders']),
            'avg_order' => $this->calculateTrend(
                ($currentStats['orders'] > 0 ? $currentStats['sales'] / $currentStats['orders'] : 0),
                ($prevStats['orders'] > 0 ? $prevStats['sales'] / $prevStats['orders'] : 0)
            )
        ];

        return $stats;
    }

    private function getRangeStats($connection, $tableName, $vendorId, $start, $end)
    {
        $select = $connection->select()
            ->from(['main_table' => $tableName], [
                'sales' => new \Zend_Db_Expr('SUM(main_table.subtotal)'),
                'orders' => new \Zend_Db_Expr('COUNT(*)')
            ])
            ->join(
                ['so' => $connection->getTableName('sales_order')],
                'main_table.order_id = so.entity_id',
                []
            )
            ->where('main_table.vendor_id = ?', $vendorId)
            ->where('main_table.created_at >= ?', $start)
            ->where('main_table.created_at <= ?', $end)
            ->where('so.is_wallet_recharge = 0 OR so.is_wallet_recharge IS NULL');

        $data = $connection->fetchRow($select);
        return [
            'sales' => (float) $data['sales'],
            'orders' => (int) $data['orders']
        ];
    }

    private function calculateTrend($current, $prev)
    {
        if ($prev == 0) {
            return $current > 0 ? 100 : 0; // 100% growth if started from 0, else 0%
        }
        return (($current - $prev) / $prev) * 100;
    }

    private function getEmptyStats()
    {
        return [
            'sales' => 0,
            'commission' => 0,
            'earnings' => 0,
            'payouts' => 0,
            'remaining' => 0,
            'orders_total' => 0,
            'orders_processing' => 0,
            'orders_pending' => 0,
            'orders_complete' => 0,
            'orders_cancelled' => 0,
            'avg_order_value' => 0,
            'total_customers' => 0,
            'chart_data' => [],
            'trends' => ['sales' => 0, 'orders' => 0, 'avg_order' => 0],
            'denied' => 0 // Added for consistency
        ];
    }

    public function formatPrice($price)
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    /**
     * Get Display Name for Vendor
     * @return string
     */
    public function getVendorName()
    {
        $vendor = $this->getVendor();
        if ($vendor && $vendor->getShopUrl()) {
            return ucwords(str_replace('-', ' ', $vendor->getShopUrl()));
        }

        // Fallback to Customer Name
        if ($this->customerSession->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getName();
        }

        return __('Vendor');
    }

    /**
     * Get Vendor Profile URL
     * @return string
     */
    public function getVendorProfileUrl()
    {
        $vendor = $this->getVendor();
        if ($vendor && $vendor->getShopUrl()) {
            return $this->getUrl('shop-by-brand/' . $vendor->getShopUrl());
        }
        return $this->getBaseUrl();
    }

    /**
     * Get Media Url
     */
    public function getMediaUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * Get Avatar URL
     * @return string
     */
    public function getVendorAvatarUrl()
    {
        $vendor = $this->getVendor();
        if ($vendor && $vendor->getId()) {
            $profile = $this->vendorProfileFactory->create()->load($vendor->getId(), 'vendor_id');
            if ($profile->getId() && $profile->getLogo()) {
                return $this->getMediaUrl() . 'vendor/logo/' . $profile->getLogo();
            }
        }

        // Fallback to UI Avatar
        $name = urlencode($this->getVendorName());
        return "https://ui-avatars.com/api/?name={$name}&background=0D8ABC&color=fff&rounded=true&bold=true&size=64";
    }
    /**
     * Get Store Logo URL
     * @return string
     */
    public function getStoreLogoUrl()
    {
        $logoPath = $this->_scopeConfig->getValue(
            'design/header/logo_src',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($logoPath) {
            return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'logo/' . $logoPath;
        }
        return $this->getViewFileUrl('images/logo.svg');
    }

    public function getRecentOrders($limit = 10)
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            error_log("Dashboard Block: No vendor found for recent orders.");
            return [];
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('main_table.vendor_id', $vendor->getId());

        // Join sales_order to get increment_id and filter wallet recharges
        $collection->getSelect()->join(
            ['so' => $collection->getTable('sales_order')],
            'main_table.order_id = so.entity_id',
            ['increment_id']
        )->where('so.is_wallet_recharge = 0 OR so.is_wallet_recharge IS NULL');

        $collection->setOrder('main_table.created_at', 'DESC');
        $collection->setPageSize($limit);

        error_log("Dashboard Block: Vendor ID " . $vendor->getId() . " has " . $collection->getSize() . " total orders for activities.");

        return $collection;
    }

    /**
     * Get Page Title
     * @return string
     */
    public function getPageTitle()
    {
        $title = $this->pageConfig->getTitle()->get();
        if (!$title || $title == 'Vendor Dashboard') {
            return __('Marketplace Dashboard');
        }
        return $title;
    }

    /**
     * Get stable header title for the vendor shell by route.
     * This avoids generic titles (e.g. Dashboard) showing on non-dashboard pages.
     *
     * @return string
     */
    public function getPortalHeaderTitle()
    {
        $fullActionName = (string) $this->getRequest()->getFullActionName();

        $titleMap = [
            'vendor_marketplace_seller_dashboard' => (string) __('Dashboard'),
            'vendor_marketplace_order_history' => (string) __('Orders'),
            'vendor_marketplace_order_view' => (string) __('Order Details'),
            'vendor_marketplace_product_index' => (string) __('Products'),
            'vendor_marketplace_product_new' => (string) __('New Product'),
            'vendor_marketplace_product_edit' => (string) __('Edit Product'),
            'vendor_marketplace_coupon_index' => (string) __('Coupons'),
            'vendor_marketplace_coupon_edit' => (string) __('Edit Coupon'),
            'vendor_marketplace_report_coupon' => (string) __('Coupon Report'),
            'vendor_marketplace_report_earnings' => (string) __('Earnings Report'),
            'vendor_marketplace_review_index' => (string) __('Reviews'),
            'vendor_marketplace_shipping_index' => (string) __('Shipping'),
            'vendor_marketplace_news_list' => (string) __('Admin News'),
            'vendor_marketplace_notification_all' => (string) __('Notifications'),
            'vendor_marketplace_transaction_history' => (string) __('Transactions')
        ];

        if (isset($titleMap[$fullActionName])) {
            return $titleMap[$fullActionName];
        }

        $pageTitle = (string) $this->pageConfig->getTitle()->get();
        if ($pageTitle !== '') {
            return $pageTitle;
        }

        return (string) __('Vendor Portal');
    }
}
