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
                    // Log warning but don't crash
                    error_log("VendorMarketplace: No vendor found for customer ID " . $customerId);
                    return false;
                }

                $collection = $this->productCollectionFactory->create();
                $collection->addAttributeToSelect('*');
                $collection->addAttributeToFilter('vendor_id', $vendor->getId());
                $collection->addAttributeToFilter('sku', ['neq' => 'wallet-recharge']);
                $collection->setOrder('created_at', 'DESC');

                // Join Stock Quantity (Legacy) before stock-based filters
                $collection->joinField(
                    'qty',
                    'cataloginventory_stock_item',
                    'qty',
                    'product_id=entity_id',
                    '{{table}}.stock_id=1',
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
                    // Configurable parents do not carry direct stock.
                    $collection->addAttributeToFilter('type_id', ['neq' => 'configurable']);
                    $collection->addAttributeToFilter('qty', ['lt' => 5]);
                    $collection->addAttributeToFilter('qty', ['gt' => 0]);
                }
                if (!empty($params['out_of_stock'])) {
                    // Configurable parents do not carry direct stock.
                    $collection->addAttributeToFilter('type_id', ['neq' => 'configurable']);
                    $collection->addAttributeToFilter('qty', ['lteq' => 0]);
                }

                // Join Attribute Set Name
                $collection->joinField(
                    'attribute_set_name',
                    'eav_attribute_set',
                    'attribute_set_name',
                    'attribute_set_id=attribute_set_id',
                    null,
                    'left'
                );

                // Force load to catch SQL errors here
                $collection->getSize();

                $this->products = $collection;
            } catch (\Exception $e) {
                error_log("VendorMarketplace Error in getVendorProducts: " . $e->getMessage());
                // Return empty collection object to prevent template crash
                $this->products = $this->productCollectionFactory->create();
                // Actually, if we return empty collection, getSize() returns 0.
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
        $vendorId = $vendor ? (int) $vendor->getId() : 0;

        $hierarchical = [];
        $childProductIds = [];

        // First pass: identify all child products of configurables
        foreach ($products as $product) {
            if ($product->getTypeId() == 'configurable') {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $configurableProduct = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable');
                $children = $configurableProduct->getUsedProducts($product);

                foreach ($children as $child) {
                    if ($vendorId > 0 && (int) $child->getData('vendor_id') !== $vendorId) {
                        continue;
                    }

                    if (!$this->passesStockFilter($child)) {
                        continue;
                    }

                    $childProductIds[] = $child->getId();
                }
            }
        }

        // Second pass: build hierarchical structure
        foreach ($products as $product) {
            // Skip if this is a child product (will be shown under parent)
            if (in_array($product->getId(), $childProductIds)) {
                continue;
            }

            $productData = [
                'product' => $product,
                'children' => []
            ];

            // If configurable, load children
            if ($product->getTypeId() == 'configurable') {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $configurableProduct = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable');
                $children = $configurableProduct->getUsedProducts($product);

                foreach ($children as $child) {
                    if ($vendorId > 0 && (int) $child->getData('vendor_id') !== $vendorId) {
                        continue;
                    }

                    if (!$this->passesStockFilter($child)) {
                        continue;
                    }

                    $productData['children'][] = $child;
                }
            }

            $hierarchical[] = $productData;
        }

        // Keep configurable parents visible near the top so variant groups do not look missing
        usort($hierarchical, function (array $left, array $right) {
            $leftType = isset($left['product']) ? $left['product']->getTypeId() : '';
            $rightType = isset($right['product']) ? $right['product']->getTypeId() : '';

            $leftPriority = ($leftType === 'configurable') ? 0 : 1;
            $rightPriority = ($rightType === 'configurable') ? 0 : 1;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            $leftCreated = isset($left['product']) ? (string) $left['product']->getCreatedAt() : '';
            $rightCreated = isset($right['product']) ? (string) $right['product']->getCreatedAt() : '';

            return strcmp($rightCreated, $leftCreated);
        });

        return $hierarchical;
    }

    /**
     * Apply active stock filter params to product rows in hierarchical rendering.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
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
        $stats = [
            'sold' => 0,
            'confirmed' => 0,
            'pending' => 0
        ];

        try {
            $collection = $this->orderItemCollectionFactory->create();
            $collection->addFieldToFilter('product_id', $product->getId());

            // Filter by vendor if needed (though product ID should be unique enough for simple products)
            // If it's a configurable product, we might need to look at parent/child logic, 
            // but for now strict product ID match is safest for the grid listing.

            foreach ($collection as $item) {
                // Qty Sold: Total ordered
                $stats['sold'] += $item->getQtyOrdered();

                // Qty Confirmed: Total invoiced
                $stats['confirmed'] += $item->getQtyInvoiced();

                // Qty Pending: Ordered - Invoiced - Canceled - Refunded
                // Alternatively, just (Ordered - Invoiced - Canceled)
                $pending = $item->getQtyOrdered() - $item->getQtyInvoiced() - $item->getQtyCanceled();
                if ($pending > 0) {
                    $stats['pending'] += $pending;
                }
            }
        } catch (\Exception $e) {
            // Fallback to 0
        }

        return $stats;
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
     * Get Product Quantity (Supports MSI)
     * @param \Magento\Catalog\Model\Product $product
     * @return float|int
     */
    public function getProductQty($product)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\InventorySalesApi\Api\GetProductSalableQtyInterface $saleableQty */
            $saleableQty = $objectManager->get('Magento\InventorySalesApi\Api\GetProductSalableQtyInterface');
            /** @var \Magento\InventorySalesApi\Api\StockResolverInterface $stockResolver */
            $stockResolver = $objectManager->get('Magento\InventorySalesApi\Api\StockResolverInterface');
            /** @var \Magento\Store\Model\StoreManagerInterface $storeManager */
            $storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');

            $websiteCode = $storeManager->getWebsite()->getCode();
            $stockId = $stockResolver->execute(\Magento\InventorySalesApi\Api\Data\SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();

            return $saleableQty->execute($product->getSku(), $stockId);
        } catch (\Exception $e) {
            // Fallback to legacy qty joined in collection
            return $product->getQty();
        }
    }

    public function getProductStats()
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            return ['enabled' => 0, 'disabled' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'denied' => 0];
        }

        // We use a clone or new collection to avoid messing with the main list pagination
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter('vendor_id', $vendor->getId());
        $collection->addAttributeToFilter('sku', ['neq' => 'wallet-recharge']);
        $collection->addAttributeToSelect('status');

        // Join stock for stock calculations - assumes legacy index for simplified stats
        // For strict MSI stats we'd need a complex loop or separate index query
        // Using legacy stock status for performance here
        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        $collection->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        $stats = [
            'enabled' => 0,
            'disabled' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'denied' => 0
        ];

        foreach ($collection as $product) {
            // Status: 1 = Enabled, 2 = Disabled
            if ($product->getStatus() == 1) {
                $stats['enabled']++;
            } else {
                $stats['disabled']++;
            }

            // Configurable parent products do not carry direct stock.
            if ($product->getTypeId() === 'configurable') {
                continue;
            }

            // Use the MSI-aware quantity method
            $qty = $this->getProductQty($product);

            if ($qty <= 0) {
                $stats['out_of_stock']++;
            } elseif ($qty < 5) {
                $stats['low_stock']++;
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
