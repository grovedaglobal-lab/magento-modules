<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\Inventory\VendorSourceManager;
use Vendor\Marketplace\Model\VendorProfileFactory;

class VariationGenerate extends Action
{
    protected $customerSession;
    protected $productFactory;
    protected $productRepository;
    protected $jsonFactory;
    protected $vendorFactory;
    protected $vendorSourceManager;
    protected $vendorProfileFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        JsonFactory $jsonFactory,
        VendorFactory $vendorFactory,
        VendorSourceManager $vendorSourceManager,
        VendorProfileFactory $vendorProfileFactory
    ) {
        $this->customerSession = $customerSession;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->jsonFactory = $jsonFactory;
        $this->vendorFactory = $vendorFactory;
        $this->vendorSourceManager = $vendorSourceManager;
        $this->vendorProfileFactory = $vendorProfileFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['error' => true, 'message' => __('Please login first.')]);
        }

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $result->setData(['error' => true, 'message' => __('No data provided.')]);
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
            $vendorId = $vendor->getId();

            // Ensure Vendor Source Exists
            try {
                $vendorProfile = $this->vendorProfileFactory->create()->load($vendorId, 'vendor_id');
                if ($vendorProfile->getId()) {
                    $this->vendorSourceManager->processVendorSource($vendorProfile);
                }
            } catch (\Exception $e) {
                // Log but continue
            }

            $baseName = $data['name'] ?? 'Product';
            $baseSku = $data['sku'] ?? 'product-' . time();
            $attributeId = $data['attribute_id'];
            $attributeCode = $data['attribute_code'];
            $options = $data['options'] ?? []; // Array of {value, label, price?, qty?}

            // Check if unique pricing/quantity is enabled
            $uniquePricing = isset($data['unique_pricing']) && $data['unique_pricing'];
            $uniqueQty = isset($data['unique_qty']) && $data['unique_qty'];

            // Fallback bulk values
            $bulkPrice = $data['bulk_price'] ?? null;
            $bulkQty = $data['bulk_qty'] ?? 0;

            if (empty($options)) {
                throw new LocalizedException(__('No options selected.'));
            }

            $generatedProducts = [];

            foreach ($options as $option) {
                // Prepare Data
                $optionLabel = $option['label'];
                $optionValue = $option['value'];

                // Use unique price/qty if available, otherwise use bulk
                $productPrice = $uniquePricing && isset($option['price']) ? $option['price'] : $bulkPrice;
                $productQty = $uniqueQty && isset($option['qty']) ? $option['qty'] : $bulkQty;
                $productQty = ($productQty === '' || $productQty === null) ? 0 : (int) $productQty;

                $newName = $baseName . ' - ' . $optionLabel;

                // Generate SKU with underscores and sanitize (only letters, numbers, underscores)
                $skuSuffix = strtolower(str_replace(' ', '_', $optionLabel));
                $skuSuffix = preg_replace('/[^a-z0-9_]/', '_', $skuSuffix);
                $newSku = $baseSku . '_' . $skuSuffix;

                // Check if SKU exists, append random if needed
                try {
                    $existing = $this->productRepository->get($newSku);
                    $newSku = $newSku . '_' . rand(100, 999);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    // SKU is free
                }

                $generatedProducts[] = [
                    'id' => null,
                    'sku' => $newSku,
                    'name' => $newName,
                    'price' => $productPrice ?: 0,
                    'qty' => $productQty,
                    'attribute_id' => $attributeId,
                    'attribute_code' => $attributeCode,
                    'option_id' => $optionValue,
                    'attribute_value' => $optionLabel
                ];
            }

            return $result->setData([
                'success' => true,
                'products' => $generatedProducts,
                'message' => __('Generated %1 variations successfully.', count($generatedProducts))
            ]);

        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => __('Error: %1', $e->getMessage())]);
        }
    }
}
