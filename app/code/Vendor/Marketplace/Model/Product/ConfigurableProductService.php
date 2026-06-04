<?php
/**
 * Configurable Product Service
 * 
 * Handles configurable product creation and management using Magento 2 standard structure.
 * 
 * Magento 2 Configurable Product Structure:
 * ==========================================
 * 
 * 1. Product Type: Magento\ConfigurableProduct\Model\Product\Type\Configurable
 *    - Manages the configurable product type
 *    - Handles parent-child product relationships
 *    - Sets configurable attributes via setUsedProductAttributeIds()
 * 
 * 2. Product Extension Attributes (Magento\Catalog\Api\Data\ProductExtensionInterface):
 *    - configurable_product_options: Array of OptionInterface objects
 *    - configurable_product_links: Array of child product IDs
 *    - Uses extension attributes to avoid modifying core product entity
 * 
 * 3. Configurable Options (Magento\ConfigurableProduct\Api\Data\OptionInterface):
 *    - Represents each configurable attribute (e.g., size, color)
 *    - Contains attribute_id, label, position, values
 *    - Created via OptionInterfaceFactory
 * 
 * 4. Option Values:
 *    - Array of value_index entries corresponding to attribute option IDs
 *    - Collected from child products' attribute values
 *    - Must exist in the attribute's option list
 * 
 * 5. Child Products (Simple Products):
 *    - Must be simple product type
 *    - Must have values for all configurable attributes
 *    - Visibility typically set to VISIBILITY_NOT_VISIBLE
 *    - Must belong to same attribute set as parent (or compatible)
 * 
 * Implementation Flow:
 * ====================
 * 1. Validate attribute is suitable (global scope, select type)
 * 2. Set configurable attribute IDs on parent product
 * 3. Create configurable option with attribute details
 * 4. Collect option values from child products
 * 5. Set extension attributes with options and child product links
 * 6. Save parent product via ProductRepository
 * 
 * @see https://devdocs.magento.com/guides/v2.4/rest/tutorials/configurable-product/config-product-intro.html
 */
namespace Vendor\Marketplace\Model\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory;
use Magento\Catalog\Api\Data\ProductExtensionInterfaceFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class ConfigurableProductService
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var Configurable
     */
    protected $configurableType;

    /**
     * @var OptionInterfaceFactory
     */
    protected $optionFactory;

    /**
     * @var OptionValueInterfaceFactory
     */
    protected $optionValueFactory;

    /**
     * @var ProductExtensionInterfaceFactory
     */
    protected $productExtensionFactory;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Configurable $configurableType
     * @param OptionInterfaceFactory $optionFactory
     * @param OptionValueInterfaceFactory $optionValueFactory
     * @param ProductExtensionInterfaceFactory $productExtensionFactory
     * @param EavConfig $eavConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Configurable $configurableType,
        OptionInterfaceFactory $optionFactory,
        OptionValueInterfaceFactory $optionValueFactory,
        ProductExtensionInterfaceFactory $productExtensionFactory,
        EavConfig $eavConfig,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
        $this->optionFactory = $optionFactory;
        $this->optionValueFactory = $optionValueFactory;
        $this->productExtensionFactory = $productExtensionFactory;
        $this->eavConfig = $eavConfig;
        $this->logger = $logger;
    }

    /**
     * Link simple products to configurable product
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $configurableProduct
     * @param int|string $attributeId
     * @param array $childProductIds
     * @return bool
     * @throws LocalizedException
     */
    public function linkProductsToConfigurable($configurableProduct, $attributeId, $childProductIds)
    {
        try {
            // Validate attribute
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeId);
            if (!$attribute || !$attribute->getId()) {
                throw new LocalizedException(__('Invalid configurable attribute.'));
            }

            // Validate attribute is suitable for configurable products
            if (!$this->isAttributeSuitableForConfigurable($attribute)) {
                throw new LocalizedException(
                    __('Attribute "%1" is not suitable for configurable products.', $attribute->getAttributeCode())
                );
            }

            // Set configurable attributes
            $this->configurableType->setUsedProductAttributeIds(
                [$attribute->getAttributeId()],
                $configurableProduct
            );

            // Create and set configurable options
            $this->setConfigurableOptions($configurableProduct, $attribute, $childProductIds);

            // Save the product
            $this->productRepository->save($configurableProduct);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error linking products to configurable: ' . $e->getMessage());
            throw new LocalizedException(__('Failed to link products: %1', $e->getMessage()));
        }
    }

    /**
     * Set configurable product options and links
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $configurableProduct
     * @param \Magento\Eav\Model\Entity\Attribute $attribute
     * @param array $childProductIds
     * @return void
     */
    protected function setConfigurableOptions($configurableProduct, $attribute, $childProductIds)
    {
        // Get or create extension attributes
        $extensionAttributes = $configurableProduct->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->productExtensionFactory->create();
        }

        // Create configurable option
        $option = $this->optionFactory->create();
        $option->setAttributeId($attribute->getAttributeId());
        $option->setLabel($attribute->getStoreLabel());
        $option->setPosition(0);
        $option->setIsUseDefault(true);

        // Collect option values from child products
        $optionValues = $this->collectOptionValues($childProductIds, $attribute->getAttributeCode());
        if (!empty($optionValues)) {
            $option->setValues($optionValues);
        }

        // Set extension attributes
        $extensionAttributes->setConfigurableProductOptions([$option]);
        $extensionAttributes->setConfigurableProductLinks($childProductIds);
        
        $configurableProduct->setExtensionAttributes($extensionAttributes);
    }

    /**
     * Collect unique option values from child products
     *
     * @param array $childProductIds
     * @param string $attributeCode
     * @return array
     */
    protected function collectOptionValues($childProductIds, $attributeCode)
    {
        $optionValues = [];
        $seenValues = [];

        foreach ($childProductIds as $childId) {
            try {
                $child = $this->productRepository->getById($childId);
                $optionValue = $child->getData($attributeCode);

                if ($optionValue && !in_array($optionValue, $seenValues)) {
                    // Create proper OptionValue object instead of array
                    $valueObject = $this->optionValueFactory->create();
                    $valueObject->setValueIndex((string) $optionValue);
                    $optionValues[] = $valueObject;
                    $seenValues[] = $optionValue;
                }
            } catch (\Exception $e) {
                $this->logger->warning(
                    sprintf('Could not load product %s: %s', $childId, $e->getMessage())
                );
                continue;
            }
        }

        return $optionValues;
    }

    /**
     * Check if attribute is suitable for configurable products
     *
     * @param \Magento\Eav\Model\Entity\Attribute $attribute
     * @return bool
     */
    protected function isAttributeSuitableForConfigurable($attribute)
    {
        // Attribute must be global scope
        if ($attribute->getIsGlobal() != \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL) {
            return false;
        }

        // Attribute must be select/swatch type
        $allowedInputTypes = ['select', 'boolean'];
        if (!in_array($attribute->getFrontendInput(), $allowedInputTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Update child product visibility
     *
     * @param int $childProductId
     * @return void
     * @throws LocalizedException
     */
    public function updateChildProductVisibility($childProductId)
    {
        try {
            $product = $this->productRepository->getById($childProductId);
            $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
            $this->productRepository->save($product);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Could not update visibility for product %1: %2', $childProductId, $e->getMessage())
            );
        }
    }

    /**
     * Validate that products can be used as configurable children
     *
     * @param array $productIds
     * @param int $vendorId
     * @return array Array of valid product IDs
     */
    public function validateChildProducts($productIds, $vendorId)
    {
        $validProductIds = [];

        foreach ($productIds as $productId) {
            try {
                $product = $this->productRepository->getById($productId);

                // Check ownership
                if ($product->getData('vendor_id') != $vendorId) {
                    $this->logger->warning(
                        sprintf('Product %s does not belong to vendor %s', $productId, $vendorId)
                    );
                    continue;
                }

                // Check product type
                if ($product->getTypeId() !== 'simple') {
                    $this->logger->warning(
                        sprintf('Product %s is not a simple product', $productId)
                    );
                    continue;
                }

                $validProductIds[] = $productId;
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('Error validating product %s: %s', $productId, $e->getMessage())
                );
            }
        }

        return $validProductIds;
    }
}
