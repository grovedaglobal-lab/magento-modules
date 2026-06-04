<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class Upload extends Action
{
    /** @var UploaderFactory */
    protected $uploaderFactory;

    /** @var DirectoryList */
    protected $directoryList;

    /** @var JsonFactory */
    protected $resultJsonFactory;
    protected $productRepository;
    protected $productFactory;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        DirectoryList $directoryList,
        JsonFactory $resultJsonFactory,
        ProductRepositoryInterface $productRepository = null,
        ProductFactory $productFactory = null
    ) {
        parent::__construct($context);
        $this->uploaderFactory = $uploaderFactory;
        $this->directoryList = $directoryList;
        $this->resultJsonFactory = $resultJsonFactory;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->productRepository = $productRepository ?: $objectManager->get(ProductRepositoryInterface::class);
        $this->productFactory = $productFactory ?: $objectManager->get(ProductFactory::class);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                return $resultJson->setData(['success' => false, 'message' => 'Invalid request method']);
            }

            if (empty($_FILES['import_file']) || empty($_FILES['import_file']['name'])) {
                return $resultJson->setData(['success' => false, 'message' => 'No file uploaded (field name: import_file)']);
            }

            $uploader = $this->uploaderFactory->create(['fileId' => 'import_file']);
            $uploader->setAllowedExtensions(['xlsx', 'xls', 'csv']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);

            $targetDir = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/imports';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $saveResult = $uploader->save($targetDir);
            if (!$saveResult || empty($saveResult['file'])) {
                throw new \RuntimeException('Failed to save uploaded file');
            }

            $savedPath = $targetDir . DIRECTORY_SEPARATOR . $saveResult['file'];

            // PhpSpreadsheet must be available to parse and import workbook.
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                return $resultJson->setData([
                    'success' => true,
                    'message' => 'File uploaded',
                    'path' => $savedPath,
                    'warning' => 'PhpSpreadsheet not installed; parsing/import skipped. Run `composer require phpoffice/phpspreadsheet` to enable parsing.'
                ]);
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($savedPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            $highestRow = $worksheet->getHighestRow();

            // Read header row (1)
            $headers = [];
            for ($c = 1; $c <= $highestColumnIndex; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $h = trim((string) $worksheet->getCell($colLetter . '1')->getValue());
                $headers[$c] = $h;
            }

            $created = 0; $updated = 0; $errors = [];

            // Resolve vendor id once and keep it on every imported product row.
            $vendorId = null;
            try {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $customerSession = $objectManager->get(\Magento\Customer\Model\Session::class);
                if ($customerSession && $customerSession->isLoggedIn()) {
                    $customerId = $customerSession->getCustomerId();
                    $vendor = $objectManager->get(\Vendor\Marketplace\Model\VendorFactory::class)->create()->load($customerId, 'customer_id');
                    if ($vendor && $vendor->getId()) {
                        $vendorId = (int) $vendor->getId();
                    }
                }
            } catch (\Exception $e) {
                // keep null if vendor resolution fails
            }

            // Build category display label -> id map for category_names assignment
            $categoryMap = [];
            try {
                $categoryCollectionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);
                $catCollection = $categoryCollectionFactory->create();
                $catCollection->addAttributeToSelect(['name', 'path', 'level']);
                $catCollection->addAttributeToFilter('is_active', 1);
                $catCollection->setOrder('path', 'ASC');

                $categoryNames = [];
                foreach ($catCollection as $category) {
                    if ((int) $category->getLevel() < 2) {
                        continue;
                    }

                    $pathIds = array_filter(explode('/', (string) $category->getPath()));
                    $breadcrumb = [];
                    foreach (array_slice($pathIds, 2) as $pathId) {
                        if ((string) $pathId === (string) $category->getId()) {
                            $breadcrumb[] = $category->getName();
                        } elseif (isset($categoryNames[$pathId])) {
                            $breadcrumb[] = $categoryNames[$pathId];
                        }
                    }

                    $displayLabel = !empty($breadcrumb) ? implode(' > ', $breadcrumb) : $category->getName();
                    $categoryMap[$displayLabel] = (int) $category->getId();
                    $categoryNames[$category->getId()] = (string) $category->getName();
                }
            } catch (\Exception $e) {
                // ignore category mapping failures
            }

            // Build attribute set name -> id map
            $attributeSetMap = [];
            try {
                $eavSetCollectionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory::class);
                $asCollection = $eavSetCollectionFactory->create();
                $asCollection->setEntityTypeFilter(4); // product entity type
                foreach ($asCollection as $aset) {
                    $attributeSetMap[trim((string)$aset->getAttributeSetName())] = (int)$aset->getId();
                }
            } catch (\Exception $e) {
                // ignore attribute set mapping failures
            }

            for ($r = 2; $r <= $highestRow; $r++) {
                // build associative row by header name
                $rowData = [];
                for ($c = 1; $c <= $highestColumnIndex; $c++) {
                    $key = $headers[$c] ?? 'col_' . $c;
                    $colLetter = Coordinate::stringFromColumnIndex($c);
                    $rowData[$key] = $worksheet->getCell($colLetter . $r)->getValue();
                }

                $sku = trim((string) ($rowData['sku'] ?? ''));
                if ($sku === '') {
                    // skip empty rows
                    continue;
                }

                try {
                    try {
                        $product = $this->productRepository->get($sku);
                        $isNew = false;
                    } catch (NoSuchEntityException $e) {
                        $product = $this->productFactory->create();
                        $isNew = true;
                    }

                    // Determine product type (default: simple)
                    $typeId = isset($rowData['product_type']) ? trim((string)$rowData['product_type']) : 'simple';
                    if ($typeId !== 'simple' && $typeId !== 'configurable') {
                        $typeId = 'simple';
                    }

                    $parentSku = isset($rowData['parent_sku']) ? trim((string)$rowData['parent_sku']) : '';
                    $variantVal = isset($rowData['variant_value']) ? trim((string)$rowData['variant_value']) : '';

                    // Map common fields
                    if ($isNew) {
                        $product->setSku($sku);
                        $product->setTypeId($typeId);
                        // attribute_set may be provided as name or id; map names to ids
                        $defaultAttrSetId = 4;
                        $attrValue = isset($rowData['attribute_set']) ? trim((string)$rowData['attribute_set']) : '';
                        $attributeSetId = $defaultAttrSetId;
                        if ($attrValue !== '') {
                            if (is_numeric($attrValue)) {
                                $maybeId = (int)$attrValue;
                                // verify id exists in our map
                                if (in_array($maybeId, $attributeSetMap, true)) {
                                    $attributeSetId = $maybeId;
                                } elseif (isset($attributeSetMap[$attrValue])) {
                                    $attributeSetId = (int)$attributeSetMap[$attrValue];
                                } else {
                                    // leave default
                                }
                            } else {
                                if (isset($attributeSetMap[$attrValue])) {
                                    $attributeSetId = (int)$attributeSetMap[$attrValue];
                                }
                            }
                        }
                        $product->setAttributeSetId($attributeSetId);
                    }

                    $nameValue = isset($rowData['name']) ? trim((string)$rowData['name']) : '';
                    if ($nameValue !== '') {
                        $product->setName($nameValue);
                    } elseif ($parentSku !== '' && $typeId === 'simple') {
                        // Auto-generate child name: parent name + variant value.
                        $parentName = isset($rowData['parent_name']) ? trim((string)$rowData['parent_name']) : '';
                        if ($parentName === '') {
                            try {
                                $parentProductForName = $this->productRepository->get($parentSku);
                                $parentName = trim((string)$parentProductForName->getName());
                            } catch (\Exception $e) {
                                $parentName = '';
                            }
                        }

                        if ($parentName !== '' && $variantVal !== '') {
                            $product->setName($parentName . ' - ' . $variantVal);
                        } elseif ($parentName !== '') {
                            $product->setName($parentName);
                        }
                    }
                    if (isset($rowData['description'])) {
                        $product->setDescription((string)$rowData['description']);
                    }
                    if (isset($rowData['short_description'])) {
                        $product->setShortDescription((string)$rowData['short_description']);
                    }
                    if (isset($rowData['price'])) {
                        $product->setPrice((float)$rowData['price']);
                    }
                    if (isset($rowData['cost'])) {
                        $product->setData('cost', (float)$rowData['cost']);
                    }
                    if (isset($rowData['weight'])) {
                        $product->setWeight((float)$rowData['weight']);
                    }
                    if (isset($rowData['status'])) {
                        $product->setStatus((int)$rowData['status']);
                    }
                    if (isset($rowData['visibility'])) {
                        $product->setVisibility((int)$rowData['visibility']);
                    }
                    if (isset($rowData['meta_title'])) {
                        $product->setMetaTitle((string)$rowData['meta_title']);
                    }
                    if (isset($rowData['meta_keyword'])) {
                        $product->setMetaKeyword((string)$rowData['meta_keyword']);
                    }
                    if (isset($rowData['meta_description'])) {
                        $product->setMetaDescription((string)$rowData['meta_description']);
                    }
                    if (isset($rowData['gst_rate'])) {
                        $product->setData('gst_rate', (string)$rowData['gst_rate']);
                    }
                    if (isset($rowData['hsn_code'])) {
                        $product->setData('hsn_code', (string)$rowData['hsn_code']);
                    }

                    $rowQty = isset($rowData['qty']) && $rowData['qty'] !== '' ? (float)$rowData['qty'] : null;

                    // Keep vendor id always for imported products.
                    if ($vendorId) {
                        $product->setData('vendor_id', $vendorId);
                    }

                    // Ensure stock flags allow storefront visibility.
                    if ($typeId === 'configurable') {
                        // Configurable parents must be marked in stock.
                        $product->setStockData([
                            'use_config_manage_stock' => 0,
                            'manage_stock' => 0,
                            'is_in_stock' => 1,
                            'qty' => 0,
                        ]);
                    } elseif ($rowQty !== null) {
                        $product->setStockData([
                            'use_config_manage_stock' => 1,
                            'manage_stock' => 1,
                            'is_in_stock' => $rowQty > 0 ? 1 : 0,
                            'qty' => $rowQty,
                        ]);
                    }

                    // Save product first (base data)
                    $this->productRepository->save($product);

                    // Handle variant linking: if parent_sku is specified, link this product as a child
                    if ($parentSku !== '' && $product->getTypeId() === 'simple') {
                        try {
                            $parentProduct = $this->productRepository->get($parentSku);
                            if ($parentProduct && $parentProduct->getTypeId() === 'configurable') {
                                $variantAttr = isset($rowData['variant_attribute']) ? trim((string)$rowData['variant_attribute']) : '';
                                $variantVal = isset($rowData['variant_value']) ? trim((string)$rowData['variant_value']) : '';

                                if ($variantAttr !== '' && $variantVal !== '') {
                                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                                    $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
                                    $attr = $eavConfig->getAttribute('catalog_product', $variantAttr);

                                    if ($attr && $attr->getId()) {
                                        $optionId = $this->resolveAttributeOptionId($attr, $variantVal);
                                        if ($optionId) {
                                            // Save the child with the actual option ID so Magento can build the configurable association.
                                            $product->setData($variantAttr, $optionId);
                                            $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
                                            $this->productRepository->save($product);

                                            // Rebuild configurable linkage using the same service used by the manual save flow.
                                            $configurableService = $objectManager->get(\Vendor\Marketplace\Model\Product\ConfigurableProductService::class);
                                            $configurableType = $objectManager->get(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::class);
                                            $existingChildIds = [];
                                            try {
                                                $childrenIds = $configurableType->getChildrenIds($parentProduct->getId());
                                                if (!empty($childrenIds) && !empty($childrenIds[0])) {
                                                    $existingChildIds = array_map('intval', $childrenIds[0]);
                                                }
                                            } catch (\Exception $e) {
                                                $existingChildIds = [];
                                            }

                                            $existingChildIds[] = (int) $product->getId();
                                            $existingChildIds = array_values(array_unique(array_filter($existingChildIds)));

                                            $configurableService->linkProductsToConfigurable(
                                                $parentProduct,
                                                (int) $attr->getAttributeId(),
                                                $existingChildIds
                                            );

                                            if ($vendorId) {
                                                $parentProduct->setData('vendor_id', $vendorId);
                                                $this->productRepository->save($parentProduct);
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Silently skip variant linking errors—products are still created
                        }
                    }

                    // Category assignment by display label (category_names column)
                    if (!empty($rowData['category_names'])) {
                        $names = preg_split('/\s*\|\s*/', (string)$rowData['category_names']);
                        $assignIds = [];
                        foreach ($names as $n) {
                            $n = trim($n);
                            if ($n === '') continue;
                            if (isset($categoryMap[$n])) {
                                $assignIds[] = $categoryMap[$n];
                            }
                        }
                        if (!empty($assignIds)) {
                            try {
                                $product->setCategoryIds($assignIds);
                                $this->productRepository->save($product);
                            } catch (\Exception $e) {
                                // Continue even if category assignment fails
                                $errors[] = 'Row ' . $r . ' (SKU: ' . $sku . '): failed assigning categories - ' . $e->getMessage();
                            }
                        }
                    }

                    // Images: download image URLs and add to media gallery
                    $imageFields = ['image','image_2','image_3','image_4','image_5','image_6','image_7'];
                    $imageCount = 0;
                    foreach ($imageFields as $idx => $imgField) {
                        if (empty($rowData[$imgField])) continue;
                        $url = trim((string)$rowData[$imgField]);
                        if ($url === '') continue;

                        try {
                            $tmp = tempnam(sys_get_temp_dir(), 'prodimg_');
                            $content = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5], 'https' => ['timeout' => 5]]));
                            
                            if ($content === false) {
                                // Skip if image not available—don't error out
                                @unlink($tmp);
                                continue;
                            }

                            file_put_contents($tmp, $content);

                            // Use Magento's product media manager to add image
                            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                            $mediaManager = $objectManager->get(\Magento\Catalog\Model\Product\Media\Config::class);
                            $mediaDir = $this->directoryList->getPath(DirectoryList::MEDIA);
                            
                            // Create proper media gallery entry with file copy
                            $gallery = $product->getMediaGallery('images');
                            if (!is_array($gallery)) {
                                $gallery = [];
                            }
                            
                            // Generate unique filename
                            $filename = md5(uniqid() . $sku) . '.jpg';
                            $destPath = $mediaDir . DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'product' . $filename;
                            
                            if (!is_dir(dirname($destPath))) {
                                mkdir(dirname($destPath), 0755, true);
                            }
                            
                            copy($tmp, $destPath);
                            
                            // Add to media gallery with roles
                            $roles = [];
                            if ($imageCount === 0) {
                                $roles = ['image', 'small_image', 'thumbnail'];
                            }
                            
                            // Use addImage method for proper integration
                            $product->addImageToMediaGallery($tmp, $roles, false, false);
                            $imageCount++;
                            
                            @unlink($tmp);
                        } catch (\Exception $e) {
                            if (isset($tmp) && file_exists($tmp)) {
                                @unlink($tmp);
                            }
                            // Log but don't fail the row—images are optional
                        }
                    }

                    // Save product with images (if any were added)
                    if ($imageCount > 0) {
                        try {
                            $this->productRepository->save($product);
                        } catch (\Exception $e) {
                            // Image save errors are non-critical
                        }
                    }

                    // MSI: assign qty to vendor source if available and qty provided
                    try {
                        $qty = isset($rowData['qty']) ? (float)$rowData['qty'] : null;
                        // Do not assign source items to configurable parent SKUs.
                        if ($qty !== null && $qty !== '' && $product->getTypeId() !== 'configurable') {
                            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                            $customerSession = $objectManager->get(\Magento\Customer\Model\Session::class);
                            if ($customerSession && $customerSession->isLoggedIn()) {
                                $customerId = $customerSession->getCustomerId();
                                $vendor = $objectManager->get(\Vendor\Marketplace\Model\VendorFactory::class)->create()->load($customerId, 'customer_id');
                                if ($vendor && $vendor->getId()) {
                                    $vendorSourceManager = $objectManager->get(\Vendor\Marketplace\Model\Inventory\VendorSourceManager::class);
                                    $sourceCode = $vendorSourceManager->getSourceCode((int)$vendor->getId());
                                } else {
                                    $sourceCode = 'default';
                                }
                            } else {
                                $sourceCode = 'default';
                            }

                            // Create and save source item
                            $sourceItemFactory = $objectManager->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class);
                            $sourceItemsSave = $objectManager->get(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class);
                            $sourceItem = $sourceItemFactory->create();
                            $sourceItem->setSku($sku);
                            $sourceItem->setSourceCode($sourceCode);
                            $sourceItem->setQuantity($qty);
                            $sourceItem->setStatus((int)($qty > 0));
                            $sourceItemsSave->execute([$sourceItem]);
                        }
                    } catch (\Exception $e) {
                        $errors[] = 'Row ' . $r . ' (SKU: ' . $sku . '): MSI assignment failed - ' . $e->getMessage();
                    }

                    if ($isNew) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Row ' . $r . ' (SKU: ' . $sku . '): ' . $e->getMessage();
                }
            }

            return $resultJson->setData([
                'success' => true,
                'message' => 'Import completed',
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ]);

            return $resultJson->setData([
                'success' => true,
                'message' => 'File uploaded',
                'path' => $savedPath,
                'warning' => 'PhpSpreadsheet not installed; parsing skipped. Run `composer require phpoffice/phpspreadsheet` to enable parsing.'
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Resolve a configurable attribute value to its option ID.
     *
     * @param \Magento\Eav\Model\Entity\Attribute $attribute
     * @param string $labelOrValue
     * @return int|null
     */
    protected function resolveAttributeOptionId($attribute, $labelOrValue)
    {
        if (!$attribute || !$attribute->getId()) {
            return null;
        }

        $labelOrValue = trim((string) $labelOrValue);
        if ($labelOrValue === '') {
            return null;
        }

        if (is_numeric($labelOrValue)) {
            return (int) $labelOrValue;
        }

        try {
            foreach ($attribute->getOptions() as $option) {
                if (strcasecmp((string) $option->getLabel(), $labelOrValue) === 0) {
                    return (int) $option->getValue();
                }
            }

            $optionManagement = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Api\AttributeOptionManagementInterface::class);
            $optionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Api\Data\AttributeOptionInterfaceFactory::class);
            $optionLabelFactory = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory::class);

            $optionLabel = $optionLabelFactory->create();
            $optionLabel->setStoreId(0);
            $optionLabel->setLabel($labelOrValue);

            $option = $optionFactory->create();
            $option->setLabel($labelOrValue);
            $option->setStoreLabels([$optionLabel]);
            $option->setSortOrder(0);
            $option->setIsDefault(false);

            $optionManagement->add(
                \Magento\Catalog\Model\Product::ENTITY,
                $attribute->getAttributeId(),
                $option
            );

            $attribute = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Eav\Model\Config::class)
                ->getAttribute('catalog_product', $attribute->getAttributeId());
            foreach ($attribute->getOptions() as $option) {
                if (strcasecmp((string) $option->getLabel(), $labelOrValue) === 0) {
                    return (int) $option->getValue();
                }
            }
        } catch (\Exception $e) {
            // Ignore option creation failures and fall back to no linkage.
        }

        return null;
    }
}
