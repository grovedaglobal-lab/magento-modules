<?php
namespace Vendor\Marketplace\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Magento\Framework\Registry;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Vendor\Marketplace\Model\Inventory\VendorSourceManager;

class Edit extends Template
{
    protected $customerSession;
    protected $productFactory;
    protected $productRepository;
    protected $eavConfig;
    protected $attributeSetCollectionFactory;
    protected $groupCollectionFactory;
    protected $attributeCollectionFactory;
    protected $attributeRepository;
    protected $categoryCollectionFactory;
    protected $registry;
    protected $sourceItemsBySku;
    protected $vendorSourceManager;
    protected $vendorFactory;
    protected $productCollectionFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        Config $eavConfig,
        AttributeSetCollectionFactory $attributeSetCollectionFactory,
        GroupCollectionFactory $groupCollectionFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        Repository $attributeRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        Registry $registry,
        GetSourceItemsBySkuInterface $sourceItemsBySku,
        VendorSourceManager $vendorSourceManager,
        \Vendor\Marketplace\Model\VendorFactory $vendorFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->eavConfig = $eavConfig;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->attributeRepository = $attributeRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->registry = $registry;
        $this->sourceItemsBySku = $sourceItemsBySku;
        $this->vendorSourceManager = $vendorSourceManager;
        $this->vendorFactory = $vendorFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get EAV attribute by code
     * 
     * @param string $entityType
     * @param string $attributeCode
     * @return \Magento\Eav\Model\Entity\Attribute\AbstractAttribute
     */
    public function getAttribute($entityType, $attributeCode)
    {
        return $this->eavConfig->getAttribute($entityType, $attributeCode);
    }

    public function getVendorProductQty($sku)
    {
        if (!$sku)
            return 0;

        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId)
            return 0;

        // Get Vendor by Customer ID
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        if (!$vendor->getId()) {
            return 0;
        }

        $sourceCode = $this->vendorSourceManager->getSourceCode($vendor->getId());

        try {
            $sourceItems = $this->sourceItemsBySku->execute($sku);
            foreach ($sourceItems as $item) {
                if ($item->getSourceCode() === $sourceCode) {
                    return $item->getQuantity();
                }
            }
        } catch (\Exception $e) {
            // Product might not exist or other error
            return 0;
        }

        return 0;
    }

    public function getProduct()
    {
        if ($id = $this->getRequest()->getParam('id')) {
            $product = $this->productRepository->getById($id);
            // Explicitly set category_ids from the product's relationships
            $categoryIds = $product->getCategoryIds();
            
            // If product is simple with no categories, try to get parent's categories
            if (empty($categoryIds) && $product->getTypeId() === 'simple') {
                try {
                    $configurableResource = \Magento\Framework\App\ObjectManager::getInstance()->get(
                        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::class
                    );
                    $parentIds = $configurableResource->getParentIdsByChild((int) $product->getId());
                    if (!empty($parentIds)) {
                        $parentId = (int) reset($parentIds);
                        $parent = $this->productRepository->getById($parentId);
                        $categoryIds = $parent->getCategoryIds();
                    }
                } catch (\Exception $e) {
                    // Continue with empty categories if parent lookup fails
                }
            }
            
            $product->setData('category_ids', $categoryIds);
            
                // For child products, also load parent's gst_rate and hsn_code
                if (empty($product->getData('gst_rate')) || empty($product->getData('hsn_code'))) {
                    if ($product->getTypeId() === 'simple') {
                        try {
                            $configurableResource = \Magento\Framework\App\ObjectManager::getInstance()->get(
                                \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::class
                            );
                            $parentIds = $configurableResource->getParentIdsByChild((int) $product->getId());
                            if (!empty($parentIds)) {
                                $parentId = (int) reset($parentIds);
                                $parent = $this->productRepository->getById($parentId);
                            
                                if (empty($product->getData('gst_rate'))) {
                                    $product->setData('gst_rate', $parent->getData('gst_rate'));
                                }
                                if (empty($product->getData('hsn_code'))) {
                                    $product->setData('hsn_code', $parent->getData('hsn_code'));
                                }
                            }
                        } catch (\Exception $e) {
                            // Continue if parent lookup fails
                        }
                    }
                }
            return $product;
        }

        $product = $this->productFactory->create();

        $type = $this->getRequest()->getParam('type');
        $set = $this->getRequest()->getParam('set');

        $product->setTypeId($type ?: 'simple');
        $product->setAttributeSetId($set ?: $this->getDefaultAttributeSetId());

        return $product;
    }

    public function getProductTypes()
    {
        $allTypes = [
            'simple' => __('Simple Product'),
            'configurable' => __('Configurable Product'),
            'virtual' => __('Virtual Product'),
            'grouped' => __('Grouped Product'),
            'bundle' => __('Bundle Product'),
            'downloadable' => __('Downloadable Product')
        ];

        $allowedTypes = $this->_scopeConfig->getValue(
            'vendor_marketplace/general/allowed_product_types',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($allowedTypes) {
            $allowedTypesArray = explode(',', (string) $allowedTypes);
            if (!empty($allowedTypesArray)) {
                $filteredTypes = [];
                foreach ($allowedTypesArray as $type) {
                    if (isset($allTypes[$type])) {
                        $filteredTypes[$type] = $allTypes[$type];
                    }
                }
                return !empty($filteredTypes) ? $filteredTypes : $allTypes;
            }
        }

        return $allTypes;
    }

    /**
     * Get attributes that can be used for configurable products
     * Must be global, selectable, assigned to selected attribute set,
     * and optionally whitelisted by admin configuration.
     */
    public function getConfigurableAttributes()
    {
        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('is_global', ScopedAttributeInterface::SCOPE_GLOBAL);
        $collection->addFieldToFilter('frontend_input', ['in' => ['select', 'boolean']]); // Swatches resolve to select

        $allowedVariationAttributes = $this->_scopeConfig->getValue(
            'vendor_marketplace/general/allowed_variation_attributes',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($allowedVariationAttributes) {
            $allowedVariationAttributeIds = array_filter(array_map('trim', explode(',', (string) $allowedVariationAttributes)));
            if (!empty($allowedVariationAttributeIds)) {
                $collection->addFieldToFilter('main_table.attribute_id', ['in' => $allowedVariationAttributeIds]);
            }
        }

        // Filter by current attribute set
        $product = $this->getProduct();
        if ($product->getAttributeSetId()) {
            $collection->setAttributeSetFilter($product->getAttributeSetId());
        }
        $collection->setOrder('frontend_label', 'ASC');

        $attributes = [];
        foreach ($collection as $attribute) {
            if ($attribute->getFrontendLabel()) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }

    public function getGstRateOptions()
    {
        $options = [
            '' => __('Select GST Rate'),
        ];
        try {
            $attribute = $this->getAttribute('catalog_product', 'gst_rate');
            if (!$attribute || !$attribute->getId() || !$attribute->usesSource()) {
                return $options;
            }

            foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                $optionValue = isset($option['value']) ? (string) $option['value'] : '';
                $optionLabel = isset($option['label']) ? (string) $option['label'] : '';

                if ($optionValue === '' || $optionLabel === '') {
                    continue;
                }

                $options[$this->formatGstRateValue($optionValue)] = $optionLabel;
            }
        } catch (\Exception $e) {
            // Fall back to the empty placeholder if GST options cannot be loaded.
        }

        return $options;
    }

    /**
     * Get Simple Products owned by the current vendor
     * For linking to configurable products
     */
    public function getVendorSimpleProducts()
    {
        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId) {
            return [];
        }

        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['entity_id', 'sku', 'name', 'price', 'status']);
        $collection->addAttributeToFilter('vendor_id', $vendor->getId());
        $collection->addAttributeToFilter('type_id', 'simple');
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->setOrder('created_at', 'DESC');

        $products = [];
        foreach ($collection as $product) {
            $products[] = [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'status' => $product->getStatus()
            ];
        }

        return $products;
    }

    /**
     * Check if product is Configurable type
     */
    public function isConfigurableProduct()
    {
        return $this->getProduct()->getTypeId() === 'configurable';
    }


    public function getAttributeSets()
    {
        $entityTypeId = $this->eavConfig->getEntityType('catalog_product')->getId();
        $collection = $this->attributeSetCollectionFactory->create();
        $collection->setEntityTypeFilter($entityTypeId);

        $allowedSets = $this->_scopeConfig->getValue(
            'vendor_marketplace/general/allowed_attribute_sets',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($allowedSets) {
            $allowedSetsArray = explode(',', (string) $allowedSets);
            if (!empty($allowedSetsArray)) {
                $collection->addFieldToFilter('attribute_set_id', ['in' => $allowedSetsArray]);
            }
        }

        return $collection;
    }

    public function getDefaultAttributeSetId()
    {
        return $this->eavConfig->getEntityType('catalog_product')->getDefaultAttributeSetId();
    }

    /**
     * Get attributes grouped by attribute group
     */
    public function getGroupedAttributes()
    {
        $product = $this->getProduct();
        $attributeSetId = $product->getAttributeSetId();

        // 1. Get all groups for this set
        $groups = $this->groupCollectionFactory->create()
            ->setAttributeSetFilter($attributeSetId)
            ->setSortOrder()
            ->load();

        $result = [];

        foreach ($groups as $group) {
            $item = [
                'name' => $group->getAttributeGroupName(),
                'id' => $group->getId(),
                'attributes' => []
            ];

            // Use the injected AttributeCollectionFactory
            $collection = $this->attributeCollectionFactory->create();
            $collection->setAttributeGroupFilter($group->getId());
            $collection->addVisibleFilter();
            $collection->setOrder('sort_order', 'ASC');

            // We need to ensure text type attributes are loaded if needed, but standard collection is fine
            // Important: We need to filter by Entity Type to avoid glitches if ID reused (unlikely but safe)
            $entityTypeId = $this->eavConfig->getEntityType('catalog_product')->getId();
            $collection->setEntityTypeFilter($entityTypeId);

            foreach ($collection as $attribute) {
                if ($this->isAttributeVisible($attribute)) {
                    $item['attributes'][] = $attribute;
                }
            }

            // Manually inject category_ids into the first group (usually General/Product Details)
            // This is required because category_ids is a system attribute often not assigned to EAV groups
            if (empty($result)) {
                try {
                    $catAttr = $this->attributeRepository->get('catalog_product', 'category_ids');
                    // Ensure it's not already there (unlikely)
                    $alreadyExists = false;
                    foreach ($item['attributes'] as $attr) {
                        if ($attr->getAttributeCode() === 'category_ids') {
                            $alreadyExists = true;
                            break;
                        }
                    }
                    if (!$alreadyExists) {
                        $item['attributes'][] = $catAttr;
                    }
                } catch (\Exception $e) {
                    // Attribute might not exist
                }
            }

            // Skip the default "Images" group since we have a custom "Images And Videos" section
            $groupName = strtolower($item['name']);
            if ($groupName === 'images') {
                continue;
            }

            if (!empty($item['attributes']) || strpos($groupName, 'video') !== false) {
                $result[] = $item;
            }
        }

        return $result;
    }

    public function isAttributeVisible($attribute)
    {
        $code = $attribute->getAttributeCode();
        $hiddenAttributes = [
            'entity_id',
            'attribute_set_id',
            'type_id',
            'created_at',
            'updated_at',
            'vendor_id',
            'custom_design',
            'custom_design_from',
            'custom_design_to',
            'custom_layout',
            'page_layout',
            'status', // Hide status as it is controlled by admin config
            'options_container',
            'custom_layout_update',
            'custom_layout_update_file',
            'layout_update_xml',
            'custom_layout_update_select',
            'url_key',
            'url_path',
            'msrp',
            'msrp_display_actual_price_type',
            'gift_message_available',
            // 'quantity_and_stock_status', // Unhidden for vendor qty
            'media_gallery',
            'gallery',
            'image',
            'small_image',
            'thumbnail',
            'swatch_image',
            'tier_price',
            'cost',
            'required_options',
            'has_options',
            'image_label',
            'small_image_label',
            'thumbnail_label',
            'special_from_date',
            'special_to_date',
            'custom_design_from',
            'custom_design_to',
            'special_price', // Hiding to show in Advanced Pricing
            'price_type', // Dynamic Price
            'weight_type', // Dynamic Weight
            'sku_type', // Dynamic SKU
            'shipment_type',
            'price_view', // Hide to prevent duplicate Advanced Pricing group
        ];

        $productType = $this->getProduct()->getTypeId();
        if ($productType !== 'configurable') {
            $hiddenAttributes[] = 'variant_weight';
            $hiddenAttributes[] = 'package_size';
        }

        // Explicitly allow category_ids as it's not a standard attribute
        if ($code === 'category_ids') {
            return true;
        }

        if (in_array($code, $hiddenAttributes)) {
            return false;
        }

        if (!$attribute->getIsVisible()) {
            return false;
        }

        $applyTo = $attribute->getApplyTo();
        if (!empty($applyTo)) {
            $productType = $this->getProduct()->getTypeId();
            $applyToArray = is_array($applyTo) ? $applyTo : explode(',', $applyTo);
            if (!empty($applyToArray) && !in_array($productType, $applyToArray)) {
                return false;
            }
        }

        return true;
    }

    public function renderAttributeInput($attribute)
    {
        if (!$this->isAttributeVisible($attribute)) {
            return '';
        }
        $product = $this->getProduct();

        // We need to fetch the value for this attribute from the product
        // Since attributes in getGroupedAttributes are definitions, they don't have the value yet
        $code = $attribute->getAttributeCode();
        $label = __($attribute->getStoreLabel());
        $value = $product->getData($code);

        if ($code === 'gst_rate') {
            $value = $this->normalizeGstRateValue($value);
        }

        // Parent configurable products should not expose base price/qty fields.
        // Price and stock are managed at variation level.
        if ($product->getTypeId() === 'configurable' && in_array($code, ['price', 'quantity_and_stock_status', 'qty'], true)) {
            return '';
        }

        // Check if this is a child product
        $isChildProduct = false;
        if (in_array($code, ['category_ids', 'gst_rate', 'hsn_code'], true) && $product->getTypeId() === 'simple' && $product->getId()) {
            try {
                $configurableResource = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::class
                );
                $parentIds = $configurableResource->getParentIdsByChild((int) $product->getId());
                if (!empty($parentIds)) {
                    $isChildProduct = true;
                }
            } catch (\Exception $e) {
                // Continue if parent lookup fails
            }
        }

        if ($code === 'category_ids') {
            $value = $product->getCategoryIds() ?: $product->getData('category_ids');
        }

        $scopeNote = '';
        if ($attribute->getIsGlobal() == 1) {
            $scopeNote = '<div class="attribute-scope">[global]</div>';
        } elseif ($attribute->getIsGlobal() == 2) {
            $scopeNote = '<div class="attribute-scope">[website]</div>';
        } else {
            $scopeNote = '<div class="attribute-scope">[store view]</div>';
        }

        // Special handling for News Dates (render as a pair)
        if ($code === 'news_from_date') {
            $newsTo = $product->getData('news_to_date');

            $html = '<div class="field ' . $code . ' ' . ($attribute->getIsRequired() ? 'required' : '') . '">';
            $html .= '<label class="label"><span>' . __('Set Product as New From') . '</span>' . $scopeNote . '</label>';
            $html .= '<div class="control" style="display:flex; gap:10px; align-items:center;">';
            $html .= '<input type="date" name="product[news_from_date]" value="' . substr((string) $value, 0, 10) . '" class="input-text" style="flex:1;" />';
            $html .= '<span>' . __('To') . '</span>';
            $html .= '<input type="date" name="product[news_to_date]" value="' . substr((string) $newsTo, 0, 10) . '" class="input-text" style="flex:1;" />';
            $html .= '</div></div>';
            return $html;
        }

        // Special handling for Quantity (MSI Source)
        if ($code === 'quantity_and_stock_status') {
            $currentQty = $this->getVendorProductQty($product->getSku());

            $html = '<div class="field quantity required">';
            $html .= '<label class="label" for="qty"><span>' . __('Quantity') . '</span></label>';
            $html .= '<div class="control">';
            $html .= '<input type="number" name="product[quantity_and_stock_status][qty]" id="qty" value="' . (float) $currentQty . '" class="input-text required-entry validate-number validate-zero-or-greater" />';
            $html .= '</div>';

            // Optional: Add Stock Status Toggle if needed, for now just Qty
            $html .= '<div class="field choice admin__field admin__field-option" style="margin-top:10px;">';
            $html .= '<input type="checkbox" id="is_in_stock" name="product[quantity_and_stock_status][is_in_stock]" value="1" class="admin__control-checkbox" ' . ($currentQty > 0 ? 'checked' : '') . '>';
            $html .= '<label class="admin__field-label" for="is_in_stock"><span>' . __('In Stock') . '</span></label>';
            $html .= '</div>';

            $html .= '</div>';
            return $html;
        }



        $inputType = $attribute->getFrontendInput();
        if ($code === 'category_ids') {
            $inputType = 'multiselect';
        }

        // Define validation attributes early for use in special cases
        $requiredAttr = $attribute->getIsRequired() ? 'data-validate="{required:true}"' : '';

        // Force Visibility to 'Catalog, Search' (Value 4) and prevent changes
        if ($code === 'visibility') {
            $html = '<div class="field ' . $code . '">';
            $html .= '<label class="label"><span>' . $label . '</span>' . $scopeNote . '</label>';
            $html .= '<div class="control">';
            // Value 4 = Catalog, Search
            $html .= '<input type="hidden" name="product[visibility]" value="4" />';
            $html .= '<span class="input-text" style="background:#eee; border:1px solid #ddd; padding:0 10px; line-height:32px; display:inline-block; width:100%; color:#555;">' . __('Catalog, Search') . '</span>';
            $html .= '</div></div>';
            return $html;
        }

        if ($code === 'gst_rate') {
            $html = '<div class="field ' . $code . ' ' . ($attribute->getIsRequired() ? 'required' : '') . '">';
            $html .= '<label class="label" for="' . $code . '"><span>' . $label . '</span>' . $scopeNote . '</label>';
            if ($isChildProduct) {
                $html .= '<div class="note" style="margin-bottom: 10px; padding: 8px; background: #fffbea; border-left: 3px solid #ffa500; color: #666; font-size: 12px;">';
                $html .= __('(Inherited from parent product - read-only)');
                $html .= '</div>';
            }
            $html .= '<div class="control">';
            $disabledAttr = $isChildProduct ? 'disabled' : '';
            $html .= '<select name="product[' . $code . ']" id="' . $code . '" class="select" ' . $requiredAttr . ' ' . $disabledAttr . ' style="' . ($isChildProduct ? 'background: #f9f9f9; opacity: 0.7;' : '') . '">';
            foreach ($this->getGstRateOptions() as $optionValue => $optionLabel) {
                $selected = ((string) $value === (string) $optionValue) ? 'selected' : '';
                $html .= '<option value="' . $this->escapeHtml($optionValue) . '" ' . $selected . '>' . $this->escapeHtml($optionLabel) . '</option>';
            }
            $html .= '</select>';
            $html .= '</div></div>';
            return $html;
        }

        if ($code === 'hsn_code') {
            $html = '<div class="field ' . $code . ' ' . ($attribute->getIsRequired() ? 'required' : '') . '">';
            $html .= '<label class="label" for="' . $code . '"><span>' . $label . '</span>' . $scopeNote . '</label>';
            if ($isChildProduct) {
                $html .= '<div class="note" style="margin-bottom: 10px; padding: 8px; background: #fffbea; border-left: 3px solid #ffa500; color: #666; font-size: 12px;">';
                $html .= __('(Inherited from parent product - read-only)');
                $html .= '</div>';
            }
            $html .= '<div class="control">';
            $disabledAttr = $isChildProduct ? 'readonly' : '';
            $html .= '<input type="text" name="product[' . $code . ']" id="' . $code . '" value="' . $this->escapeHtml($value) . '" class="input-text" ' . $requiredAttr . ' ' . $disabledAttr . ' style="' . ($isChildProduct ? 'background: #f9f9f9; opacity: 0.7;' : '') . '" />';
            $html .= '</div></div>';
            return $html;
        }

        $validationClasses = [];
        if ($attribute->getIsRequired()) {
            $validationClasses[] = 'required-entry';
        }

        // Add specific validation based on type or code
        if ($inputType === 'price' || $inputType === 'weight' || $code === 'price' || $code === 'weight') {
            $validationClasses[] = 'validate-number';
            $validationClasses[] = 'validate-zero-or-greater';
        }

        if ($code === 'sku') {
            $validationClasses[] = 'validate-code';
        }

        $allClasses = implode(' ', $validationClasses);

        $html = '<div class="field ' . $code . ' ' . ($attribute->getIsRequired() ? 'required' : '') . '">';
        $html .= '<label class="label" for="' . $code . '"><span>' . $label . '</span>' . $scopeNote . '</label>';
        $html .= '<div class="control">';

        switch ($inputType) {
            case 'text':
            case 'price':
            case 'weight':
                $suffix = '';
                if ($code === 'weight') {
                    $unit = $this->_scopeConfig->getValue('general/locale/weight_unit', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) ?: 'lbs';
                    $suffix = '<span class="input-suffix">' . $unit . '</span>';

                    $html .= '<div class="weight-wrapper" style="display:flex; gap:10px; align-items:center;">';
                    $html .= '<div class="input-with-suffix" style="position:relative; flex:1;">';
                    $html .= '<input type="text" name="product[' . $code . ']" id="' . $code . '" value="' . $this->escapeHtml($value) . '" class="input-text ' . $allClasses . '" ' . $requiredAttr . ' style="padding-right:40px;" />';
                    $html .= $suffix;
                    $html .= '</div>';

                    // Added weight toggle dropdown to match admin screenshot
                    $html .= '<div class="weight-toggle" style="flex:1;">';
                    $html .= '<select class="select" id="weight_type">';
                    $html .= '<option value="1">' . __('This item has weight') . '</option>';
                    $html .= '<option value="0">' . __('This item has no weight') . '</option>';
                    $html .= ' </select>';
                    $html .= '</div>';
                    $html .= '</div>';
                } elseif ($code === 'price') {
                    $displayPrice = $this->formatDecimalForInput($value);
                    $html .= '<input type="text" name="product[' . $code . ']" id="' . $code . '" value="' . $this->escapeHtml($displayPrice) . '" class="input-text ' . $allClasses . '" ' . $requiredAttr . ' />';

                    // Advanced Pricing Link and Modal Trigger
                    $html .= '<div class="advanced-pricing-link" style="margin-top: 10px;">';
                    $html .= '<a href="#" id="advanced_pricing_button" style="text-decoration: none; color: #007bdb; font-weight: 600;">' . __('Advanced Pricing') . '</a>';
                    $html .= '</div>';

                    // Fetch values for hidden attributes
                    $specialPrice = $this->formatDecimalForInput($product->getData('special_price'));
                    $specialFrom = $product->getData('special_from_date');
                    $specialTo = $product->getData('special_to_date');

                    // Modal Content Wrapper
                    $html .= '<div id="advanced-pricing-modal" class="advanced-pricing-modal-overlay" style="display:none;">';

                    // Internal CSS for this modal to ensure layout correctness regardless of theme
                    $html .= '<style>
                        .advanced-pricing-modal-overlay {
                            position: fixed;
                            inset: 0;
                            background: rgba(0, 0, 0, 0.45);
                            z-index: 9999;
                            display: none;
                            align-items: center;
                            justify-content: center;
                            padding: 20px;
                            box-sizing: border-box;
                        }
                        .advanced-pricing-panel {
                            width: min(900px, 96vw);
                            max-height: 90vh;
                            overflow: auto;
                            background: #fff;
                            border-radius: 8px;
                            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
                            padding: 20px 24px;
                        }
                        .advanced-pricing-header {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            margin-bottom: 12px;
                        }
                        .advanced-pricing-title {
                            font-size: 26px;
                            font-weight: 700;
                            color: #1f2937;
                        }
                        .advanced-pricing-close {
                            border: 0;
                            background: transparent;
                            font-size: 28px;
                            line-height: 1;
                            cursor: pointer;
                            color: #374151;
                        }
                        .advanced-pricing-content { padding-top: 8px; }
                        .advanced-pricing-content .admin__field {
                            display: flex;
                            align-items: center;
                            margin-bottom: 20px;
                        }
                        .advanced-pricing-content .admin__field-label {
                            width: 30%;
                            text-align: right;
                            padding-right: 20px;
                            font-weight: 600;
                            color: #333;
                            flex-shrink: 0;
                        }
                        .advanced-pricing-content .admin__field-control {
                            width: 70%;
                            flex-grow: 1;
                        }
                        .advanced-pricing-content .input-text, 
                        .advanced-pricing-content .admin__control-text {
                            width: 100%;
                            height: 32px;
                        }
                        .advanced-pricing-actions {
                            margin-top: 18px;
                            display: flex;
                            justify-content: flex-end;
                        }
                     </style>';

                    $html .= '<div class="advanced-pricing-panel">';
                    $html .= '<div class="advanced-pricing-header">';
                    $html .= '<div class="advanced-pricing-title">' . __('Advanced Pricing') . '</div>';
                    $html .= '<button type="button" class="advanced-pricing-close" id="advanced_pricing_close" aria-label="' . __('Close') . '">&times;</button>';
                    $html .= '</div>';
                    $html .= '<div class="advanced-pricing-content">';

                    // Special Price
                    $html .= '<div class="admin__field">';
                    $html .= '<label class="admin__field-label"><span>' . __('Special Price') . '</span></label>';
                    $html .= '<div class="admin__field-control">';
                    $html .= '<input type="text" name="product[special_price]" value="' . $this->escapeHtml($specialPrice) . '" class="input-text admin__control-text" />';
                    $html .= '</div></div>';

                    // Special Price Dates
                    $html .= '<div class="admin__field">';
                    $html .= '<label class="admin__field-label"><span>' . __('Special Price From') . '</span></label>';
                    $html .= '<div class="admin__field-control" style="display: flex; gap: 10px; align-items: center;">';
                    $html .= '<input type="date" name="product[special_from_date]" value="' . substr((string) $specialFrom, 0, 10) . '" class="input-text" style="flex:1;" />';
                    $html .= '<span style="color:#666;">' . __('To') . '</span>';
                    $html .= '<input type="date" name="product[special_to_date]" value="' . substr((string) $specialTo, 0, 10) . '" class="input-text" style="flex:1;" />';
                    $html .= '</div></div>';

                    $html .= '<div class="advanced-pricing-actions">';
                    $html .= '<button type="button" class="action primary" id="advanced_pricing_done"><span>' . __('Done') . '</span></button>';
                    $html .= '</div>';
                    $html .= '</div>'; // End Content
                    $html .= '</div>'; // End Panel
                    $html .= '</div>'; // End Modal Overlay

                    // Script to Init Modal
                    $html .= '<script>
                        require(["jquery"], function($) {
                            var modal = $("#advanced-pricing-modal");

                            function closeAdvancedPricing() {
                                modal.fadeOut(120);
                                $("body").css("overflow", "");
                            }

                            $("#advanced_pricing_button").off("click").on("click", function(e) {
                                e.preventDefault();
                                modal.css("display", "flex").hide().fadeIn(120);
                                $("body").css("overflow", "hidden");
                            });

                            $("#advanced_pricing_close, #advanced_pricing_done").off("click").on("click", function() {
                                closeAdvancedPricing();
                            });

                            modal.off("click").on("click", function(e) {
                                if ($(e.target).is("#advanced-pricing-modal")) {
                                    closeAdvancedPricing();
                                }
                            });

                            $(document).off("keydown.advancedPricing").on("keydown.advancedPricing", function(e) {
                                if (e.key === "Escape" && modal.is(":visible")) {
                                    closeAdvancedPricing();
                                }
                            });
                        });
                     </script>';

                } else {
                    $textValue = is_array($value) ? implode(',', $value) : $value;
                    $html .= '<input type="text" name="product[' . $code . ']" id="' . $code . '" value="' . $this->escapeHtml($textValue) . '" class="input-text ' . $allClasses . '" ' . $requiredAttr . ' />';
                }
                break;
            case 'textarea':
                $wysiwyg = $attribute->getIsWysiwygEnabled() ? 'wysiwyg-editor' : '';
                $html .= '<textarea name="product[' . $code . ']" id="' . $code . '" class="textarea ' . $allClasses . ' ' . $wysiwyg . '" ' . $requiredAttr . '>' . $this->escapeHtml($value) . '</textarea>';
                break;
            case 'select':
            case 'boolean':
                $html .= '<select name="product[' . $code . ']" id="' . $code . '" class="select ' . $allClasses . '" ' . $requiredAttr . '>';
                $html .= '<option value=""></option>';
                if ($attribute->usesSource()) {
                    foreach ($attribute->getSource()->getAllOptions() as $option) {
                        $optValue = isset($option['value']) ? $option['value'] : '';
                        $optLabel = isset($option['label']) ? $option['label'] : '';

                        $selected = ($value == $optValue && $value !== null) ? 'selected' : '';
                        if ($optValue === '' && empty($optLabel))
                            continue;

                        $html .= '<option value="' . $optValue . '" ' . $selected . '>' . $optLabel . '</option>';
                    }
                }
                $html .= '</select>';
                break;
            case 'multiselect':
                if ($code === 'category_ids') {
                    if ($isChildProduct) {
                        $html .= '<div class="category-list-container" style="border: 1px solid #e8e8e8; padding: 10px; max-height: 300px; overflow-y: auto; background: #f9f9f9; border-radius: 4px; opacity: 0.7;">';
                        $html .= '<div class="note" style="margin-bottom: 10px; padding: 8px; background: #fffbea; border-left: 3px solid #ffa500; color: #666; font-size: 12px;">';
                        $html .= __('(Inherited from parent product - read-only)');
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="category-list-container" style="border: 1px solid #ccc; padding: 10px; max-height: 300px; overflow-y: auto; background: #fff; border-radius: 4px;">';
                    }
                    
                    // Ensure value is an array, and cast all to integers for safe comparison
                    $values = is_array($value) ? $value : explode(',', (string) $value);
                    $values = array_map(static function($v) { return (int) $v; }, array_filter($values));

                    foreach ($this->getCategories() as $category) {
                        $isChecked = in_array((int) $category['value'], $values, true) ? 'checked' : '';
                        // Use level for indentation
                        $indentLevel = max(0, $category['level'] - 2) * 20;

                        $html .= '<div class="category-item" style="margin-bottom: 5px; margin-left: ' . $indentLevel . 'px;">';
                        $html .= '<label style="cursor: ' . ($isChildProduct ? 'not-allowed' : 'pointer') . '; display: flex; align-items: center; font-weight: normal;">';
                        // Use checkbox and [] in name to allow multiple selections
                        $disabledAttr = $isChildProduct ? 'disabled' : '';
                        $html .= '<input type="checkbox" name="product[' . $code . '][]" value="' . $category['value'] . '" ' . $isChecked . ' ' . $disabledAttr . ' style="margin-right: 8px; margin-top:0; cursor: ' . ($isChildProduct ? 'not-allowed' : 'pointer') . ';">';
                        $html .= '<span>' . $this->escapeHtml($category['display_label']) . '</span>';
                        $html .= '</label>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                } else {
                    $html .= '<select name="product[' . $code . '][]" id="' . $code . '" class="multiselect ' . $allClasses . '" multiple="multiple" ' . $requiredAttr . '>';
                    $values = is_array($value) ? $value : explode(',', (string) $value);
                    if ($attribute->usesSource()) {
                        foreach ($attribute->getSource()->getAllOptions() as $option) {
                            $optValue = isset($option['value']) ? $option['value'] : '';
                            $optLabel = isset($option['label']) ? $option['label'] : '';
                            $selected = in_array($optValue, $values) ? 'selected' : '';
                            if ($optValue === '' && empty($optLabel))
                                continue;
                            $html .= '<option value="' . $optValue . '" ' . $selected . '>' . $optLabel . '</option>';
                        }
                    }
                    $html .= '</select>';
                }
                break;
            case 'date':
                $html .= '<input type="date" name="product[' . $code . ']" id="' . $code . '" value="' . $value . '" class="input-text ' . $allClasses . '" ' . $requiredAttr . ' />';
                break;
            default:
                $html .= '<input type="text" name="product[' . $code . ']" id="' . $code . '" value="' . $this->escapeHtml($value) . '" class="input-text ' . $allClasses . '" ' . $requiredAttr . ' />';
                break;
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Normalize a GST rate value to the rate_id used by the dropdown.
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
            foreach (['rate_id', 'total_rate', 'igst_rate'] as $field) {
                $matchCollection = $this->gstRateCollectionFactory->create();
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

                    return (string) $value;
                }
            }
        } catch (\Exception $e) {
            // Fall through and use the original value.
        }

        return $value;
    }

    /**
     * Format a GST rate value for stable comparison and select keys.
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
    /**
     * Format decimal values for text inputs by removing unnecessary trailing zeros.
     *
     * @param mixed $value
     * @return string
     */
    protected function formatDecimalForInput($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        $formatted = number_format((float) $value, 6, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    public function getCategories()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToSelect('level');
        $collection->addAttributeToFilter('is_active', 1);
        $collection->setOrder('path', 'ASC');

        $categories = [];
        foreach ($collection as $category) {
            // Level 0 is Root Catalog, Level 1 is Store Root, Level 2 is Default Category
            if ($category->getLevel() < 2)
                continue;

            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(0, $category->getLevel() - 2));
            $prefix = ($category->getLevel() > 2) ? '— ' : '';
            $categories[] = [
                'value' => $category->getId(),
                'label' => $category->getName(),
                'display_label' => $indent . $prefix . $category->getName(),
                'level' => $category->getLevel()
            ];
        }
        return $categories;
    }

    public function getSaveUrl()
    {
        return $this->getUrl('marketplace/product/save');
    }
}
