<?php
/**
 * Data Patch: Add Package Size Attribute
 * 
 * Creates the package_size attribute for configurable products.
 * This attribute allows vendors to create variations based on package sizes.
 * 
 * Magento 2.3+ uses Data Patches instead of InstallData/UpgradeData scripts.
 */
namespace Vendor\Marketplace\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class AddPackageSizeAttribute implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Check if attribute already exists
        if ($eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, 'package_size')) {
            // Attribute exists, just add more options if needed
            $this->addAttributeOptions($eavSetup);
            return $this;
        }

        // Create the package_size attribute
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'package_size',
            [
                'type' => 'int',
                'label' => 'Package Size',
                'input' => 'select',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
                'required' => false,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'used_in_product_listing' => true,
                'visible_on_front' => true,
                'user_defined' => true,
                'searchable' => true,
                'filterable' => true,
                'filterable_in_search' => true,
                'comparable' => true,
                'visible' => true,
                'is_html_allowed_on_front' => false,
                'used_for_promo_rules' => true,
                'apply_to' => 'simple,configurable', // Can be used for both simple and configurable
                'group' => 'General',
                'option' => [
                    'values' => $this->getDefaultPackageSizes()
                ]
            ]
        );

        return $this;
    }

    /**
     * Add additional attribute options to existing attribute
     *
     * @param EavSetup $eavSetup
     * @return void
     */
    private function addAttributeOptions($eavSetup)
    {
        $attributeId = $eavSetup->getAttributeId(
            \Magento\Catalog\Model\Product::ENTITY,
            'package_size'
        );

        if (!$attributeId) {
            return;
        }

        // Get existing options
        $connection = $this->moduleDataSetup->getConnection();
        $optionTable = $this->moduleDataSetup->getTable('eav_attribute_option');
        $optionValueTable = $this->moduleDataSetup->getTable('eav_attribute_option_value');

        $select = $connection->select()
            ->from(['o' => $optionTable])
            ->join(
                ['ov' => $optionValueTable],
                'o.option_id = ov.option_id',
                ['value']
            )
            ->where('o.attribute_id = ?', $attributeId);

        $existingOptions = $connection->fetchCol($select);

        // Add new options that don't exist
        $newOptions = [];
        foreach ($this->getDefaultPackageSizes() as $size) {
            if (!in_array($size, $existingOptions)) {
                $newOptions[] = $size;
            }
        }

        if (!empty($newOptions)) {
            $eavSetup->addAttributeOption([
                'attribute_id' => $attributeId,
                'values' => $newOptions
            ]);
        }
    }

    /**
     * Get default package size options
     * These are common package sizes that vendors can use
     *
     * @return array
     */
    private function getDefaultPackageSizes()
    {
        return [
            // Weight-based (grams)
            '100g',
            '250g',
            '500g',
            '750g',
            
            // Weight-based (kilograms)
            '1kg',
            '2kg',
            '5kg',
            '10kg',
            '25kg',
            '50kg',
            
            // Volume-based (milliliters)
            '100ml',
            '250ml',
            '500ml',
            '750ml',
            '1L',
            '2L',
            '5L',
            
            // Count-based
            'Pack of 6',
            'Pack of 12',
            'Pack of 24',
            'Pack of 50',
            'Pack of 100',
            
            // Size-based (apparel/general)
            'Small',
            'Medium',
            'Large',
            'Extra Large',
            'XXL',
            
            // Generic
            'Single Unit',
            'Bulk',
            'Trial Size',
            'Family Pack',
            'Economy Pack'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
