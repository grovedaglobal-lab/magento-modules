<?php
namespace Vendor\Ads\Cron;

use Vendor\Ads\Service\WalletRechargeProcessor;
use Vendor\Ads\Helper\Config as AdsConfig;
use Magento\Framework\App\ResourceConnection;

class ReconcileFailedRecharges
{
    protected $processor;
    protected $resource;
    protected $adsConfig;

    public function __construct(
        WalletRechargeProcessor $processor,
        ResourceConnection $resource,
        AdsConfig $adsConfig
    ) {
        $this->processor = $processor;
        $this->resource = $resource;
        $this->adsConfig = $adsConfig;
    }

    public function execute()
    {
        $connection = $this->resource->getConnection();
        $paymentTable = $this->resource->getTableName('vendor_ads_payment');

        // Find recharges that are 'success' (wallet credited) but have no Magento order/invoice
        $select = $connection->select()
            ->from($paymentTable)
            ->where('status = ?', 'success')
            ->where('(magento_order_id IS NULL OR magento_order_id = 0)')
            ->where('created_at > ?', date('Y-m-d H:i:s', strtotime('-7 days'))) // Only check recent ones
            ->limit(10); // Process in small batches

        $failedPayments = $connection->fetchAll($select);

        foreach ($failedPayments as $payment) {
            try {
                if (empty($payment['razorpay_payment_id'])) {
                    continue;
                }

                // This method will attempt to create the quote/order/invoice
                $this->processor->ensureOrderAndInvoice($payment, $payment['razorpay_payment_id']);
                
                // If success, update the record (re-fetching order ID if necessary is handled inside the processor or below)
                $orderId = $this->findOrderIdByQuoteId($payment); // Fallback lookup if not returned
                
                if ($orderId) {
                    $connection->update(
                        $paymentTable,
                        ['magento_order_id' => $orderId],
                        ['id = ?' => (int)$payment['id']]
                    );
                }
            } catch (\Exception $e) {
                // Log and continue to next
                \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)
                    ->error('Cron Reconcile Error for Payment ID ' . $payment['id'] . ': ' . $e->getMessage());
            }
        }
    }

    protected function findOrderIdByQuoteId($payment)
    {
        // Simple helper to find if an order already exists for this payment reference string
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $incrementId = 'vendor_ads_' . $payment['id'];
        
        return $connection->fetchOne(
            $connection->select()
                ->from($orderTable, ['entity_id'])
                ->where('increment_id = ?', $incrementId)
                ->limit(1)
        );
    }
}
