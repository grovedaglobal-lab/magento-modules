<?php
/**
 * Vendor: Custom Package Size Input
 * 
 * Alternative approach: Allow vendors to enter custom text value
 * if their size is not in the predefined list
 * 
 * This creates a custom attribute that stores vendor-specific sizes
 * (not merged into global attribute options)
 */
namespace Vendor\Marketplace\Model\Product\Attribute;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class CustomPackageSizeSetup
{
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Create custom_package_size attribute
     * 
     * This attribute allows vendors to enter custom package sizes
     * that don't exist in the standard package_size dropdown
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     */
    public function createCustomPackageSizeAttribute(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        // Check if attribute already exists
        if ($eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, 'custom_package_size')) {
            return;
        }

        // Create the custom_package_size attribute for vendor-specific sizes
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'custom_package_size',
            [
                'type' => 'varchar',
                'label' => 'Custom Package Size',
                'input' => 'text',
                'required' => false,
                'sort_order' => 101,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'user_defined' => true,
                'apply_to' => 'simple,configurable',
                'group' => 'General',
            ]
        );
    }
}
