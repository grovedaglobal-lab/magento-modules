<?php
namespace Vendor\Marketplace\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder\CollectionFactory as VendorOrderCollectionFactory;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class History extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderCollectionFactory;
    protected $pricingHelper;
    protected $orders;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderCollectionFactory $vendorOrderCollectionFactory,
        PricingHelper $pricingHelper,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderCollectionFactory = $vendorOrderCollectionFactory;
        $this->pricingHelper = $pricingHelper;
        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getOrders()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'vendor.order.history.pager'
            )->setCollection(
                    $this->getOrders()
                );
            $this->setChild('pager', $pager);
        }
        return $this;
    }

    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    public function getOrders()
    {
        if (!$this->orders) {
            $customerId = $this->customerSession->getCustomerId();
            if ($customerId) {
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
                if (!$vendor->getId()) {
                    return false;
                }

                $collection = $this->vendorOrderCollectionFactory->create();
                $collection->addFieldToFilter('vendor_id', $vendor->getId());
                $collection->setOrder('created_at', 'DESC');

                // Join with Sales Order explicitly
                $collection->getSelect()->join(
                    ['so' => $collection->getTable('sales_order')],
                    'main_table.order_id = so.entity_id',
                    ['increment_id', 'order_created_at' => 'created_at', 'customer_firstname', 'customer_lastname', 'base_grand_total', 'order_status' => 'status']
                )->where('so.is_wallet_recharge = 0 OR so.is_wallet_recharge IS NULL');

                // Apply Filters
                $params = $this->getRequest()->getParams();

                if (!empty($params['increment_id'])) {
                    $collection->getSelect()->where('so.increment_id LIKE ?', '%' . $params['increment_id'] . '%');
                }

                if (!empty($params['status'])) {
                    $collection->getSelect()->where('so.status = ?', $params['status']);
                }

                if (!empty($params['date_from'])) {
                    $dateFrom = date('Y-m-d 00:00:00', strtotime($params['date_from']));
                    $collection->getSelect()->where('so.created_at >= ?', $dateFrom);
                }

                if (!empty($params['date_to'])) {
                    $dateTo = date('Y-m-d 23:59:59', strtotime($params['date_to']));
                    $collection->getSelect()->where('so.created_at <= ?', $dateTo);
                }

                if (!empty($params['customer'])) {
                    $collection->getSelect()->where("CONCAT(so.customer_firstname, ' ', so.customer_lastname) LIKE ?", '%' . $params['customer'] . '%');
                }

                $this->orders = $collection;
            } else {
                return false;
            }
        }
        return $this->orders;
    }

    public function getOrderStats()
    {
        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        if (!$vendor->getId()) {
            return [];
        }

        $collection = $this->vendorOrderCollectionFactory->create();
        $connection = $collection->getResource()->getConnection();
        $tableName = $collection->getResource()->getMainTable();
        $salesOrderTable = $collection->getTable('sales_order');
        $vendorId = $vendor->getId();

        $select = $connection->select()
            ->from(['main_table' => $tableName], [])
            ->join(
                ['so' => $salesOrderTable],
                'main_table.order_id = so.entity_id',
                ['status' => 'status', 'count' => new \Zend_Db_Expr('COUNT(*)')]
            )
            ->where('main_table.vendor_id = ?', $vendorId)
            ->where('so.is_wallet_recharge = 0 OR so.is_wallet_recharge IS NULL')
            ->group('so.status');

        $results = $connection->fetchPairs($select);

        $stats = [
            'all' => array_sum($results),
            'pending' => $results['pending'] ?? 0,
            'processing' => $results['processing'] ?? 0,
            'on_hold' => $results['on_hold'] ?? 0,
            'complete' => $results['complete'] ?? 0,
            'closed' => $results['closed'] ?? 0,
            'canceled' => $results['canceled'] ?? 0,
            'denied' => 0 // Added for consistency
        ];

        return $stats;
    }

    public function getProductInfo($vendorOrder)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create(\Magento\Sales\Model\Order::class)->load($vendorOrder->getOrderId());
        $items = $order->getAllVisibleItems();

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            return [];
        }

        $vendorId = $vendor->getId();

        // Get vendor_id attribute ID
        $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
        $attribute = $eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, 'vendor_id');
        $attributeId = $attribute->getAttributeId();

        // Get resource connection
        $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resource->getConnection();

        $productDetails = [];
        foreach ($items as $item) {
            // Get product ID
            $productId = $item->getProductId();

            // Query vendor_id from catalog_product_entity_int
            $select = $connection->select()
                ->from($resource->getTableName('catalog_product_entity_int'), ['value'])
                ->where('entity_id = ?', $productId)
                ->where('attribute_id = ?', $attributeId)
                ->limit(1);

            $itemVendorId = $connection->fetchOne($select);

            // Only include items that belong to this vendor
            if ($itemVendorId == $vendorId) {
                $productDetails[] = [
                    'name' => $item->getName(),
                    'qty_ordered' => (int) $item->getQtyOrdered(),
                    'qty_canceled' => (int) $item->getQtyCanceled(),
                    'qty_invoiced' => (int) $item->getQtyInvoiced(),
                    'qty_shipped' => (int) $item->getQtyShipped(),
                    'qty_refunded' => (int) $item->getQtyRefunded(),
                    'options' => $item->getProductOptions()
                ];
            }
        }
        return $productDetails;
    }

    public function formatPrice($price)
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    public function getViewUrl($order)
    {
        return $this->getUrl('marketplace/order/view', ['id' => $order->getId()]);
    }
}
