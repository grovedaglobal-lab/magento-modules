<?php
declare(strict_types=1);

namespace Tax\IndianGST\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class CreateDefaultGstRates implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $table = $this->moduleDataSetup->getTable('tax_indiangst_rate');

        $defaultRates = [
            [
                'tax_code' => 'GST_EXEMPT',
                'tax_name' => 'GST Exempt',
                'cgst_rate' => 0.00,
                'sgst_rate' => 0.00,
                'igst_rate' => 0.00,
                'cess_rate' => 0.00,
                'total_rate' => 0.00,
                'is_active' => 1,
                'description' => 'Exempt from GST - Essential goods like fresh vegetables, milk, etc.'
            ],
            [
                'tax_code' => 'GST_0.25',
                'tax_name' => 'GST 0.25%',
                'cgst_rate' => 0.125,
                'sgst_rate' => 0.125,
                'igst_rate' => 0.25,
                'cess_rate' => 0.00,
                'total_rate' => 0.25,
                'is_active' => 1,
                'description' => 'Minimum GST rate - Rough diamonds, precious stones'
            ],
            [
                'tax_code' => 'GST_1.5',
                'tax_name' => 'GST 1.5%',
                'cgst_rate' => 0.75,
                'sgst_rate' => 0.75,
                'igst_rate' => 1.5,
                'cess_rate' => 0.00,
                'total_rate' => 1.5,
                'is_active' => 1,
                'description' => 'Special rate - Cut and polished diamonds'
            ],
            [
                'tax_code' => 'GST_3',
                'tax_name' => 'GST 3%',
                'cgst_rate' => 1.5,
                'sgst_rate' => 1.5,
                'igst_rate' => 3.0,
                'cess_rate' => 0.00,
                'total_rate' => 3.0,
                'is_active' => 1,
                'description' => 'Jewellery rate - Gold, silver ornaments'
            ],
            [
                'tax_code' => 'GST_5',
                'tax_name' => 'GST 5%',
                'cgst_rate' => 2.5,
                'sgst_rate' => 2.5,
                'igst_rate' => 5.0,
                'cess_rate' => 0.00,
                'total_rate' => 5.0,
                'is_active' => 1,
                'description' => 'Super reduced rate - Household necessities, packaged food items'
            ],
            [
                'tax_code' => 'GST_12',
                'tax_name' => 'GST 12%',
                'cgst_rate' => 6.0,
                'sgst_rate' => 6.0,
                'igst_rate' => 12.0,
                'cess_rate' => 0.00,
                'total_rate' => 12.0,
                'is_active' => 1,
                'description' => 'Reduced rate - Processed food, computers, medicines'
            ],
            [
                'tax_code' => 'GST_18',
                'tax_name' => 'GST 18%',
                'cgst_rate' => 9.0,
                'sgst_rate' => 9.0,
                'igst_rate' => 18.0,
                'cess_rate' => 0.00,
                'total_rate' => 18.0,
                'is_active' => 1,
                'description' => 'Standard rate - Most goods and services'
            ],
            [
                'tax_code' => 'GST_28',
                'tax_name' => 'GST 28%',
                'cgst_rate' => 14.0,
                'sgst_rate' => 14.0,
                'igst_rate' => 28.0,
                'cess_rate' => 0.00,
                'total_rate' => 28.0,
                'is_active' => 1,
                'description' => 'Peak rate - Luxury goods, automobiles, tobacco'
            ],
            [
                'tax_code' => 'GST_28_CESS12',
                'tax_name' => 'GST 28% + Cess 12%',
                'cgst_rate' => 14.0,
                'sgst_rate' => 14.0,
                'igst_rate' => 28.0,
                'cess_rate' => 12.0,
                'total_rate' => 40.0,
                'is_active' => 1,
                'description' => 'Peak rate with cess - Small petrol cars, SUVs'
            ],
            [
                'tax_code' => 'GST_28_CESS60',
                'tax_name' => 'GST 28% + Cess 60%',
                'cgst_rate' => 14.0,
                'sgst_rate' => 14.0,
                'igst_rate' => 28.0,
                'cess_rate' => 60.0,
                'total_rate' => 88.0,
                'is_active' => 1,
                'description' => 'Peak rate with high cess - Luxury vehicles, tobacco products'
            ]
        ];

        foreach ($defaultRates as $rate) {
            // Check if already exists
            $select = $this->moduleDataSetup->getConnection()->select()
                ->from($table, 'rate_id')
                ->where('tax_code = ?', $rate['tax_code']);

            $exists = $this->moduleDataSetup->getConnection()->fetchOne($select);

            if (!$exists) {
                $this->moduleDataSetup->getConnection()->insert($table, $rate);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
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
