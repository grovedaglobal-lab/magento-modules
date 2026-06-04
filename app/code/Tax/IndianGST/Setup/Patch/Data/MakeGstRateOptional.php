<?php
declare(strict_types=1);

namespace Tax\IndianGST\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product;

class MakeGstRateOptional implements DataPatchInterface
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

        // 1. GST Rate - Force Cleanup
        $eavSetup->updateAttribute(Product::ENTITY, 'gst_rate', 'is_required', 0);
        $eavSetup->updateAttribute(Product::ENTITY, 'gst_rate', 'frontend_class', '');
        $eavSetup->updateAttribute(Product::ENTITY, 'gst_rate', 'default_value', '');
        $eavSetup->updateAttribute(Product::ENTITY, 'gst_rate', 'note', '');

        // 2. HSN Code - Force Cleanup
        $eavSetup->updateAttribute(Product::ENTITY, 'hsn_code', 'is_required', 0);
        $eavSetup->updateAttribute(Product::ENTITY, 'hsn_code', 'frontend_class', '');
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
