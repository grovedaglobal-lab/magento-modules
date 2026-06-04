<?php
namespace Vendor\Marketplace\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class AddPickupAddressColumn implements SchemaPatchInterface
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

        $connection = $this->moduleDataSetup->getConnection();
        $tableName = $this->moduleDataSetup->getTable('vendor_profile');

        // Add pickup address columns
        $pickupColumns = [
            'pickup_address' => [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Store Pickup Street Address'
            ],
            'pickup_city' => [
                'type' => Table::TYPE_TEXT,
                'length' => 100,
                'nullable' => true,
                'comment' => 'Store Pickup City'
            ],
            'pickup_state' => [
                'type' => Table::TYPE_TEXT,
                'length' => 100,
                'nullable' => true,
                'comment' => 'Store Pickup State/Province'
            ],
            'pickup_zip_code' => [
                'type' => Table::TYPE_TEXT,
                'length' => 20,
                'nullable' => true,
                'comment' => 'Store Pickup ZIP/Postal Code'
            ],
            'pickup_country' => [
                'type' => Table::TYPE_TEXT,
                'length' => 100,
                'nullable' => true,
                'comment' => 'Store Pickup Country'
            ]
        ];

        foreach ($pickupColumns as $columnName => $columnConfig) {
            if (!$connection->tableColumnExists($tableName, $columnName)) {
                $connection->addColumn($tableName, $columnName, $columnConfig);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
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
