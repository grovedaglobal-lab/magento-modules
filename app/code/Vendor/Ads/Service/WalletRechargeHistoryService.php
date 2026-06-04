<?php
namespace Vendor\Ads\Service;

use Magento\Framework\App\ResourceConnection;

class WalletRechargeHistoryService
{
    protected $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function logRecharge(
        int $vendorId,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        string $source = 'vendor',
        ?string $note = null,
        ?string $razorpayPaymentId = null,
        ?int $magentoOrderId = null,
        ?string $taxMode = null
    ): void {
        if ($vendorId <= 0 || $amount <= 0) {
            return;
        }

        $connection = $this->resource->getConnection();
        $historyTable = $this->resource->getTableName('vendor_ads_wallet_recharge_history');

        $connection->insert($historyTable, [
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'source' => in_array($source, ['admin', 'vendor'], true) ? $source : 'vendor',
            'note' => $note,
            'razorpay_payment_id' => $razorpayPaymentId,
            'magento_order_id' => $magentoOrderId,
            'tax_mode' => $taxMode
        ]);
    }

    public function getVendorHistory(int $vendorId, int $limit = 50): array
    {
        if ($vendorId <= 0) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $historyTable = $this->resource->getTableName('vendor_ads_wallet_recharge_history');

        $select = $connection->select()
            ->from(['main_table' => $historyTable], [
                'history_id', 'vendor_id', 'amount', 'balance_before', 'balance_after', 
                'source', 'note', 'razorpay_payment_id', 'magento_order_id', 'tax_mode', 'created_at'
            ])
            ->joinLeft(
                ['so' => $this->resource->getTableName('sales_order')],
                'main_table.magento_order_id = so.entity_id',
                ['magento_increment_id' => 'so.increment_id']
            )
            ->joinLeft(
                ['vo' => $this->resource->getTableName('vendor_order')],
                'main_table.magento_order_id = vo.order_id AND main_table.vendor_id = vo.vendor_id',
                ['marketplace_order_id' => 'vo.entity_id']
            )
            ->where('main_table.vendor_id = ?', $vendorId)
            ->order('main_table.history_id DESC')
            ->limit(max(1, $limit));

        return $connection->fetchAll($select);
    }

    public function getAllHistory(int $limit = 200): array
    {
        $connection = $this->resource->getConnection();
        $historyTable = $this->resource->getTableName('vendor_ads_wallet_recharge_history');

        $select = $connection->select()
            ->from(['main_table' => $historyTable], [
                'history_id', 'vendor_id', 'amount', 'balance_before', 'balance_after', 
                'source', 'note', 'razorpay_payment_id', 'magento_order_id', 'tax_mode', 'created_at'
            ])
            ->joinLeft(
                ['so' => $this->resource->getTableName('sales_order')],
                'main_table.magento_order_id = so.entity_id',
                ['magento_increment_id' => 'so.increment_id']
            )
            ->order('main_table.history_id DESC')
            ->limit(max(1, $limit));

        return $connection->fetchAll($select);
    }
}