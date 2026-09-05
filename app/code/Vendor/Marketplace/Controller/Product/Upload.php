<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\UploaderFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Vendor\Marketplace\Controller\AbstractVendor;
use Vendor\Marketplace\Model\Session\VendorSession;

class Upload extends AbstractVendor implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    protected $uploaderFactory;
    protected $directoryList;
    protected $resultJsonFactory;
    protected $productRepository;
    protected $productFactory;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        UploaderFactory $uploaderFactory,
        DirectoryList $directoryList,
        JsonFactory $resultJsonFactory,
        ?ProductRepositoryInterface $productRepository = null,
        ?ProductFactory $productFactory = null
    ) {
        parent::__construct($context, $vendorSession);
        $this->uploaderFactory = $uploaderFactory;
        $this->directoryList = $directoryList;
        $this->resultJsonFactory = $resultJsonFactory;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->productRepository = $productRepository ?: $objectManager->get(ProductRepositoryInterface::class);
        $this->productFactory = $productFactory ?: $objectManager->get(ProductFactory::class);
    }

    protected function logImport($message)
    {
        $logFile = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/log/vendor_product_import.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $formatted = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $formatted, FILE_APPEND);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        if (!$this->_vendorSession->isLoggedIn()) {
            $this->messageManager->addErrorMessage(__('Please log in to your vendor account.'));
            return $resultRedirect->setPath('marketplace/account/login');
        }
        $redirectPath = '*/*/import';

        try {
            if (!$this->getRequest()->isPost()) {
                $this->messageManager->addErrorMessage('Invalid request method');
                return $resultRedirect->setPath($redirectPath);
            }

            if (empty($_FILES['import_file']) || empty($_FILES['import_file']['name'])) {
                $this->messageManager->addErrorMessage('No file uploaded (field name: import_file)');
                return $resultRedirect->setPath($redirectPath);
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
            $fileExt = strtolower(pathinfo($savedPath, PATHINFO_EXTENSION));

            $this->logImport("--- NEW IMPORT STARTED: {$saveResult['file']} (Type: $fileExt) ---");

            $rowsData = [];
            if ($fileExt === 'csv') {
                if (($handle = fopen($savedPath, "r")) !== false) {
                    $headerRow = fgetcsv($handle);
                    if ($headerRow) {
                        $headers = [];
                        foreach ($headerRow as $c => $h) {
                            $h = trim((string)$h);
                            $headerAliases = ['Variant Weight' => 'variant_value'];
                            $headers[$c] = $headerAliases[$h] ?? $h;
                        }
                        $rIndex = 2;
                        while (($data = fgetcsv($handle)) !== false) {
                            $row = [];
                            foreach ($data as $c => $val) {
                                $key = $headers[$c] ?? 'col_' . $c;
                                $row[$key] = $val;
                            }
                            $rowsData[$rIndex] = $row;
                            $rIndex++;
                        }
                    }
                    fclose($handle);
                }
            } else {
                if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                    if ($this->getRequest()->isXmlHttpRequest()) {
                        $resultJson = $this->resultJsonFactory->create();
                        return $resultJson->setData([
                            'success' => false,
                            'message' => 'PhpSpreadsheet not installed on server. You can either upload the .csv version directly or run composer require phpoffice/phpspreadsheet on the server.',
                            'path' => $savedPath
                        ]);
                    }
                    $this->messageManager->addErrorMessage(__('PhpSpreadsheet is not installed for Excel (.xlsx) files. Please upload the .csv version directly, or run: composer require phpoffice/phpspreadsheet on the server.'));
                    return $resultRedirect->setPath($redirectPath);
                }

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($savedPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
                $highestRow = $worksheet->getHighestRow();

                $headers = [];
                for ($c = 1; $c <= $highestColumnIndex; $c++) {
                    $colLetter = Coordinate::stringFromColumnIndex($c);
                    $h = trim((string) $worksheet->getCell($colLetter . '1')->getValue());
                    $headerAliases = ['Variant Weight' => 'variant_value'];
                    $h = $headerAliases[$h] ?? $h;
                    $headers[$c] = $h;
                }

                for ($r = 2; $r <= $highestRow; $r++) {
                    $row = [];
                    for ($c = 1; $c <= $highestColumnIndex; $c++) {
                        $key = $headers[$c] ?? 'col_' . $c;
                        $colLetter = Coordinate::stringFromColumnIndex($c);
                        $row[$key] = $worksheet->getCell($colLetter . $r)->getValue();
                    }
                    $rowsData[$r] = $row;
                }
            }

            $created = 0;
            $updated = 0;
            $errors = [];
            $variantLinks = []; // Queue for 2nd pass linking: [parent_sku => [ [child_sku, variant_attr, variant_val, row] ]]

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

            $this->logImport("Vendor ID: " . ($vendorId ?: 'NONE') . " | Total rows to process: " . count($rowsData));

            // Build category display label -> id map
            $categoryMap = [];
            try {
                $categoryCollectionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);
                $catCollection = $categoryCollectionFactory->create();
                $catCollection->addAttributeToSelect(['name', 'path', 'level']);
                $catCollection->addAttributeToFilter('is_active', 1);

                $byId = [];
                foreach ($catCollection as $cat) {
                    $byId[$cat->getId()] = $cat;
                }

                foreach ($catCollection as $cat) {
                    $pathIds = explode('/', (string)$cat->getPath());
                    $names = [];
                    foreach ($pathIds as $pId) {
                        if ($pId <= 2) continue;
                        if (isset($byId[$pId]) && $byId[$pId]->getName()) {
                            $names[] = trim((string)$byId[$pId]->getName());
                        }
                    }
                    if (!empty($names)) {
                        $displayLabel = implode(' > ', $names);
                        $categoryMap[$displayLabel] = (int)$cat->getId();
                        $categoryMap[trim((string)$cat->getName())] = (int)$cat->getId();
                    }
                }
            } catch (\Exception $e) {
                // ignore category map failures
            }

            // Build attribute set map
            $attributeSetMap = [];
            try {
                $eavSetCollectionFactory = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory::class);
                $asCollection = $eavSetCollectionFactory->create();
                $asCollection->setEntityTypeFilter(4);
                foreach ($asCollection as $aset) {
                    $attributeSetMap[trim((string)$aset->getAttributeSetName())] = (int)$aset->getId();
                }
            } catch (\Exception $e) {
                // ignore
            }

            // PASS 1: Create and update all products
            foreach ($rowsData as $r => $rowData) {
                $sku = trim((string) ($rowData['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }

                try {
                    try {
                        $product = $this->productRepository->get($sku);
                        $isNew = false;
                        $existingVendorId = (int)$product->getData('vendor_id');
                        if ($existingVendorId && $vendorId && $existingVendorId !== (int)$vendorId) {
                            $msg = sprintf('Row %d: SKU "%s" belongs to another vendor (%d) and cannot be modified.', $r, $sku, $existingVendorId);
                            $errors[] = $msg;
                            $this->logImport("ERROR: $msg");
                            continue;
                        }
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
                    $variantAttr = isset($rowData['variant_attribute']) ? trim((string)$rowData['variant_attribute']) : ($variantVal !== '' ? 'variant_weight' : '');

                    if ($parentSku !== '' && $typeId === 'simple') {
                        $variantLinks[$parentSku][] = [
                            'child_sku' => $sku,
                            'variant_attr' => $variantAttr,
                            'variant_val' => $variantVal,
                            'row' => $r
                        ];
                    }

                    // Map common fields
                    if ($isNew) {
                        $product->setSku($sku);
                        $product->setTypeId($typeId);
                        
                        $defaultAttrSetId = 9; // Grocery, Food & Supplies
                        $attrValue = isset($rowData['attribute_set']) ? trim((string)$rowData['attribute_set']) : '';
                        $attributeSetId = $defaultAttrSetId;
                        if ($attrValue !== '') {
                            if (isset($attributeSetMap[$attrValue])) {
                                $attributeSetId = (int)$attributeSetMap[$attrValue];
                            } elseif (is_numeric($attrValue)) {
                                $attributeSetId = (int)$attrValue;
                            }
                        }
                        $product->setAttributeSetId($attributeSetId);
                    }

                    $nameValue = isset($rowData['name']) ? trim((string)$rowData['name']) : '';
                    if ($nameValue !== '') {
                        $product->setName($nameValue);
                    } elseif ($parentSku !== '' && $typeId === 'simple' && $variantVal !== '') {
                        $product->setName($parentSku . ' - ' . $variantVal);
                    }

                    if (isset($rowData['description'])) {
                        $product->setDescription($this->sanitizeHtml($rowData['description']));
                    }
                    if (isset($rowData['short_description'])) {
                        $product->setShortDescription($this->sanitizeHtml($rowData['short_description']));
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
                    } else {
                        $product->setStatus(1);
                    }

                    if (isset($rowData['visibility'])) {
                        $product->setVisibility((int)$rowData['visibility']);
                    } else {
                        $product->setVisibility($parentSku !== '' ? 1 : 4);
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
                        $gstVal = trim((string)$rowData['gst_rate']);
                        if ($gstVal !== '') {
                            $resolvedGstId = $this->resolveGstRateOptionId($gstVal);
                            $product->setData('gst_rate', $resolvedGstId !== null ? $resolvedGstId : $gstVal);
                        }
                    }
                    if (isset($rowData['hsn_code'])) {
                        $product->setData('hsn_code', (string)$rowData['hsn_code']);
                    }

                    $rowQty = isset($rowData['qty']) && $rowData['qty'] !== '' ? (float)$rowData['qty'] : null;

                    if ($vendorId) {
                        $product->setData('vendor_id', $vendorId);
                    }

                    // Stock data
                    if ($typeId === 'configurable') {
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

                    // Pre-assign variant attribute option on child product
                    if ($variantAttr !== '' && $variantVal !== '') {
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
                        $attr = $eavConfig->getAttribute('catalog_product', $variantAttr);
                        if ($attr && $attr->getId()) {
                            $optId = $this->resolveAttributeOptionId($attr, $variantVal);
                            if ($optId) {
                                $product->setData($variantAttr, $optId);
                            }
                        }
                    }

                    // Category assignment
                    if (!empty($rowData['category_names'])) {
                        $names = preg_split('/\s*\|\s*/', (string)$rowData['category_names']);
                        $catIds = [];
                        foreach ($names as $name) {
                            $name = trim($name);
                            if (isset($categoryMap[$name])) {
                                $catIds[] = $categoryMap[$name];
                            }
                        }
                        if (!empty($catIds)) {
                            $product->setCategoryIds(array_values(array_unique($catIds)));
                        }
                    }

                    // Save base product
                    $this->productRepository->save($product);

                    // MSI quantity assignment
                    if ($rowQty !== null && $product->getTypeId() !== 'configurable') {
                        try {
                            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                            $sourceCode = 'default';
                            if ($vendorId) {
                                $vendorSourceManager = $objectManager->get(\Vendor\Marketplace\Model\Inventory\VendorSourceManager::class);
                                $sourceCode = $vendorSourceManager->getSourceCode((int)$vendorId) ?: 'default';
                            }
                            $sourceItemFactory = $objectManager->get(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class);
                            $sourceItemsSave = $objectManager->get(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class);
                            $sourceItem = $sourceItemFactory->create();
                            $sourceItem->setSku($sku);
                            $sourceItem->setSourceCode($sourceCode);
                            $sourceItem->setQuantity($rowQty);
                            $sourceItem->setStatus((int)($rowQty > 0));
                            $sourceItemsSave->execute([$sourceItem]);
                        } catch (\Exception $e) {
                            // MSI fallback
                        }
                    }

                    if ($isNew) {
                        $created++;
                        $this->logImport("SUCCESS: Created Row $r - SKU: $sku ($typeId)");
                    } else {
                        $updated++;
                        $this->logImport("SUCCESS: Updated Row $r - SKU: $sku ($typeId)");
                    }
                } catch (\Exception $e) {
                    $err = "Row $r (SKU: $sku): " . $e->getMessage();
                    $errors[] = $err;
                    $this->logImport("ERROR on Row $r (SKU: $sku): " . $e->getMessage());
                }
            }

            // PASS 2: Link Configurable Products with their Child Variants
            $this->logImport("--- PASS 2: Configurable Linkage (" . count($variantLinks) . " parent SKUs) ---");
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $configurableService = $objectManager->get(\Vendor\Marketplace\Model\Product\ConfigurableProductService::class);
            $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);

            foreach ($variantLinks as $pSku => $childItems) {
                try {
                    $parentProduct = $this->productRepository->get($pSku, true, null, true);
                    if ($parentProduct->getTypeId() !== 'configurable') {
                        $this->logImport("NOTICE: Parent $pSku is not configurable (Type: {$parentProduct->getTypeId()}) - skipping linkage.");
                        continue;
                    }

                    $childIds = [];
                    $configuredAttrId = null;

                    foreach ($childItems as $item) {
                        $cSku = $item['child_sku'];
                        $vAttr = $item['variant_attr'] ?: 'variant_weight';
                        $vVal = $item['variant_val'];

                        try {
                            $childProduct = $this->productRepository->get($cSku, true, null, true);
                            $attr = $eavConfig->getAttribute('catalog_product', $vAttr);
                            if ($attr && $attr->getId()) {
                                $configuredAttrId = (int)$attr->getAttributeId();
                                $optId = $this->resolveAttributeOptionId($attr, $vVal);
                                if ($optId) {
                                    $childProduct->setData($vAttr, $optId);
                                    $childProduct->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
                                    if ($vendorId) {
                                        $childProduct->setData('vendor_id', $vendorId);
                                    }
                                    $this->productRepository->save($childProduct);
                                    $childIds[] = (int)$childProduct->getId();
                                    $this->logImport("PASS 2: Prepared child $cSku (ID: {$childProduct->getId()}) with $vAttr=$optId for parent $pSku");
                                } else {
                                    $this->logImport("PASS 2 WARNING: Option ID could not be resolved for child $cSku with value '$vVal'");
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logImport("PASS 2 ERROR on child $cSku: " . $e->getMessage());
                        }
                    }

                    $childIds = array_values(array_unique(array_filter($childIds)));
                    if (!empty($childIds) && $configuredAttrId) {
                        $configurableService->linkProductsToConfigurable(
                            $parentProduct,
                            $configuredAttrId,
                            $childIds
                        );
                        if ($vendorId) {
                            $parentProduct->setData('vendor_id', $vendorId);
                            $this->productRepository->save($parentProduct);
                        }
                        $this->logImport("PASS 2 SUCCESS: Configurable Parent $pSku linked with " . count($childIds) . " children (IDs: " . implode(',', $childIds) . ")");
                    }
                } catch (\Exception $e) {
                    $err = "Configurable Linkage Error ($pSku): " . $e->getMessage();
                    $errors[] = $err;
                    $this->logImport("PASS 2 FAILED for parent $pSku: " . $e->getMessage());
                }
            }

            $this->logImport("--- IMPORT COMPLETED: Created=$created, Updated=$updated, Errors=" . count($errors) . " ---");

            $this->messageManager->addSuccessMessage(__('Import completed. Created: %1, Updated: %2', $created, $updated));
            if (!empty($errors)) {
                $this->messageManager->addErrorMessage(__('Some rows had issues: %1', implode(' | ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' (and ' . (count($errors) - 5) . ' more - see var/log/vendor_product_import.log)' : '')));
            }
            return $resultRedirect->setPath($redirectPath);

        } catch (\Exception $e) {
            $this->logImport("FATAL EXCEPTION: " . $e->getMessage());
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath($redirectPath);
        }
    }

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
            // fallback
        }

        return null;
    }

    protected function resolveGstRateOptionId($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\s*(\d+(?:\.\d+)?)/', $value, $m)) {
            $rateNum = (string)(float)$m[1];
        } else {
            $rateNum = $value;
        }

        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
            $attr = $eavConfig->getAttribute('catalog_product', 'gst_rate');
            if ($attr && $attr->getId()) {
                foreach ($attr->getOptions() as $opt) {
                    $label = trim((string)$opt->getLabel());
                    $val = (string)$opt->getValue();
                    if ($val === $value || $label === $rateNum || (is_numeric($label) && (float)$label === (float)$rateNum)) {
                        return (int)$val;
                    }
                }
            }
        } catch (\Exception $e) {
            // fallback
        }

        return is_numeric($value) ? (int)$value : null;
    }

    protected function sanitizeHtml($html)
    {
        if (!is_string($html) || strpos($html, '<') === false) {
            return (string)$html;
        }
        $allowed = ['class', 'width', 'height', 'style', 'alt', 'title', 'border', 'id', 'href', 'target', 'role', 'aria-hidden', 'aria-label'];
        return preg_replace_callback('/<([a-z0-9]+)\s+([^>]+)>/i', function($matches) use ($allowed) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];
            preg_match_all('/([a-z0-9_-]+)(?:\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\s>]+)))?/i', $attrs, $attrMatches, PREG_SET_ORDER);
            $cleanAttrs = [];
            foreach ($attrMatches as $am) {
                $attrName = strtolower($am[1]);
                if (in_array($attrName, $allowed) || strpos($attrName, 'data-pb-') === 0 || strpos($attrName, 'data-element') === 0) {
                    $val = $am[2] ?? ($am[3] ?? ($am[4] ?? ''));
                    $cleanAttrs[] = $attrName . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
                }
            }
            return '<' . $tag . (empty($cleanAttrs) ? '' : ' ' . implode(' ', $cleanAttrs)) . '>';
        }, $html);
    }
}