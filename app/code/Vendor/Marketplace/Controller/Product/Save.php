<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionsFactory;
use Magento\Catalog\Api\Data\ProductExtensionInterfaceFactory;
use Magento\Eav\Model\Config as EavConfig;
use Vendor\Marketplace\Model\Product\ConfigurableProductService;
use Vendor\Marketplace\Model\Inventory\VendorSourceManager;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Vendor\Marketplace\Model\Vendor;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Api\CategoryLinkManagementInterface;

class Save extends Action
{
    protected $customerSession;
    protected $productFactory;
    protected $productRepository;
    protected $vendorFactory;
    protected $filesystem;
    protected $uploaderFactory;
    protected $categoryLinkManagement;
    protected $scopeConfig;
    protected $configurableType;
    protected $optionFactory;
    protected $productExtensionFactory;
    protected $eavConfig;
    protected $configurableProductService;
    protected $vendorSourceManager;
    protected $vendorProfileFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        VendorFactory $vendorFactory,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagement,
        ScopeConfigInterface $scopeConfig,
        Configurable $configurableType,
        OptionInterfaceFactory $optionFactory,
        ProductExtensionInterfaceFactory $productExtensionFactory,
        EavConfig $eavConfig,
        ConfigurableProductService $configurableProductService,
        VendorSourceManager $vendorSourceManager,
        VendorProfileFactory $vendorProfileFactory
    ) {
        $this->customerSession = $customerSession;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->vendorFactory = $vendorFactory;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->scopeConfig = $scopeConfig;
        $this->configurableType = $configurableType;
        $this->optionFactory = $optionFactory;
        $this->productExtensionFactory = $productExtensionFactory;
        $this->eavConfig = $eavConfig;
        $this->configurableProductService = $configurableProductService;
        $this->vendorSourceManager = $vendorSourceManager;
        $this->vendorProfileFactory = $vendorProfileFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $formKeyValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Data\Form\FormKey\Validator::class);
        if (!$formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if ($vendor->getStatus() != 1) { // 1 = Approved
                throw new \Exception(__('Your account is pending approval. You cannot add or edit products.'));
            }

            // Check auto-approve configuration (needed for both new and edited products)
            $autoApprove = $this->scopeConfig->isSetFlag(
                'vendor_marketplace/general/auto_approve_product',
                ScopeInterface::SCOPE_STORE
            );

            if (isset($data['id']) && $data['id']) {
                $product = $this->productRepository->getById($data['id']);
                // Verify ownership
                if ($product->getData('vendor_id') != $vendor->getId()) {
                    throw new \Exception(__('You do not have permission to edit this product.'));
                }
            } else {
                $product = $this->productFactory->create();
                $product->setAttributeSetId($data['set'] ?? 4); // Default
                $product->setTypeId($data['type'] ?? 'simple');
                $product->setData('vendor_id', $vendor->getId());

                $status = $autoApprove ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
                $product->setStatus($status);
                $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
            }

            // Prepare Data
            $productData = $data['product'] ?? [];

            // Server-side Validation
            $logger = $this->_objectManager->get(\Psr\Log\LoggerInterface::class);
            $logger->info("--- SAVE ACTION START ---");
            $logger->info("User ID: " . $customerId);
            $logger->info("AutoApprove Setting: " . ($autoApprove ? 'TRUE' : 'FALSE'));
            
            if (empty($productData['name'])) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Product Name is required.'));
            }
            if (empty($productData['sku'])) {
                throw new \Magento\Framework\Exception\LocalizedException(__('SKU is required.'));
            }
            // Enforce price for simple/virtual/downloadable
            $type = $product->getTypeId();
            if (in_array($type, ['simple', 'virtual', 'downloadable']) && (!isset($productData['price']) || $productData['price'] === '')) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Price is required.'));
            }

            $categoryIdsToAssign = [];
            if (isset($data['product'])) {
                foreach ($data['product'] as $key => $value) {
                    if ($key === 'category_ids') {
                        // Store for later, DO NOT set on product yet
                        $categoryIdsToAssign = is_array($value) ? $value : explode(',', (string) $value);
                    } elseif ($key === 'status') {
                        // Prevent the form from overriding the status here
                        // We will set it down below based on the auto-approve configuration
                        continue;
                    } elseif ($key === 'gst_rate') {
                        $product->setData($key, $this->normalizeGstRateValue($value));
                    } else {
                        $product->setData($key, $value);
                    }
                }
            }

            // Removed the early status setting, will do it right before save for better reliability

            // Handle Multiple File Uploads (Limit to 7)
            $imageFields = ['image', 'image_2', 'image_3', 'image_4', 'image_5', 'image_6', 'image_7'];
            $first = true;
            $uploadErrors = [];
            $uploadedCount = 0;

            foreach ($imageFields as $field) {
                if (isset($_FILES[$field]['name']) && $_FILES[$field]['name'] != '') {
                    try {
                        // Use custom uploader for WebP support
                        $uploader = new \Vendor\Marketplace\Model\File\Uploader($field);
                        $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'webp']);
                        $uploader->setAllowRenameFiles(true);
                        $uploader->setFilesDispersion(true);
                        $uploader->setAllowCreateFolders(true);

                        $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('catalog/product');
                        $result = $uploader->save($mediaPath);

                        if (isset($result['file'])) {
                            $imagePath = $result['file'];
                            $extension = strtolower(pathinfo($result['name'], PATHINFO_EXTENSION));
                            $isWebP = ($extension === 'webp');

                            if ($first) {
                                if (!$isWebP) {
                                    // Standard image formats - use normal flow
                                    $product->addImageToMediaGallery($mediaPath . $imagePath, ['image', 'small_image', 'thumbnail'], false, false);
                                } else {
                                    // WebP - manually add to gallery
                                    $mediaGallery = $this->_objectManager->create(\Magento\Catalog\Model\Product\Gallery\Processor::class);

                                    try {
                                        // Add image to gallery
                                        $imageFile = $mediaPath . $imagePath;
                                        $product->addImageToMediaGallery($imageFile, null, false, false);

                                        // Set as main images
                                        $product->setImage($imagePath);
                                        $product->setSmallImage($imagePath);
                                        $product->setThumbnail($imagePath);
                                    } catch (\Exception $e) {
                                        // If addImageToMediaGallery fails, set directly
                                        $product->setData('image', $imagePath);
                                        $product->setData('small_image', $imagePath);
                                        $product->setData('thumbnail', $imagePath);

                                        $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->warning(
                                            'WebP image set directly without gallery: ' . $e->getMessage(),
                                            ['file' => $imagePath]
                                        );
                                    }
                                }
                                $first = false;
                            } else {
                                // Additional images
                                if (!$isWebP) {
                                    $product->addImageToMediaGallery($mediaPath . $imagePath, null, false, false);
                                } else {
                                    try {
                                        $product->addImageToMediaGallery($mediaPath . $imagePath, null, false, false);
                                    } catch (\Exception $e) {
                                        $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->warning(
                                            'Additional WebP image could not be added to gallery: ' . $e->getMessage()
                                        );
                                    }
                                }
                            }
                            $uploadedCount++;
                        }

                    } catch (\Exception $e) {
                        $uploadErrors[] = sprintf('Image %s: %s', $field, $e->getMessage());
                        $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->error(
                            'Product image upload error: ' . $e->getMessage(),
                            ['field' => $field, 'exception' => $e]
                        );
                    }
                }
            }

            // Handle Video URL
            if (isset($data['product']['video_url']) && !empty($data['product']['video_url'])) {
                try {
                    $videoUrl = trim($data['product']['video_url']);

                    // Validate video URL (YouTube, Vimeo)
                    if (preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//', $videoUrl)) {
                        // Store video URL in product data
                        $product->setData('video_url', $videoUrl);

                        // Optionally add to media gallery as external video
                        // This requires Magento's ProductVideo module to be enabled
                        // For now, we'll just store the URL
                    } else {
                        $uploadErrors[] = 'Invalid video URL. Only YouTube and Vimeo URLs are supported.';
                    }
                } catch (\Exception $e) {
                    $uploadErrors[] = sprintf('Video: %s', $e->getMessage());
                    $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->error(
                        'Product video URL error: ' . $e->getMessage()
                    );
                }
            }

            // Set Website IDs (Assign to main website)
            $product->setWebsiteIds([1]);

            // Store qty for MSI assignment after product save
            $qtyToAssign = 0;
            if (isset($data['product']['quantity_and_stock_status']['qty'])) {
                $qtyToAssign = (float) $data['product']['quantity_and_stock_status']['qty'];
            } elseif (isset($data['product']['qty'])) {
                $qtyToAssign = (float) $data['product']['qty'];
            }

            // Ensure we are saving in the correct store scope (Admin/Global) to prevent URL rewrite errors
            // Force global store scope for vendor products to ensure status consistency
            $product->setStoreId(0);

            // Legacy Stock Data for Configurable Product
            // Critical: "is_in_stock" must be 1 for the configurable parent to be visible/salable
            if ($product->getTypeId() == 'configurable') {
                $product->setStockData([
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 0,
                    'is_in_stock' => 1,
                    'qty' => 0
                ]);
            }

            // Force status again right before save (to fix the "still enabled" bug)
            $status = $autoApprove ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
            $product->setStatus($status);
            $product->setData('status', $status);
            
            // Log it
            $logger->info("Final status check for " . $product->getSku() . ": " . $status);

            // Use Repository for initial save (creates product + URL rewrites)
            $savedProduct = $this->productRepository->save($product);
            
            // Post-save verification log
            $logger->info("Post-save status in object: " . $savedProduct->getStatus());
            
            // Double-check/Force save status attribute via resource if it's an edit
            if ($product->getId()) {
                $product->getResource()->saveAttribute($product, 'status');
            }

            // Now Assign Categories separately
            if (!empty($categoryIdsToAssign)) {
                $this->categoryLinkManagement->assignProductToCategories(
                    $savedProduct->getSku(),
                    $categoryIdsToAssign
                );
            }

            // Assign Stock to Vendor Source (MSI)
            if ($qtyToAssign > 0 || $savedProduct->getTypeId() !== 'configurable') {
                // For simple products, assign stock immediately
                // For configurable, we'll assign stock to child products instead
                if ($savedProduct->getTypeId() !== 'configurable') {
                    $this->vendorSourceManager->assignProductToVendorSource(
                        $savedProduct->getSku(),
                        $vendor->getId(),
                        $qtyToAssign
                    );
                }
            }

            // --- CONFIGURABLE PRODUCT LOGIC START ---
            if ($savedProduct->getTypeId() == 'configurable') {
                if (isset($data['configurable_products_data']) && !empty($data['configurable_products_data'])) {
                    // Creating new variations from wizard
                    $this->processConfigurableProduct($savedProduct, $data['configurable_products_data'], $vendor);
                } elseif (isset($data['id']) && $data['id']) {
                    // Editing existing configurable - sync MSI for existing children
                    $this->syncExistingChildrenToMSI($savedProduct, $vendor);
                }

                // CRITICAL: Always propagate tax attributes to children for consistency
                $this->propagateAttributesToChildren($savedProduct);
            }
            // --- CONFIGURABLE PRODUCT LOGIC END ---


            // --- FORCE STATUS GLOBALLY ACROSS ALL STORES TO AVOID OVERRIDES ---
            $productId = $savedProduct->getId();
            $attributeAction = $this->_objectManager->create(\Magento\Catalog\Model\Product\Action::class);
            $attributeAction->updateAttributes([$productId], ['status' => $status], 0); // Store 0
            
            // If it's a configurable, also force for all children
            if ($savedProduct->getTypeId() == 'configurable') {
                $childIds = $this->configurableType->getChildrenIds($productId);
                if (!empty($childIds) && !empty($childIds[0])) {
                    $attributeAction->updateAttributes($childIds[0], ['status' => $status], 0);
                }
            }
            
            $logger->info("Global status enforcement applied for ID $productId (Status: $status)");


            // Invalidate Indexer to ensure product shows up
            $indexerIds = [
                'catalog_category_product',
                'catalog_product_category',
                'catalog_product_price',
                'catalogsearch_fulltext'
            ];
            foreach ($indexerIds as $indexerId) {
                $indexer = $this->_objectManager->get(\Magento\Framework\Indexer\IndexerRegistry::class)->get($indexerId);
                $indexer->invalidate();
            }

            if ($autoApprove) {
                $this->messageManager->addSuccessMessage(__('Product saved successfully and is now enabled.'));
            } else {
                $this->messageManager->addSuccessMessage(__('Product saved successfully and is pending approval.'));
            }

            // Add upload feedback
            if ($uploadedCount > 0) {
                $this->messageManager->addSuccessMessage(__('%1 image(s) uploaded successfully.', $uploadedCount));
            }
            if (!empty($uploadErrors)) {
                foreach ($uploadErrors as $error) {
                    $this->messageManager->addWarningMessage($error);
                }
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error saving product: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }

    /**
     * Process configurable product creation/update using Magento 2 standard structure
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $configurableProduct
     * @param string $configDataJson
     * @param \Vendor\Marketplace\Model\Vendor $vendor
     * @return void
     */
    /**
     * Process Configurable Product - Create child variations and link to parent
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $configurableProduct
     * @param string $configDataJson JSON string from wizard
     * @param \Vendor\Marketplace\Model\Vendor $vendor
     * @return void
     */
    protected function processConfigurableProduct($configurableProduct, $configDataJson, $vendor)
    {
        try {
            $variationsData = json_decode($configDataJson, true);

            if (!is_array($variationsData) || empty($variationsData)) {
                $this->messageManager->addWarningMessage(__('No variation data provided for configurable product.'));
                return;
            }

            $rootAttributeId = null;
            if (isset($variationsData['attribute_id'])) {
                $rootAttributeId = $variationsData['attribute_id'];
            }

            // Unpack wrapper if present
            if (isset($variationsData['linked_products'])) {
                $variationsData = $variationsData['linked_products'];
            }

            $logger = $this->_objectManager->get(\Psr\Log\LoggerInterface::class);
            $logger->info('Processing Variations Data: ' . print_r($variationsData, true));

            $childProductIds = [];
            $usedAttributeIds = [];

            // Create or Update each child product
            foreach ($variationsData as $index => $varData) {
                try {
                    $childProduct = null;
                    $isNew = false;

                    // 1. Try to load by ID (from wizard generation)
                    if (isset($varData['product_id'])) {
                        try {
                            $childProduct = $this->productRepository->getById($varData['product_id']);
                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                            // ID provided but not found? Should not happen usually.
                            $childProduct = null;
                        }
                    }

                    $sku = $varData['sku'] ?? null;
                    if (!$sku && $childProduct) {
                        $sku = $childProduct->getSku();
                    } elseif (!$sku) {
                        $sku = $configurableProduct->getSku() . '_var_' . $index;
                    }
                    // Sanitize SKU
                    $sku = preg_replace('/[^a-zA-Z0-9_]/', '_', $sku);

                    // Check auto-approve configuration
                    $autoApprove = $this->scopeConfig->isSetFlag(
                        'vendor_marketplace/general/auto_approve_product',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    );
                    $childStatus = $autoApprove ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;

                    // 2. If not found by ID, try by SKU
                    if (!$childProduct || !$childProduct->getId()) { // Added check for getId() to ensure it's a loaded product
                        try {
                            $childProduct = $this->productRepository->get($sku);
                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                            // Create new simple product
                            $childProduct = $this->productFactory->create();
                            $childProduct->setSku($sku);
                            $childProduct->setAttributeSetId($configurableProduct->getAttributeSetId());
                            $childProduct->setWebsiteIds([1]);
                            $childProduct->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);

                            $childProduct->setTypeId('simple');
                            $isNew = true;
                        }
                    }

                    if (!$childProduct) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('Could not initialize variation product for SKU %1.', $sku)
                        );
                    }

                    $childProduct->setStatus($childStatus);

                    // Update basic data
                    if (isset($varData['name'])) {
                        $childProduct->setName($varData['name']);
                    } elseif ($isNew || !$childProduct->getName()) {
                        $childProduct->setName('Variation ' . ($index + 1));
                    }

                    // Always update price if provided (even if 0, maybe they want free?)
                    // But if not provided, keep existing or inherit parent
                    if (isset($varData['price']) && $varData['price'] !== '') {
                        $childProduct->setPrice($varData['price']);
                    } elseif ($isNew || !$childProduct->getPrice()) {
                        $childProduct->setPrice($configurableProduct->getPrice());
                    }

                    if (isset($varData['weight'])) {
                        $childProduct->setWeight($varData['weight']);
                    }

                    // Set vendor ID
                    $childProduct->setData('vendor_id', $vendor->getId());

                    // Set legacy stock data to ensure "In Stock" status so MSI works reliably
                    // DO NOT set qty here, as it may push to Magento's "Default Source" unintentionally
                    $childProduct->setStockData([
                        'use_config_manage_stock' => 1,
                        'manage_stock' => 1,
                        'is_in_stock' => 1
                    ]);

                    // Parse and set configurable attributes
                    if (isset($varData['attributes'])) {
                        $attributesJson = is_string($varData['attributes']) ? json_decode($varData['attributes'], true) : $varData['attributes'];

                        if (is_array($attributesJson)) {
                            foreach ($attributesJson as $attrData) {
                                $attrId = $attrData['attribute_id'] ?? null;
                                $attrValue = $attrData['value'] ?? null;

                                if ($attrId && $attrValue) {
                                    // Get attribute code
                                    $attribute = $this->eavConfig->getAttribute('catalog_product', $attrId);
                                    if ($attribute && $attribute->getId()) {
                                        $attrCode = $attribute->getAttributeCode();

                                        // Get or create option ID for this value
                                        $optionId = null;
                                        if (is_numeric($attrValue)) {
                                            // Check if this is a valid option ID for this attribute
                                            $options = $attribute->getOptions();
                                            foreach ($options as $option) {
                                                if ($option->getValue() == $attrValue) {
                                                    $optionId = $attrValue;
                                                    break;
                                                }
                                            }
                                        }

                                        if (!$optionId) {
                                            $optionId = $this->getOptionIdByLabel($attribute, $attrValue);
                                        }

                                        if ($optionId) {
                                            $childProduct->setData($attrCode, $optionId);

                                            // Track used attribute IDs
                                            if (!in_array($attrId, $usedAttributeIds)) {
                                                $usedAttributeIds[] = $attrId;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Handle variation image upload
                    if (!empty($varData['file_key']) && isset($_FILES[$varData['file_key']]['name']) && $_FILES[$varData['file_key']]['name'] != '') {
                        $field = $varData['file_key'];
                        try {
                            $uploader = new \Vendor\Marketplace\Model\File\Uploader($field);
                            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'webp']);
                            $uploader->setAllowRenameFiles(true);
                            $uploader->setFilesDispersion(true);
                            $uploader->setAllowCreateFolders(true);

                            $mediaPath = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath('catalog/product');
                            $result = $uploader->save($mediaPath);

                            if (isset($result['file'])) {
                                $imagePath = $result['file'];
                                $extension = strtolower(pathinfo($result['name'], PATHINFO_EXTENSION));
                                $isWebP = ($extension === 'webp');

                                if (!$isWebP) {
                                    $childProduct->addImageToMediaGallery($mediaPath . $imagePath, ['image', 'small_image', 'thumbnail'], false, false);
                                } else {
                                    // Set directly for webp, gallery sync might be complex for new un-saved product
                                    $childProduct->setData('image', $imagePath);
                                    $childProduct->setData('small_image', $imagePath);
                                    $childProduct->setData('thumbnail', $imagePath);
                                }
                            }
                        } catch (\Exception $e) {
                            $logger->error('Variation image error: ' . $e->getMessage(), ['field' => $field]);
                        }
                    }

                    // Save child product
                    $savedChild = $this->productRepository->save($childProduct);

                    // Assign stock using MSI - Ensure vendor source exists first
                    $qty = isset($varData['qty']) ? (float) $varData['qty'] : 0;

                    try {
                        // Ensure Profile Exists to process source
                        $vendorProfile = $this->vendorProfileFactory->create()->load($vendor->getId(), 'vendor_id');
                        if ($vendorProfile->getId()) {
                            $this->vendorSourceManager->processVendorSource($vendorProfile);
                        }

                        $logger->info(sprintf("Assigning MSI Stock for SKU %s: Qty %f to Vendor %s", $savedChild->getSku(), $qty, $vendor->getId()));

                        $this->vendorSourceManager->assignProductToVendorSource(
                            $savedChild->getSku(),
                            $vendor->getId(),
                            $qty
                        );
                    } catch (\Exception $e) {
                        $logger->error("MSI Assignment Error for SKU $sku: " . $e->getMessage());
                        // Log error
                        $this->messageManager->addWarningMessage(
                            __('Stock could not be updated for %1: %2', $sku, $e->getMessage())
                        );
                    }

                    $childProductIds[] = $savedChild->getId();

                } catch (\Exception $e) {
                    $this->messageManager->addWarningMessage(
                        __('Could not save variation %1: %2', $index + 1, $e->getMessage())
                    );
                    continue;
                }
            }

            if (empty($childProductIds)) {
                $this->messageManager->addWarningMessage(__('No child products were created.'));
                return;
            }

            // Link child products to configurable parent
            // Use root attribute ID if available (from wizard), otherwise use tracked attributes
            $attributeIdToLink = $rootAttributeId ? $rootAttributeId : ($usedAttributeIds[0] ?? null);

            if ($attributeIdToLink) {
                $this->configurableProductService->linkProductsToConfigurable(
                    $configurableProduct,
                    $attributeIdToLink,
                    $childProductIds
                );

                $this->messageManager->addSuccessMessage(
                    __('Successfully created and linked %1 variation(s) to the configurable product.', count($childProductIds))
                );
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error creating configurable product variations: %1', $e->getMessage())
            );
        }
    }

    /**
     * Get or create attribute option ID by label
     * 
     * @param \Magento\Eav\Model\Entity\Attribute $attribute
     * @param string $label
     * @return int|null
     */
    protected function getOptionIdByLabel($attribute, $label)
    {
        $options = $attribute->getOptions();

        // Search for existing option
        foreach ($options as $option) {
            if (strcasecmp($option->getLabel(), $label) === 0) {
                return $option->getValue();
            }
        }

        // Create new option if not found
        try {
            $optionManagement = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Api\AttributeOptionManagementInterface::class);

            $optionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Api\Data\AttributeOptionInterfaceFactory::class);

            $optionLabelFactory = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory::class);

            $optionLabel = $optionLabelFactory->create();
            $optionLabel->setStoreId(0);
            $optionLabel->setLabel($label);

            $option = $optionFactory->create();
            $option->setLabel($label);
            $option->setStoreLabels([$optionLabel]);
            $option->setSortOrder(0);
            $option->setIsDefault(false);

            $optionManagement->add(
                \Magento\Catalog\Model\Product::ENTITY,
                $attribute->getAttributeId(),
                $option
            );

            // Reload attribute to get new option
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attribute->getAttributeId());
            $options = $attribute->getOptions();

            // Find the newly created option
            foreach ($options as $opt) {
                if (strcasecmp($opt->getLabel(), $label) === 0) {
                    return $opt->getValue();
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addWarningMessage(
                __('Could not create option "%1" for attribute: %2', $label, $e->getMessage())
            );
        }

        return null;
    }



    /**
     * Sync existing child products to MSI
     * Used when editing existing configurable products to migrate from legacy stock
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $configurableProduct
     * @param \Vendor\Marketplace\Model\Vendor $vendor
     * @return void
     */
    protected function syncExistingChildrenToMSI($configurableProduct, $vendor)
    {
        try {
            // Get child product IDs
            $childIds = $this->configurableType->getChildrenIds($configurableProduct->getId());

            if (empty($childIds) || empty($childIds[0])) {
                return; // No children to sync
            }

            // Get source code for checking
            $sourceCode = $this->vendorSourceManager->getSourceCode($vendor->getId());

            $childProductIds = $childIds[0]; // First element contains the IDs
            $syncedCount = 0;

            foreach ($childProductIds as $childId) {
                try {
                    $childProduct = $this->productRepository->getById($childId);

                    // Check if child belongs to this vendor
                    if ($childProduct->getData('vendor_id') != $vendor->getId()) {
                        continue;
                    }

                    // Check if MSI source item already exists
                    if ($this->hasSourceItem($childProduct->getSku(), $sourceCode)) {
                        continue; // Already has MSI stock
                    }

                    // Get quantity from legacy stock or use default
                    $qty = $this->getLegacyStockQty($childProduct);

                    // Assign to MSI - catch errors if source doesn't exist
                    try {
                        $this->vendorSourceManager->assignProductToVendorSource(
                            $childProduct->getSku(),
                            $vendor->getId(),
                            $qty
                        );
                        $syncedCount++;
                    } catch (\Exception $msiException) {
                        // Source doesn't exist - skip this child silently
                        continue;
                    }

                } catch (\Exception $e) {
                    $this->messageManager->addWarningMessage(
                        __('Could not sync child product %1 to MSI: %2', $childId, $e->getMessage())
                    );
                    continue;
                }
            }

            if ($syncedCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('Synced %1 child product(s) to MSI inventory.', $syncedCount)
                );
            }

        } catch (\Exception $e) {
            $this->messageManager->addWarningMessage(
                __('Error syncing children to MSI: %1', $e->getMessage())
            );
        }
    }

    /**
     * Check if product SKU has a source item in MSI
     * 
     * @param string $sku
     * @param string $sourceCode
     * @return bool
     */
    protected function hasSourceItem($sku, $sourceCode)
    {
        try {
            $sourceItemRepository = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class);

            $sourceItems = $sourceItemRepository->execute($sku);

            foreach ($sourceItems as $sourceItem) {
                if ($sourceItem->getSourceCode() === $sourceCode) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Source item doesn't exist or error occurred
            return false;
        }

        return false;
    }

    /**
     * Get legacy stock quantity for a product
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return float
     */
    protected function getLegacyStockQty($product)
    {
        try {
            // Try to get from stock item (legacy)
            $stockItem = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class)
                ->getStockItem($product->getId());

            if ($stockItem && $stockItem->getQty() > 0) {
                return (float) $stockItem->getQty();
            }
        } catch (\Exception $e) {
            // Legacy stock not available
        }

        // Default quantity
        return 10.0;
    }

    /**
     * Propagate critical attributes from configurable parent to all simple children
     * 
     * @param \Magento\Catalog\Api\Data\ProductInterface $configurableProduct
     * @return void
     */
    protected function propagateAttributesToChildren($configurableProduct)
    {
        try {
            $childIds = $this->configurableType->getChildrenIds($configurableProduct->getId());
            if (empty($childIds) || empty($childIds[0])) {
                return;
            }

            $gstRate = $configurableProduct->getData('gst_rate');
            $hsnCode = $configurableProduct->getData('hsn_code');
            $vendorId = $configurableProduct->getData('vendor_id');
            $status = $configurableProduct->getStatus();

            $logger = $this->_objectManager->get(\Psr\Log\LoggerInterface::class);
            $logger->info("Propagating attributes from Parent {$configurableProduct->getSku()} (Rate: $gstRate, HSN: $hsnCode) to " . count($childIds[0]) . " children.");

            foreach ($childIds[0] as $childId) {
                try {
                    $childProduct = $this->productRepository->getById($childId);
                    $changed = false;

                    // Sync GST Rate
                    if ($childProduct->getData('gst_rate') != $gstRate) {
                        $childProduct->setData('gst_rate', $gstRate);
                        $changed = true;
                    }

                    // Sync HSN Code
                    if ($childProduct->getData('hsn_code') != $hsnCode) {
                        $childProduct->setData('hsn_code', $hsnCode);
                        $changed = true;
                    }

                    // Sync Vendor ID
                    if ($childProduct->getData('vendor_id') != $vendorId) {
                        $childProduct->setData('vendor_id', $vendorId);
                        $changed = true;
                    }

                    // Sync Status
                    if ($childProduct->getStatus() != $status) {
                        $childProduct->setStatus($status);
                        $changed = true;
                    }

                    // Always ensure Taxable Goods class
                    if ($childProduct->getTaxClassId() != 2) {
                        $childProduct->setTaxClassId(2);
                        $changed = true;
                    }

                    if ($changed) {
                        $this->productRepository->save($childProduct);
                        $logger->info(" - Updated Child: {$childProduct->getSku()}");
                    }
                } catch (\Exception $e) {
                    $logger->err(" - Error updating Child ID $childId: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            // Silently fail or log to main log
        }
    }

    /**
     * Normalize GST rate input to the percentage value used by the product attribute.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalizeGstRateValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;

        try {
            $collection = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Tax\IndianGST\Model\ResourceModel\Rate\CollectionFactory::class)
                ->create();

            foreach (['rate_id', 'total_rate', 'igst_rate'] as $field) {
                $matchCollection = clone $collection;
                $matchCollection->addFieldToFilter($field, $value);
                $matchCollection->setPageSize(1);

                $rate = $matchCollection->getFirstItem();
                if ($rate && $rate->getId()) {
                    $totalRate = $rate->getData('total_rate');
                    if ($totalRate === null || $totalRate === '') {
                        $totalRate = $rate->getData('igst_rate');
                    }

                    if ($totalRate !== null && $totalRate !== '') {
                        return $this->formatGstRateValue($totalRate);
                    }

                    return $value;
                }
            }
        } catch (\Exception $e) {
            // Use the posted value if the GST lookup fails.
        }

        return $this->formatGstRateValue($value);
    }

    /**
     * Format GST rate values consistently for storage.
     *
     * @param mixed $value
     * @return string
     */
    protected function formatGstRateValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        $formatted = number_format((float) $value, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

}
