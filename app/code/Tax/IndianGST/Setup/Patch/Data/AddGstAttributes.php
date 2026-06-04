<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Tax\IndianGST\Setup\Patch\Data;

use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;

class AddGstAttributes implements DataPatchInterface
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

        // Add HSN Code Attribute
        $eavSetup->addAttribute(
            Product::ENTITY,
            'hsn_code',
            [
                'type' => 'varchar',
                'label' => 'HSN Code',
                'input' => 'text',
                'required' => false,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => 'Tax Details',
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
            ]
        );

        // Add GST Rate Attribute
        $eavSetup->addAttribute(
            Product::ENTITY,
            'gst_rate',
            [
                'type' => 'int',
                'label' => 'GST Rate (%)',
                'input' => 'select',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
                'required' => true,
                'sort_order' => 110,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => 'Tax Details',
                'option' => [
                    'values' => [
                        '0',
                        '3',
                        '5',
                        '12',
                        '18',
                        '28'
                    ],
                ],
                'default' => '18',
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'note' => 'Select the applicable GST slab for this product'
            ]
        );

        // Add GST Calculation Method (On Item Price or Total)
        $eavSetup->addAttribute(
            Product::ENTITY,
            'gst_min_price',
            [
                'type' => 'decimal',
                'label' => 'Minimum GST Price Threshold',
                'input' => 'price',
                'required' => false,
                'sort_order' => 120,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => 'Tax Details',
                'note' => 'If product price is below this, a different tax rate might apply (Logic to be implemented in Calculator)'
            ]
        );
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
