<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection as AttributeSetCollection;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem;
use Vendor\Marketplace\Model\VendorFactory;

class DownloadTemplate extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $fileFactory;
    protected $filesystem;
    protected $categoryCollectionFactory;
    protected $attributeSetCollection;
    protected $directoryList;
    protected $productRepository;
    protected $categoryFactory;

    public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
    ?\Magento\Framework\App\Filesystem\DirectoryList $directoryList = null,
    ?\Magento\Catalog\Api\ProductRepositoryInterface $productRepository = null,
    ?\Magento\Catalog\Model\CategoryFactory $categoryFactory = null,
    ?\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory = null,
    ?AttributeSetCollection $attributeSetCollection = null
) {
    parent::__construct($context);
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $this->fileFactory = $fileFactory;
    $this->directoryList = $directoryList ?: $objectManager->get(\Magento\Framework\App\Filesystem\DirectoryList::class);
    $this->productRepository = $productRepository ?: $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    $this->categoryFactory = $categoryFactory ?: $objectManager->get(\Magento\Catalog\Model\CategoryFactory::class);
    $this->categoryCollectionFactory = $categoryCollectionFactory ?: $objectManager->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);
    $this->attributeSetCollection = $attributeSetCollection ?: $objectManager->create(AttributeSetCollection::class);

    // initialize additional services to avoid dynamic property creation (PHP 8.2+)
    $this->filesystem = $objectManager->get(\Magento\Framework\Filesystem::class);
    $this->customerSession = $objectManager->get(\Magento\Customer\Model\Session::class);
    $this->vendorFactory = $objectManager->get(VendorFactory::class);
}

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            $this->customerSession->logout();
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $categories = $this->getCategoryOptions();
        $attributeSets = $this->getAttributeSetOptions();
        $relativePath = 'tmp/vendor-product-import-template.xlsx';
        $absolutePath = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->getAbsolutePath($relativePath);
        $this->buildXlsxTemplate($absolutePath, $categories, $attributeSets, $vendor);

        return $this->fileFactory->create(
            'vendor-product-import-template.xlsx',
            ['type' => 'filename', 'value' => $relativePath, 'rm' => true],
            DirectoryList::VAR_DIR,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    protected function getCategoryOptions()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'path', 'level']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->setOrder('path', 'ASC');

        $categories = [];
        $categoryNames = [];

        foreach ($collection as $category) {
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

            $categories[] = [
                'id' => (int) $category->getId(),
                'name' => (string) $category->getName(),
                'display_label' => $displayLabel,
            ];

            $categoryNames[$category->getId()] = (string) $category->getName();
        }

        return $categories;
    }

    protected function getAttributeSetOptions()
    {
        $collection = $this->attributeSetCollection;
        $collection->setEntityTypeFilter(4); // 4 is catalog product entity type
        $collection->setOrder('attribute_set_name', 'ASC');

        $attributeSets = [];
        foreach ($collection as $attributeSet) {
            $attributeSets[] = [
                'id' => (int) $attributeSet->getId(),
                'name' => (string) $attributeSet->getAttributeSetName(),
            ];
        }

        return $attributeSets;
    }

    protected function buildXlsxTemplate($filePath, array $categories, array $attributeSets, $vendor)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException(__('ZipArchive extension is required to generate the Excel template.'));
        }

        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $headers = [
            'product_type', 'attribute_set', 'sku', 'name', 'description', 'short_description', 'meta_title', 'meta_keyword', 'meta_description', 'price', 'cost', 'weight',
            'category_names', 'status', 'visibility', 'image', 'image_2', 'image_3',
            'image_4', 'image_5', 'image_6', 'image_7', 'video_url', 'gst_rate', 'hsn_code', 'barcode',
            'parent_sku', 'parent_name', 'variant_attribute', 'Variant Weight', 'qty'
        ];

        $templateRows = $this->getVendorProductRows($vendor, $categories, $attributeSets);
        $gstRates = $this->getGstRates();

        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(__('Unable to create the Excel template.'));
        }

        $categoryCount = count($categories);
        $dataValidationFormula = $categoryCount > 0 ? 'CategoryNames' : '';

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFromString('docProps/app.xml', $this->buildAppXml());
        $zip->addFromString('docProps/core.xml', $this->buildCoreXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml($categoryCount > 0, count($attributeSets) > 0, count($gstRates)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildTemplateSheetXml($headers, $templateRows, $dataValidationFormula, $gstRates));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->buildCategorySheetXml($categories));
        $zip->addFromString('xl/worksheets/sheet3.xml', $this->buildInstructionsSheetXml());
        $zip->addFromString('xl/worksheets/sheet4.xml', $this->buildRequirementsSheetXml($headers));
        $zip->addFromString('xl/worksheets/sheet5.xml', $this->buildAttributesSheetXml());
            $zip->addFromString('xl/worksheets/sheet6.xml', $this->buildChangesToTemplateSheetXml());
            $zip->addFromString('xl/worksheets/sheet7.xml', $this->buildImagesSheetXml());
            $zip->addFromString('xl/worksheets/sheet8.xml', $this->buildDataDefinitionsSheetXml());
            $zip->addFromString('xl/worksheets/sheet9.xml', $this->buildBrowseDataSheetXml($categories));
            $zip->addFromString('xl/worksheets/sheet10.xml', $this->buildConditionsListSheetXml());
            $zip->addFromString('xl/worksheets/sheet11.xml', $this->buildValidValuesSheetXml());
            $zip->addFromString('xl/worksheets/sheet12.xml', $this->buildDropdownListsSheetXml($attributeSets, $gstRates));
            $zip->addFromString('xl/worksheets/sheet13.xml', $this->buildAttributePTDMAPSheetXml());
        $zip->close();
    }

    protected function buildContentTypesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet5.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet6.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet7.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet8.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet9.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet10.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet11.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet12.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet13.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>';
    }

    protected function buildRootRelsXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    protected function buildAppXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Microsoft Excel</Application>'
            . '</Properties>';
    }

    protected function buildCoreXml()
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Vendor Marketplace</dc:creator>'
            . '<cp:lastModifiedBy>Vendor Marketplace</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    protected function buildWorkbookXml($hasCategories, $hasAttributeSets, $gstCount = 0)
    {
        $definedNames = '<definedNames>';
        if ($hasCategories) {
            $definedNames .= '<definedName name="CategoryNames">\'Categories\'!$A$2:$A$' . $this->getCategoryRowCount($hasCategories) . '</definedName>';
        }
        if ($hasAttributeSets) {
            $definedNames .= '<definedName name="AttributeSets">\'Dropdown Lists\'!$B$2:$B$' . (2 + 50) . '</definedName>';
        }
        if ($gstCount > 0) {
            $definedNames .= '<definedName name="GSTRates">\'Dropdown Lists\'!$D$2:$D$' . ($gstCount + 1) . '</definedName>';
        }
        $definedNames .= '<definedName name="ProductTypes">\'Dropdown Lists\'!$A$2:$A$3</definedName>';
        $definedNames .= '</definedNames>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Template" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Categories" sheetId="2" r:id="rId2" state="hidden"/>'
            . '<sheet name="Instructions" sheetId="3" r:id="rId3"/>'
            . '<sheet name="Requirements" sheetId="4" r:id="rId4"/>'
            . '<sheet name="Attributes" sheetId="5" r:id="rId5" state="hidden"/>'
                        . '<sheet name="Changes to the template" sheetId="6" r:id="rId6"/>'
                        . '<sheet name="Images" sheetId="7" r:id="rId7"/>'
                        . '<sheet name="Data Definitions" sheetId="8" r:id="rId8"/>'
                        . '<sheet name="Browse Data" sheetId="9" r:id="rId9"/>'
                        . '<sheet name="Conditions List" sheetId="10" r:id="rId10" state="hidden"/>'
                        . '<sheet name="Valid Values" sheetId="11" r:id="rId11"/>'
                        . '<sheet name="Dropdown Lists" sheetId="12" r:id="rId12" state="hidden"/>'
                        . '<sheet name="AttributePTDMAP" sheetId="13" r:id="rId13" state="hidden"/>'
            . '</sheets>'
            . $definedNames
            . '</workbook>';
    }

    protected function buildWorkbookRelsXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>'
            . '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet5.xml"/>'
            . '<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet6.xml"/>'
            . '<Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet7.xml"/>'
            . '<Relationship Id="rId8" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet8.xml"/>'
            . '<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet9.xml"/>'
            . '<Relationship Id="rId10" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet10.xml"/>'
            . '<Relationship Id="rId11" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet11.xml"/>'
            . '<Relationship Id="rId12" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet12.xml"/>'
            . '<Relationship Id="rId13" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet13.xml"/>'
            . '<Relationship Id="rId14" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected function buildStylesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><sz val="11"/><name val="Calibri"/><b/><color rgb="FFFFFFFF"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD32F2F"/><bgColor rgb="FFD32F2F"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    protected function buildTemplateSheetXml(array $headers, array $rows, $dataValidationFormula, array $gstRates = [])
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $xml .= $this->buildRowXml(1, $headers, true);
        $rowNumber = 2;
        foreach ($rows as $row) {
            $xml .= $this->buildRowXml($rowNumber, $row, false);
            $rowNumber++;
        }
        $xml .= '</sheetData>';

        $validationCount = 4;
        if (!empty($gstRates)) {
            $validationCount++;
        }
        $xml .= '<dataValidations count="' . $validationCount . '">';
        $xml .= '<dataValidation type="list" allowBlank="0" showErrorMessage="1" showInputMessage="1" sqref="A2:A1048576">';
        $xml .= '<formula1>ProductTypes</formula1>';
        $xml .= '</dataValidation>';
        $xml .= '<dataValidation type="list" allowBlank="0" showErrorMessage="1" showInputMessage="1" sqref="B2:B1048576">';
        $xml .= '<formula1>AttributeSets</formula1>';
        $xml .= '</dataValidation>';
        $xml .= '<dataValidation type="whole" operator="equal" allowBlank="0" showErrorMessage="1" showInputMessage="1" sqref="O2:O1048576">';
        $xml .= '<formula1>4</formula1>';
        $xml .= '</dataValidation>';
        if ($dataValidationFormula) {
            $xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" showInputMessage="1" sqref="M2:M1048576">';
            $xml .= '<formula1>' . $this->xmlEscape($dataValidationFormula) . '</formula1>';
            $xml .= '</dataValidation>';
        }
        if (!empty($gstRates)) {
            $xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" showInputMessage="1" sqref="X2:X1048576">';
            $xml .= '<formula1>GSTRates</formula1>';
            $xml .= '</dataValidation>';
        }
        $xml .= '</dataValidations>';

        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildCategorySheetXml(array $categories)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetData>';
        $xml .= $this->buildRowXml(1, ['Category Names'], true);
        $rowNumber = 2;
        foreach ($categories as $category) {
            $xml .= $this->buildRowXml($rowNumber, [$category['display_label']], false);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildRowXml($rowNumber, array $values, $header = false)
    {
        $xml = '<row r="' . (int) $rowNumber . '">';
        foreach ($values as $index => $value) {
            $column = $this->columnLetter($index + 1);
            $cellReference = $column . $rowNumber;
            $style = $header ? ' s="1"' : '';
            $xml .= '<c r="' . $cellReference . '" t="inlineStr"' . $style . '><is><t>' . $this->xmlEscape((string) $value) . '</t></is></c>';
        }
        $xml .= '</row>';

        return $xml;
    }

    protected function columnLetter($index)
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = (int) floor($index / 26);
        }

        return $letters;
    }

    protected function xmlEscape($value)
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function getCategoryRowCount($hasCategories)
    {
        if (!$hasCategories) {
            return 1;
        }

        $categories = $this->getCategoryOptions();
        return max(2, count($categories) + 1);
    }

    protected function buildInstructionsSheetXml()
    {
        $instructions = [
            ['VENDOR PRODUCT EXPORT / IMPORT INSTRUCTIONS'],
            [],
            ['OVERVIEW:'],
            ['This workbook exports the current vendor product catalog using the same column layout as the vendor product form.'],
            ['You can review, edit, and re-import the Template sheet if needed.'],
            [],
            ['REQUIRED FIELDS:'],
            ['- product_type: simple or configurable'],
            ['- sku: Unique product identifier'],
            ['- name: Product title'],
            ['- price: Selling price'],
            ['- category_ids: Comma-separated category IDs'],
            ['- category_names: Breadcrumb names for reference'],
            [],
            ['OPTIONAL FIELDS:'],
            ['- description, cost, weight, image_2 through image_7, video_url, etc.'],
            [],
            ['PRODUCT TYPES:'],
            ['Simple: Single product with one SKU'],
            ['Configurable: Parent product with variants (children)'],
            [],
            ['FOR CONFIGURABLE PRODUCTS:'],
            ['1. Enter parent row with type=configurable, parent_sku, parent_name'],
            ['2. Enter child rows with type=simple, same parent_sku, variant_attribute, Variant Weight'],
            ['3. Each child requires unique SKU and pricing'],
            [],
            ['CATEGORY SELECTION:'],
            ['Use the Categories sheet to view available categories.'],
            ['Enter the category IDs in category_ids and use category_names for matching breadcrumbs (e.g., Home > Men > Shirts).'],
            [],
            ['IMAGES:'],
            ['Provide full URLs to publicly accessible images.'],
            ['Format: https://example.com/image.jpg'],
            [],
            ['TIPS:'],
            ['- Always include unique SKU for each product/variant'],
            ['- Use consistent naming conventions'],
            ['- Test with a few products before bulk import'],
            ['- Contact support if you have questions'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($instructions as $row) {
            if (!empty($row)) {
                $xml .= $this->buildRowXml($rowNumber, $row, false);
            }
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function getVendorProductRows($vendor, array $categories, array $attributeSets)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productCollectionFactory = $objectManager->get(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class);
        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
        $sourceItemsBySku = $objectManager->get(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class);
        $vendorSourceManager = $objectManager->get(\Vendor\Marketplace\Model\Inventory\VendorSourceManager::class);
        $configurableResource = $objectManager->get(\Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::class);
        $configurableType = $objectManager->get(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::class);

        $categoryLabels = [];
        foreach ($categories as $category) {
            $categoryLabels[(int) $category['id']] = (string) $category['display_label'];
        }

        $attributeSetLabels = [];
        foreach ($attributeSets as $attributeSet) {
            $attributeSetLabels[(int) $attributeSet['id']] = (string) $attributeSet['name'];
        }

        $collection = $productCollectionFactory->create();
        $collection->addAttributeToSelect([
            'entity_id', 'type_id', 'attribute_set_id', 'sku', 'name', 'description', 'short_description', 
            'meta_title', 'meta_keyword', 'meta_description', 'price', 'cost', 'weight',
            'status', 'visibility', 'image', 'small_image', 'thumbnail', 'video_url', 'vendor_id', 'gst_rate', 'hsn_code',
            // common barcode attributes: try barcode, then ean, upc, gtin
            'barcode', 'ean', 'upc', 'gtin'
        ]);
        $collection->addAttributeToFilter('vendor_id', (int) $vendor->getId());
        $collection->addAttributeToFilter('sku', ['neq' => 'wallet-recharge']);
        $collection->setOrder('created_at', 'DESC');

        $rows = [];
        $parentCache = [];
        foreach ($collection as $productItem) {
            try {
                $product = $this->productRepository->get($productItem->getSku());
            } catch (\Exception $e) {
                $product = $productItem;
            }

            $rows[] = $this->buildVendorProductRow(
                $product,
                $categoryLabels,
                $attributeSetLabels,
                $storeManager,
                $stockRegistry,
                (int) $vendor->getId(),
                $sourceItemsBySku,
                $vendorSourceManager,
                $configurableResource,
                $configurableType,
                $parentCache
            );
        }


        if (empty($rows)) {
            $defaultAttrSet = current($attributeSetLabels) ?: 'Default';
            // 1. Simple Product Example
            $rows[] = [
                'simple', $defaultAttrSet, 'EXAMPLE-SIMPLE-SKU', 'Example Simple Product', 'Description here...', 'Short description',
                '', '', '', 299.00, 150.00, 0.5,
                '', 1, 4, 'https://example.com/image.jpg', '', '', '', '', '', '', '', '', '', '',
                '', '', '', '', 100
            ];
            // 2. Configurable Parent Example
            $rows[] = [
                'configurable', $defaultAttrSet, 'EXAMPLE-CONF-SKU', 'Example Configurable Product', 'Description here...', 'Short desc',
                '', '', '', 0, 0, '',
                '', 1, 4, 'https://example.com/image.jpg', '', '', '', '', '', '', '', '', '', '',
                '', '', '', '', 0
            ];
            // 3. Configurable Child 1 (15g)
            $rows[] = [
                'simple', $defaultAttrSet, 'EXAMPLE-CONF-SKU-15g', 'Example Configurable Product - 15g', 'Description here...', 'Short desc',
                '', '', '', 199.00, 100.00, 0.015,
                '', 1, 1, 'https://example.com/image-15g.jpg', '', '', '', '', '', '', '', '', '', '',
                'EXAMPLE-CONF-SKU', 'Example Configurable Product', 'Variant Weight', '15g', 50
            ];
            // 4. Configurable Child 2 (40g)
            $rows[] = [
                'simple', $defaultAttrSet, 'EXAMPLE-CONF-SKU-40g', 'Example Configurable Product - 40g', 'Description here...', 'Short desc',
                '', '', '', 399.00, 200.00, 0.040,
                '', 1, 1, 'https://example.com/image-40g.jpg', '', '', '', '', '', '', '', '', '', '',
                'EXAMPLE-CONF-SKU', 'Example Configurable Product', 'Variant Weight', '40g', 30
            ];
        }

        return $rows;
    }

    protected function buildVendorProductRow(
        $product,
        array $categoryLabels,
        array $attributeSetLabels,
        $storeManager,
        $stockRegistry,
        $vendorId,
        $sourceItemsBySku,
        $vendorSourceManager,
        $configurableResource,
        $configurableType,
        &$parentCache
    )
    {
        $categoryIds = array_filter(array_map('intval', (array) $product->getCategoryIds()));
        $categoryNames = [];
        foreach ($categoryIds as $categoryId) {
            if (isset($categoryLabels[$categoryId])) {
                $categoryNames[] = $categoryLabels[$categoryId];
            }
        }

        // If parent and child breadcrumbs are both assigned, keep the most specific child path.
        $categoryNames = array_values(array_unique(array_filter($categoryNames)));
        usort($categoryNames, static function ($a, $b) {
            return strlen((string) $a) <=> strlen((string) $b);
        });

        $filteredCategoryNames = [];
        foreach ($categoryNames as $name) {
            $isParentOfExisting = false;
            foreach ($categoryNames as $otherName) {
                if ($name !== $otherName && strpos((string) $otherName, (string) $name . ' > ') === 0) {
                    $isParentOfExisting = true;
                    break;
                }
            }

            if (!$isParentOfExisting) {
                $filteredCategoryNames[] = $name;
            }
        }
        $categoryNames = $filteredCategoryNames;

        $attributeSetId = (int) $product->getAttributeSetId();
        $attributeSetName = $attributeSetLabels[$attributeSetId] ?? (string) $attributeSetId;
        $mediaBaseUrl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';

        $images = [];
        $baseImage = (string) $product->getData('image');
        if ($baseImage && $baseImage !== 'no_selection') {
            $images[] = $mediaBaseUrl . $baseImage;
        }

        $galleryImages = $product->getMediaGalleryImages();
        if ($galleryImages) {
            foreach ($galleryImages as $galleryImage) {
                $file = (string) $galleryImage->getFile();
                if ($file && $file !== 'no_selection') {
                    $images[] = $mediaBaseUrl . $file;
                }
            }
        }

        $images = array_values(array_unique(array_filter($images)));
        while (count($images) < 7) {
            $images[] = '';
        }

        $qty = 0;
        try {
            $sourceCode = $vendorSourceManager->getSourceCode((int) $vendorId);
            $sourceItems = $sourceItemsBySku->execute((string) $product->getSku());
            foreach ($sourceItems as $sourceItem) {
                if ($sourceItem->getSourceCode() === $sourceCode) {
                    $qty = (float) $sourceItem->getQuantity();
                    break;
                }
            }

            // Fallback to legacy stock if no vendor source item is found.
            if ($qty === 0.0) {
                $stockItem = $stockRegistry->getStockItem((int) $product->getId());
                if ($stockItem) {
                    $qty = (float) $stockItem->getQty();
                }
            }
        } catch (\Exception $e) {
            $qty = 0;
        }

        $cost = $product->getData('cost');
        $weight = $product->getData('weight');
        $variantLink = $this->getVariantLinkData($product, $configurableResource, $configurableType, $parentCache);

        // no vendor columns in export; keep vendor_id available via product attribute if needed server-side

        return [
            (string) $product->getTypeId(),
            $attributeSetName,
            (string) $product->getSku(),
            (string) $product->getName(),
            (string) $product->getDescription(),
            (string) $product->getData('short_description'),
            (string) $product->getData('meta_title'),
            (string) $product->getData('meta_keyword'),
            (string) $product->getData('meta_description'),
            (float) $product->getPrice(),
            ($cost === null || $cost === '') ? '' : (float) $cost,
            ($weight === null || $weight === '') ? '' : (float) $weight,
            implode(' | ', $categoryNames),
            (int) $product->getStatus(),
            4,
            $images[0],
            $images[1],
            $images[2],
            $images[3],
            $images[4],
            $images[5],
            $images[6],
            (string) $product->getData('video_url'),
            $this->formatGstRateForDropdown($product->getData('gst_rate'), $product),
            (string) $product->getData('hsn_code'),
            // choose first non-empty barcode-like attribute
            (string) ($product->getData('barcode') ?: $product->getData('ean') ?: $product->getData('upc') ?: $product->getData('gtin') ?: ''),
            $variantLink['parent_sku'],
            $variantLink['parent_name'],
            $variantLink['variant_attribute'],
            $variantLink['variant_value'],
            $qty,
        ];
    }

    protected function getVariantLinkData($product, $configurableResource, $configurableType, array &$parentCache)
    {
        $link = [
            'parent_sku' => '',
            'parent_name' => '',
            'variant_attribute' => '',
            'variant_value' => '',
        ];

        if ((string) $product->getTypeId() !== 'simple') {
            return $link;
        }

        try {
            $parentIds = $configurableResource->getParentIdsByChild((int) $product->getId());
        } catch (\Exception $e) {
            return $link;
        }

        if (empty($parentIds)) {
            return $link;
        }

        $parentId = (int) reset($parentIds);
        if (!isset($parentCache[$parentId])) {
            try {
                $parentCache[$parentId] = $this->productRepository->getById($parentId);
            } catch (\Exception $e) {
                $parentCache[$parentId] = null;
            }
        }

        $parent = $parentCache[$parentId];
        if (!$parent) {
            return $link;
        }

        $link['parent_sku'] = (string) $parent->getSku();
        $link['parent_name'] = (string) $parent->getName();

        try {
            $configurableAttributes = $configurableType->getConfigurableAttributes($parent);
            foreach ($configurableAttributes as $configurableAttribute) {
                $productAttribute = $configurableAttribute->getProductAttribute();
                if (!$productAttribute) {
                    continue;
                }

                $attributeCode = (string) $productAttribute->getAttributeCode();
                $rawValue = $product->getData($attributeCode);
                if ($rawValue === null || $rawValue === '') {
                    continue;
                }

                $displayValue = $product->getAttributeText($attributeCode);
                if (is_array($displayValue)) {
                    $displayValue = implode(', ', $displayValue);
                }
                if ($displayValue === false || $displayValue === null || $displayValue === '') {
                    $displayValue = (string) $rawValue;
                }

                $link['variant_attribute'] = $attributeCode;
                $link['variant_value'] = (string) $displayValue;
                break;
            }
        } catch (\Exception $e) {
            return $link;
        }

        return $link;
    }

    protected function buildRequirementsSheetXml($headers)
    {
        $requirements = [
            array_merge(['Field Name'], ['Type'], ['Required'], ['Notes']),
        ];

        $fieldDetails = [
            'product_type' => ['string', 'Yes', 'simple or configurable'],
            'attribute_set' => ['string', 'Yes', 'Default attribute set'],
            'sku' => ['string', 'Yes', 'Unique per product'],
            'name' => ['string', 'Yes', 'Product title, 3-255 chars'],
            'description' => ['text', 'No', 'Product description'],
            'short_description' => ['text', 'No', 'Short description for listings'],
            'meta_title' => ['string', 'No', 'Search engine meta title'],
            'meta_keyword' => ['string', 'No', 'Search engine meta keywords'],
            'meta_description' => ['string', 'No', 'Search engine meta description'],
            'price' => ['decimal', 'Yes', 'Selling price, min 0.01'],
            'cost' => ['decimal', 'No', 'Cost price for reference'],
            'weight' => ['decimal', 'No', 'Product weight in kg'],
            'category_ids' => ['string', 'Yes', 'Comma-separated category IDs'],
            'category_names' => ['string', 'No', 'Breadcrumb category labels for reference'],
            'status' => ['integer', 'Yes', '1=Enabled, 0=Disabled'],
            'visibility' => ['integer', 'Yes', '1=None, 2=Catalog, 3=Search, 4=Both'],
            'image' => ['url', 'No', 'Primary product image URL'],
            'image_2' => ['url', 'No', 'Additional product image URL'],
            'image_3' => ['url', 'No', 'Additional product image URL'],
            'image_4' => ['url', 'No', 'Additional product image URL'],
            'image_5' => ['url', 'No', 'Additional product image URL'],
            'image_6' => ['url', 'No', 'Additional product image URL'],
            'image_7' => ['url', 'No', 'Additional product image URL'],
            'video_url' => ['url', 'No', 'Optional product video URL'],
            'gst_rate' => ['decimal', 'No', 'GST percentage (e.g., 5, 12, 18)'],
            'hsn_code' => ['string', 'No', 'HSN/SAC code for tax compliance'],
            'barcode' => ['string', 'No', 'Product barcode / EAN / UPC / GTIN'],
            'parent_sku' => ['string', 'For variants', 'Links child to parent'],
            'parent_name' => ['string', 'For variants', 'Parent product name'],
            'variant_attribute' => ['string', 'For variants', 'e.g., color, size'],
            'variant_value' => ['string', 'For variants', 'e.g., Red, Large'],
            'Variant Weight' => ['string', 'For variants', 'e.g., 15g, 40g, 100g'],
            'qty' => ['integer', 'Yes', 'Stock quantity'],
        ];

        foreach ($headers as $field) {
            if (isset($fieldDetails[$field])) {
                $details = $fieldDetails[$field];
                $requirements[] = [$field, $details[0], $details[1], $details[2]];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($requirements as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildAttributesSheetXml()
    {
        $attributes = [
            ['Attribute Code', 'Attribute Label', 'Type', 'Values/Notes'],
            ['color', 'Color', 'select', 'Red, Blue, Green, Black, White, etc.'],
            ['size', 'Size', 'select', 'XS, S, M, L, XL, XXL'],
            ['material', 'Material', 'text', 'Cotton, Polyester, Wool, etc.'],
            ['brand', 'Brand', 'text', 'Optional brand name'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($attributes as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';

        return $xml;
    }
    protected function buildChangesToTemplateSheetXml()
    {
        $rows = [
            ['Version', 'Date', 'Notes'],
            ['1.0', gmdate('Y-m-d'), 'Initial vendor template generated'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildImagesSheetXml()
    {
        $rows = [
            ['Image Type', 'Recommended Size', 'Notes'],
            ['Main (base_image)', '1200x1200', 'Primary product image, high quality'],
            ['Small (small_image)', '500x500', 'Thumbnail for listings'],
            ['Thumbnail (thumbnail_image)', '200x200', 'Tiny thumbnail for carts'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildDataDefinitionsSheetXml()
    {
        $rows = [
            ['Field', 'Definition', 'Example'],
            ['sku', 'Unique identifier for product', 'SKU-001'],
            ['price', 'Selling price in store currency', '99.99'],
            ['category_ids', 'Comma-separated category IDs', '12,18'],
            ['category_names', 'Breadcrumb category path', 'Home > Men > Shirts'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildBrowseDataSheetXml(array $categories)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetData>';
        $xml .= $this->buildRowXml(1, ['Available Categories'], true);
        $rowNumber = 2;
        foreach ($categories as $category) {
            $xml .= $this->buildRowXml($rowNumber, [$category['display_label']], false);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildConditionsListSheetXml()
    {
        $rows = [
            ['Condition', 'Description'],
            ['New', 'Brand new, unused, unopened, undamaged item'],
            ['Used - Like New', 'Used item but looks new'],
            ['Used - Good', 'Shows wear but fully functional'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildValidValuesSheetXml()
    {
        $rows = [
            ['Field', 'Valid Values'],
            ['product_type', 'simple, configurable'],
            ['status', '0 (Disabled), 1 (Enabled)'],
            ['visibility', '1,2,3,4'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildDropdownListsSheetXml(array $attributeSets = [], array $gstRates = [])
    {
        $rows = [
            ['ProductTypes', 'AttributeSets', 'Sizes', 'GSTRates'],
            ['simple', '', 'XS', ''],
            ['configurable', '', 'S', ''],
        ];

        // Add attribute sets starting from row 2
        $attrSetIdx = 1;
        foreach ($attributeSets as $attributeSet) {
            if (isset($rows[$attrSetIdx])) {
                $rows[$attrSetIdx][1] = $attributeSet['name'];
            } else {
                $rows[] = ['', $attributeSet['name'], '', ''];
            }
            $attrSetIdx++;
        }

        // Add color palette (columns for reference)
        $colors = ['Red', 'Blue', 'Green', 'Black', 'White'];
        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        
        // Rebuild with proper structure: ProductTypes | AttributeSets | Sizes | GSTRates
        $rows = [
            ['ProductTypes', 'AttributeSets', 'Sizes', 'GSTRates'],
            ['simple', '', 'XS', ''],
            ['configurable', '', 'S', ''],
        ];

        // Fill in attribute sets
        foreach ($attributeSets as $idx => $attributeSet) {
            if (!isset($rows[$idx + 2])) {
                $rows[$idx + 2] = ['', '', '', ''];
            }
            $rows[$idx + 2][1] = $attributeSet['name'];
        }

        // Ensure we have enough rows and add sizes
        $maxRows = max(count($attributeSets) + 2, count($sizes) + 1, count($gstRates) + 1);
        for ($i = 2; $i < $maxRows; $i++) {
            if (!isset($rows[$i])) {
                $rows[$i] = ['', '', '', ''];
            }
            if (isset($sizes[$i - 2])) {
                $rows[$i][2] = $sizes[$i - 2];
            }
            if (isset($gstRates[$i - 2])) {
                $rows[$i][3] = $gstRates[$i - 2];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function buildAttributePTDMAPSheetXml()
    {
        $rows = [
            ['Attribute', 'Product Types'],
            ['color', 'configurable'],
            ['size', 'configurable'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetData>';
        $rowNumber = 1;
        foreach ($rows as $idx => $row) {
            $isHeader = ($idx === 0);
            $xml .= $this->buildRowXml($rowNumber, $row, $isHeader);
            $rowNumber++;
        }
        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected function getGstRates()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $collection = $objectManager->create(\Tax\IndianGST\Model\ResourceModel\Rate\Collection::class);
            $collection->addFieldToFilter('is_active', 1);
            $collection->setOrder('total_rate', 'ASC');
            $rates = [];
            foreach ($collection as $item) {
                $totalRate = $item->getData('total_rate');
                if ($totalRate === null || $totalRate === '') {
                    $totalRate = $item->getData('igst_rate') ?: ($item->getData('cgst_rate') + $item->getData('sgst_rate'));
                }
                
                if ($totalRate !== null && $totalRate !== '') {
                    $label = (string)(float)$totalRate;
                    if (!in_array($label, $rates, true)) {
                        $rates[] = $label;
                    }
                }
            }
            return !empty($rates) ? $rates : ['0', '3', '5', '12', '18', '28'];
        } catch (\Exception $e) {
            return ['0', '3', '5', '12', '18', '28'];
        }
    }

    protected function formatGstRateForDropdown($gstRateValue, $product = null)
    {
        if (empty($gstRateValue)) {
            return '';
        }

        $value = trim((string) $gstRateValue);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        // If the value is numeric it might be either a stored percentage, a rate_id, or an attribute option id.
        if (is_numeric($value)) {
            // 1) Try resolving numeric -> Tax\IndianGST rate by rate_id
            try {
                $rate = $this->findGstRateByField($objectManager, 'rate_id', $value);
                if ($rate && $rate->getId()) {
                    return $this->normalizeGstRateNumber($rate->getData('total_rate') ?: $rate->getData('igst_rate'));
                }
            } catch (\Exception $e) {
                // ignore and continue to next resolution attempt
            }

            // 2) Try resolving numeric -> attribute option id -> label
            try {
                if ($product && method_exists($product, 'getAttributeText')) {
                    $attrText = $product->getAttributeText('gst_rate');
                    if ($attrText) {
                        if (is_array($attrText)) {
                            $attrText = implode(', ', $attrText);
                        }
                        if (preg_match('/^(\d+(?:\.\d+)?)/', (string) $attrText, $m)) {
                            return $this->normalizeGstRateNumber($m[1]);
                        }
                    }
                }

                $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
                $attribute = $eavConfig->getAttribute('catalog_product', 'gst_rate');
                if ($attribute && $attribute->getSource()) {
                    $optLabel = $attribute->getSource()->getOptionText($value);
                    if ($optLabel) {
                        if (is_array($optLabel)) {
                            $optLabel = implode(', ', $optLabel);
                        }
                        if (preg_match('/^(\d+(?:\.\d+)?)/', (string) $optLabel, $m2)) {
                            return $this->normalizeGstRateNumber($m2[1]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // ignore and fall back to numeric percentage
            }

            // 3) Treat numeric as a direct percentage if no id resolution succeeded.
            return $this->normalizeGstRateNumber($value);
        }

        // If gst_rate was stored as a display label, extract the leading numeric part.
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:-|$)/', $value, $matches)) {
            return rtrim(rtrim(number_format((float) $matches[1], 4, '.', ''), '0'), '.');
        }

        try {
            // 1) Most products store GST as a display label; try matching by total_rate.
            $rate = $this->findGstRateByField($objectManager, 'total_rate', $value);
            if ($rate && $rate->getId()) {
                return $this->normalizeGstRateNumber($rate->getData('total_rate'));
            }

            // 2) Final fallback for schemas using igst_rate directly.
            $rate = $this->findGstRateByField($objectManager, 'igst_rate', $value);
            if ($rate && $rate->getId()) {
                return $this->normalizeGstRateNumber($rate->getData('igst_rate'));
            }
        } catch (\Exception $e) {
            // Keep export resilient: return original value if lookup fails.
        }

        // If we reach here the value wasn't resolved from Tax\IndianGST rates.
        // It may be an attribute option ID (e.g. 38). Try resolving via attribute option text.
        try {
            // Prefer using provided product instance (faster, avoids EAV load)
            if ($product && method_exists($product, 'getAttributeText')) {
                $attrText = $product->getAttributeText('gst_rate');
                if ($attrText) {
                    if (is_array($attrText)) {
                        $attrText = implode(', ', $attrText);
                    }
                    if (preg_match('/^(\d+(?:\.\d+)?)/', (string) $attrText, $m)) {
                        return $this->normalizeGstRateNumber($m[1]);
                    }
                }
            }

            // Fall back to loading attribute source to map option id -> label
            $eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
            $attribute = $eavConfig->getAttribute('catalog_product', 'gst_rate');
            if ($attribute && $attribute->getSource()) {
                $optLabel = $attribute->getSource()->getOptionText($value);
                if ($optLabel) {
                    if (is_array($optLabel)) {
                        $optLabel = implode(', ', $optLabel);
                    }
                    if (preg_match('/^(\d+(?:\.\d+)?)/', (string) $optLabel, $m2)) {
                        return $this->normalizeGstRateNumber($m2[1]);
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore and fall back to returning original value normalized
        }

        return $this->normalizeGstRateNumber($value);
    }

    protected function normalizeGstRateNumber($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    protected function findGstRateByField($objectManager, $field, $value)
    {
        $collection = $objectManager->create(\Tax\IndianGST\Model\ResourceModel\Rate\Collection::class);
        $collection->addFieldToFilter($field, $value);

        return $collection->getFirstItem();
    }

    protected function buildGstLabel($rateItem)
    {
        $totalRate = $rateItem->getData('total_rate');
        if ($totalRate === null || $totalRate === '') {
            $totalRate = $rateItem->getData('igst_rate') ?: 
                ($rateItem->getData('cgst_rate') + $rateItem->getData('sgst_rate'));
        }
        return (string)(float)$totalRate;
    }
}
