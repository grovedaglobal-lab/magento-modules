<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class UpdateVariations extends Action
{
    protected $customerSession;
    protected $productRepository;
    protected $jsonFactory;
    protected $stockRegistry;

    public function __construct(
        Context $context,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        JsonFactory $jsonFactory,
        StockRegistryInterface $stockRegistry
    ) {
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->jsonFactory = $jsonFactory;
        $this->stockRegistry = $stockRegistry;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['error' => true, 'message' => __('Please login first.')]);
        }

        $updates = $this->getRequest()->getParam('updates', []);
        if (empty($updates)) {
            return $result->setData(['error' => true, 'message' => __('No updates provided.')]);
        }

        try {
            $updatedCount = 0;

            foreach ($updates as $update) {
                $productId = $update['product_id'] ?? null;
                $name = $update['name'] ?? '';
                $sku = $update['sku'] ?? '';
                $price = $update['price'] ?? 0;
                $qty = $update['qty'] ?? 0;

                if (!$productId || !$name || !$sku) {
                    continue; // Skip invalid entries
                }

                // Load product
                $product = $this->productRepository->getById($productId);

                // Update fields
                $product->setName($name);
                $product->setSku($sku);
                $product->setPrice($price);

                // Update stock
                $stockItem = $this->stockRegistry->getStockItem($productId);
                $stockItem->setQty($qty);
                $stockItem->setIsInStock($qty > 0 ? 1 : 0);
                $this->stockRegistry->updateStockItemBySku($sku, $stockItem);

                // Save product
                $this->productRepository->save($product);

                $updatedCount++;
            }

            return $result->setData([
                'success' => true,
                'message' => __('Updated %1 variations successfully.', $updatedCount)
            ]);

        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => __('Error: %1', $e->getMessage())]);
        }
    }
}
