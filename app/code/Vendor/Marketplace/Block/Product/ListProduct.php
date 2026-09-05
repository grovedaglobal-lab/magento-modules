<?php
namespace Vendor\Marketplace\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class ListProduct extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $productCollectionFactory;
    protected $pricingHelper;
    protected $products;

    protected $orderItemCollectionFactory;
    protected $cachedSalesStats = null;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        CollectionFactory $productCollectionFactory,
        PricingHelper $pricingHelper,
        \Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory $orderItemCollectionFactory,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->pricingHelper = $pricingHelper;
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getVendorProducts()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'vendor.product.list.pager'
            )->setAvailableLimit([10 => 10, 20 => 20, 50 => 50, 100 => 100])
             ->setShowPerPage(true)
             ->setCollection($this->getVendorProducts());
            
            $this->setChild('pager', $pager);
        }
        return $this;
    }

    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    public function getVendorProducts()
    {
        if (!$this->products) {
            $customerId = $this->customerSession->getCustomerId();
            if (!$customerId) {
                return false;
            }

            try {
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
                if (!$vendor->getId()) {
                    error_log("VendorMarketplace: No vendor found for customer ID " . $customerId);
                    return false;
                }

                $collection = $this->productCollectionFactory->create();
                // Crucial: Bypass frontend stock filter so out-of-stock / qty 0 products are visible in vendor portal
                $collection->setFlag('has_stock_status_filter', true);

                $collection->addAttributeToSelect([
                    "name",
                    "sku",
                    "price",
                    "status",
                    "thumbnail",
                    "small_image",
                    "image",
                    "vendor_id",
                    "type_id"
                ]);
                $collection->addAttributeToFilter('vendor_id', $vendor->getId());
                $collection->addAttributeToFilter('sku', ['neq' => 'wallet-recharge']);
                $collection->setOrder('created_at', 'DESC');

                // Join MSI and legacy stock accurately
                $sourceItemTable = $collection->getTable('inventory_source_item');
                $legacyStockTable = $collection->getTable('cataloginventory_stock_item');

                $collection->getSelect()->joinLeft(
                    ['isi' => $sourceItemTable],
                    'isi.sku = e.sku',
                    ['msi_qty' => 'isi.quantity', 'msi_status' => 'isi.status']
                )->joinLeft(
                    ['csi' => $legacyStockTable],
                    'csi.product_id = e.entity_id AND csi.stock_id = 1',
                    ['legacy_qty' => 'csi.qty', 'legacy_is_in_stock' => 'csi.is_in_stock']
                )->columns([
                    'qty' => new \Zend_Db_Expr('COALESCE(isi.quantity, csi.qty, 0)')
                ])->group('e.entity_id');

                // Join Attribute Set Name
                $collection->joinField(
                    'attribute_set_name',
                    'eav_attribute_set',
                    'attribute_set_name',
                    'attribute_set_id=attribute_set_id',
                    null,
                    'left'
                );

                // Apply Filters
                $params = $this->getRequest()->getParams();

                if (!empty($params['name'])) {
                    $collection->addAttributeToFilter('name', ['like' => '%' . $params['name'] . '%']);
                }
                if (!empty($params['sku'])) {
                    $collection->addAttributeToFilter('sku', ['like' => '%' . $params['sku'] . '%']);
                }
                
                // Status Filter
                if (isset($params['status']) && $params['status'] !== '') {
                    $collection->addAttributeToFilter('status', $params['status']);
                }

                // Keyword Search (Name OR SKU)
                if (!empty($params['keyword'])) {
                    $collection->addAttributeToFilter([
                        ['attribute' => 'name', 'like' => '%' . $params['keyword'] . '%'],
                        ['attribute' => 'sku', 'like' => '%' . $params['keyword'] . '%']
                    ]);
                }

                // Stock Filters
                if (!empty($params['low_stock'])) {
                    $collection->getSelect()->having('`qty` > 0 AND `qty` < 5 AND e.type_id != "configurable"');
                }
                if (!empty($params['out_of_stock'])) {
                    $collection->getSelect()->having('`qty` <= 0 AND e.type_id != "configurable"');
                }

                $this->products = $collection;
            } catch (\Exception $e) {
                error_log("VendorMarketplace Error in getVendorProducts: " . $e->getMessage());
                $this->products = $this->productCollectionFactory->create();
            }
        }
        return $this->products;
    }

    /**
     * Get products organized in hierarchical structure
     * Configurable products will have their children nested
     * @return array
     */
    public function getHierarchicalProducts()
    {
        $products = $this->getVendorProducts();
        if (!$products) {
            return [];
        }

        $vendor = $this->getVendor();
        $vendorId = $vendor ? (int)$vendor->getId() : 0;

        $hierarchical = [];
        $configChildrenMap = [];
        $childProductIds = [];

        // Load all linked variants with MSI stock regardless of salability
        foreach ($products as $product) {
            if ($product->getTypeId() === "configurable") {
                $pId = (int)$product->getId();
                $childCollection = $product->getTypeInstance()->getUsedProductCollection($product)
                    ->setFlag('has_stock_status_filter', true)
                    ->addAttributeToSelect(['name', 'sku', 'price', 'status', 'vendor_id', 'thumbnail', 'small_image', 'image']);
                
                $sourceItemTable = $childCollection->getTable('inventory_source_item');
                $legacyStockTable = $childCollection->getTable('cataloginventory_stock_item');

                $childCollection->getSelect()->joinLeft(
                    ['isi' => $sourceItemTable],
                    'isi.sku = e.sku',
                    ['msi_qty' => 'isi.quantity']
                )->joinLeft(
                    ['csi' => $legacyStockTable],
                    'csi.product_id = e.entity_id AND csi.stock_id = 1',
                    ['legacy_qty' => 'csi.qty']
                )->columns([
                    'qty' => new \Zend_Db_Expr('COALESCE(isi.quantity, csi.qty, 0)')
                ])->group('e.entity_id');

                $validChildren = [];
                foreach ($childCollection as $child) {
                    if ($vendorId > 0 && (int)$child->getData("vendor_id") !== $vendorId && (int)$child->getData("vendor_id") !== 0) {
                        continue;
                    }
                    if (!$this->passesStockFilter($child)) {
                        continue;
                    }
                    $validChildren[] = $child;
                    $childProductIds[(int)$child->getId()] = true;
                }
                $configChildrenMap[$pId] = $validChildren;
            }
        }

        foreach ($products as $product) {
            $pId = (int)$product->getId();
            // Skip if this is a variant child belonging to a parent in the list
            if (isset($childProductIds[$pId])) {
                continue;
            }

            $hierarchical[] = [
                "product" => $product,
                "children" => $configChildrenMap[$pId] ?? []
            ];
        }

        return $hierarchical;
    }

    protected function passesStockFilter($product)
    {
        $params = $this->getRequest()->getParams();
        $qty = (float) $this->getProductQty($product);

        if (!empty($params['out_of_stock'])) {
            return $qty <= 0;
        }

        if (!empty($params['low_stock'])) {
            return $qty > 0 && $qty < 5;
        }

        return true;
    }

    public function getSalesStats($product)
    {
        if ($this->cachedSalesStats === null) {
            $this->cachedSalesStats = [];
            try {
                $collection = $this->orderItemCollectionFactory->create();
                $collection->getSelect()->columns([
                    "total_sold" => new \Zend_Db_Expr("SUM(main_table.qty_ordered)"),
                    "total_confirmed" => new \Zend_Db_Expr("SUM(main_table.qty_invoiced)"),
                    "total_canceled" => new \Zend_Db_Expr("SUM(main_table.qty_canceled)")
                ])->group("main_table.product_id");

                foreach ($collection as $item) {
                    $pId = (int)$item->getProductId();
                    $sold = (float)$item->getData("total_sold");
                    $confirmed = (float)$item->getData("total_confirmed");
                    $canceled = (float)$item->getData("total_canceled");
                    $pending = max(0, $sold - $confirmed - $canceled);
                    $this->cachedSalesStats[$pId] = [
                        "sold" => $sold,
                        "confirmed" => $confirmed,
                        "pending" => $pending
                    ];
                }
            } catch (\Exception $e) {
                // Fallback to empty
            }
        }

        $productId = (int)$product->getId();
        return $this->cachedSalesStats[$productId] ?? ["sold" => 0, "confirmed" => 0, "pending" => 0];
    }

    public function formatPrice($price)
    {
        return $this->pricingHelper->currency($price, true, false);
    }

    public function getAddProductUrl()
    {
        return $this->getUrl('marketplace/product/new');
    }

    public function getEditProductUrl($product)
    {
        return $this->getUrl('marketplace/product/edit', ['id' => $product->getId()]);
    }

    public function getDeleteProductUrl($product)
    {
        return $this->getUrl('marketplace/product/delete', ['id' => $product->getId()]);
    }

    /**
     * Get Product Quantity (Supports MSI & Configurable Children Sum)
     * @param \Magento\Catalog\Model\Product $product
     * @param array $children
     * @return float|int
     */
    public function getProductQty($product, $children = [])
    {
        if ($product->getTypeId() === 'configurable') {
            if (!empty($children)) {
                $sum = 0;
                foreach ($children as $child) {
                    $sum += (float)$this->getProductQty($child);
                }
                return $sum;
            }
            return 0;
        }

        $qty = $product->getData('qty');
        if ($qty !== null) {
            return (float)$qty;
        }

        $msiQty = $product->getData('msi_qty');
        if ($msiQty !== null) {
            return (float)$msiQty;
        }

        return 0;
    }

    public function getProductStats()
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            return ["enabled" => 0, "disabled" => 0, "low_stock" => 0, "out_of_stock" => 0, "denied" => 0];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->setFlag('has_stock_status_filter', true);
        $collection->addAttributeToFilter("vendor_id", $vendor->getId());
        $collection->addAttributeToFilter("sku", ["neq" => "wallet-recharge"]);
        $collection->addAttributeToSelect(["status", "type_id"]);

        // Exclude child simple products belonging to configurable parents from top-level count
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $linkTable = $resource->getTableName("catalog_product_super_link");
        $collection->getSelect()->where("e.entity_id NOT IN (SELECT product_id FROM " . $linkTable . ")");

        $sourceItemTable = $collection->getTable('inventory_source_item');
        $legacyStockTable = $collection->getTable('cataloginventory_stock_item');

        $collection->getSelect()->joinLeft(
            ['isi' => $sourceItemTable],
            'isi.sku = e.sku',
            ['msi_qty' => 'isi.quantity']
        )->joinLeft(
            ['csi' => $legacyStockTable],
            'csi.product_id = e.entity_id AND csi.stock_id = 1',
            ['legacy_qty' => 'csi.qty']
        )->columns([
            'qty' => new \Zend_Db_Expr('COALESCE(isi.quantity, csi.qty, 0)')
        ])->group('e.entity_id');

        $stats = [
            "enabled" => 0,
            "disabled" => 0,
            "low_stock" => 0,
            "out_of_stock" => 0,
            "denied" => 0
        ];

        foreach ($collection as $product) {
            if ($product->getStatus() == 1) {
                $stats["enabled"]++;
            } else {
                $stats["disabled"]++;
            }

            $qty = (float)$product->getData('qty');
            if ($product->getTypeId() !== 'configurable') {
                if ($qty <= 0) {
                    $stats["out_of_stock"]++;
                } elseif ($qty < 5) {
                    $stats["low_stock"]++;
                }
            }
        }

        return $stats;
    }

    public function getVendor()
    {
        $customerId = $this->customerSession->getCustomerId();
        if ($customerId) {
            return $this->vendorFactory->create()->load($customerId, 'customer_id');
        }
        return null;
    }
}
