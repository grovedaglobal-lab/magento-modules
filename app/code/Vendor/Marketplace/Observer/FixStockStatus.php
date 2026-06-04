<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ResourceConnection;

class FixStockStatus implements ObserverInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * Fix stock status directly via DB to ensure product visibility
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        $productId = (int) $product->getId();
        $websiteIds = $product->getWebsiteIds();

        // If no websites, fallback to default
        if (empty($websiteIds)) {
            $websiteIds = [1];
        }

        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('cataloginventory_stock_status');

        foreach ($websiteIds as $websiteId) {
            // Check if record exists
            $select = $connection->select()
                ->from($tableName)
                ->where('product_id = ?', $productId)
                ->where('website_id = ?', $websiteId)
                ->where('stock_id = ?', 1);

            $existing = $connection->fetchRow($select);

            if (!$existing) {
                // Determine qty and status
                $stockData = $product->getStockData();
                $qty = isset($stockData['qty']) ? (float) $stockData['qty'] : 0;
                $isInStock = isset($stockData['is_in_stock']) ? (int) $stockData['is_in_stock'] : 0;

                // Check standard object data
                if ($product->getData('quantity_and_stock_status')) {
                    $qas = $product->getData('quantity_and_stock_status');
                    $qty = isset($qas['qty']) ? (float) $qas['qty'] : $qty;
                    $isInStock = isset($qas['is_in_stock']) ? (int) $qas['is_in_stock'] : $isInStock;
                }

                // Prepare insert
                $bind = [
                    'product_id' => $productId,
                    'website_id' => $websiteId,
                    'stock_id' => 1,
                    'qty' => $qty,
                    'stock_status' => $isInStock
                ];

                try {
                    $connection->insert($tableName, $bind);
                } catch (\Exception $e) {
                    // Silently ignore if already exists (race condition)
                }
            }
        }
    }
}
