<?php
/**
 * Package Size Attribute Manager
 * 
 * Helps vendors manage package_size attribute values
 * Provides methods to add, retrieve, and validate package size options
 */
namespace Vendor\Marketplace\Model\Product\Attribute;

use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class PackageSizeManager
{
    const ATTRIBUTE_CODE = 'package_size';
    const ENTITY_TYPE = 'catalog_product';

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var AttributeOptionManagementInterface
     */
    private $attributeOptionManagement;

    /**
     * @var AttributeOptionInterfaceFactory
     */
    private $optionFactory;

    /**
     * @var AttributeOptionLabelInterfaceFactory
     */
    private $optionLabelFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param AttributeOptionManagementInterface $attributeOptionManagement
     * @param AttributeOptionInterfaceFactory $optionFactory
     * @param AttributeOptionLabelInterfaceFactory $optionLabelFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionInterfaceFactory $optionFactory,
        AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        LoggerInterface $logger
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->optionFactory = $optionFactory;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->logger = $logger;
    }

    /**
     * Get all package size options
     *
     * @return array
     */
    public function getAllOptions()
    {
        try {
            $attribute = $this->attributeRepository->get(self::ENTITY_TYPE, self::ATTRIBUTE_CODE);
            $options = $attribute->getOptions();
            
            $result = [];
            foreach ($options as $option) {
                if ($option->getValue()) {
                    $result[] = [
                        'value' => $option->getValue(),
                        'label' => $option->getLabel()
                    ];
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error getting package size options: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Add a new package size option
     * 
     * Usage: When vendor needs a custom package size not in the default list
     *
     * @param string $label The package size label (e.g., "3kg", "1.5L")
     * @param int $sortOrder Optional sort order
     * @return int|null The new option ID or null on failure
     * @throws LocalizedException
     */
    public function addOption($label, $sortOrder = 0)
    {
        try {
            // Check if option already exists
            if ($this->optionExists($label)) {
                throw new LocalizedException(__('Package size "%1" already exists.', $label));
            }

            // Create option label
            $optionLabel = $this->optionLabelFactory->create();
            $optionLabel->setStoreId(0);
            $optionLabel->setLabel($label);

            // Create option
            $option = $this->optionFactory->create();
            $option->setLabel($label);
            $option->setStoreLabels([$optionLabel]);
            $option->setSortOrder($sortOrder);
            $option->setIsDefault(false);

            // Add option via API
            $result = $this->attributeOptionManagement->add(
                self::ENTITY_TYPE,
                self::ATTRIBUTE_CODE,
                $option
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error adding package size option: ' . $e->getMessage());
            throw new LocalizedException(__('Could not add package size: %1', $e->getMessage()));
        }
    }

    /**
     * Check if an option with this label already exists
     *
     * @param string $label
     * @return bool
     */
    public function optionExists($label)
    {
        $options = $this->getAllOptions();
        foreach ($options as $option) {
            if (strcasecmp($option['label'], $label) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get option ID by label (case-insensitive)
     *
     * @param string $label
     * @return int|null
     */
    public function getOptionIdByLabel($label)
    {
        $options = $this->getAllOptions();
        foreach ($options as $option) {
            if (strcasecmp($option['label'], $label) === 0) {
                return $option['value'];
            }
        }
        return null;
    }

    /**
     * Get suggested package sizes based on category or product type
     * Helps vendors choose appropriate sizes
     *
     * @param string|null $category
     * @return array
     */
    public function getSuggestedSizes($category = null)
    {
        // Default suggestions
        $suggestions = [
            'food' => ['100g', '250g', '500g', '1kg', '2kg', '5kg'],
            'beverages' => ['250ml', '500ml', '750ml', '1L', '2L', '5L'],
            'cosmetics' => ['50ml', '100ml', '250ml', '500ml'],
            'apparel' => ['Small', 'Medium', 'Large', 'Extra Large', 'XXL'],
            'household' => ['500ml', '1L', '2L', '5L', '10L'],
            'supplements' => ['Pack of 30', 'Pack of 60', 'Pack of 90', 'Pack of 120'],
        ];

        if ($category && isset($suggestions[$category])) {
            return $suggestions[$category];
        }

        // Return common sizes if no category specified
        return ['100g', '250g', '500g', '1kg', '2kg', '5kg', '250ml', '500ml', '1L', 'Small', 'Medium', 'Large'];
    }

    /**
     * Validate package size format
     * Ensures the entered size follows a standard format
     *
     * @param string $size
     * @return bool
     */
    public function validateFormat($size)
    {
        // Allow formats like: 100g, 1kg, 250ml, 1L, Pack of 12, Small, Medium, etc.
        $patterns = [
            '/^\d+(\.\d+)?(g|kg|ml|L)$/',           // Weight/Volume: 100g, 1.5kg, 250ml, 2L
            '/^Pack of \d+$/',                       // Count: Pack of 6
            '/^(Small|Medium|Large|Extra Large|XXL)$/', // Size
            '/^\w+( \w+)*$/'                         // Generic: Trial Size, Family Pack
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $size)) {
                return true;
            }
        }

        return false;
    }
}
