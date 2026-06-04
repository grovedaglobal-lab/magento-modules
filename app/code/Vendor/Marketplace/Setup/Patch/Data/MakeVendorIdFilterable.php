<?php
namespace Vendor\Marketplace\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;

class MakeVendorIdFilterable implements DataPatchInterface
{
    private $moduleDataSetup;
    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'is_filterable',
            1 // Filterable with results
        );

        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'is_searchable',
            1
        );

        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'is_visible_in_advanced_search',
            1
        );

        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'is_filterable_in_grid',
            1
        );
    }

    public static function getDependencies()
    {
        return [
            UpdateVendorIdToSelect::class
        ];
    }

    public function getAliases()
    {
        return [];
    }
}
