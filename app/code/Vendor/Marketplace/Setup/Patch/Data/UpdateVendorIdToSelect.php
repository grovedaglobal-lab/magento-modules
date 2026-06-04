<?php
namespace Vendor\Marketplace\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;

class UpdateVendorIdToSelect implements DataPatchInterface
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
     * @inheritdoc
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'frontend_input',
            'select'
        );

        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'source_model',
            \Vendor\Marketplace\Model\Config\Source\VendorList::class
        );

        // Ensure it is visible and usable
        $eavSetup->updateAttribute(
            Product::ENTITY,
            'vendor_id',
            'backend_type',
            'int'
        );
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [
            AddVendorIdAttribute::class
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
