<?php
/**
 * Data Patch: Create Package Size Request Table
 * 
 * Creates table to store vendor requests for new package sizes
 * Admin reviews and approves requests to add them globally
 */
namespace Vendor\Marketplace\Setup\Patch\Schema;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Db\Ddl\Table;

class CreatePackageSizeRequestTable implements SchemaPatchInterface
{
    /**
     * @var SchemaSetupInterface
     */
    private $schemaSetup;

    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $connection = $this->schemaSetup->getConnection();
        $tableName = $this->schemaSetup->getTable('vendor_package_size_request');

        // Check if table already exists
        if ($connection->isTableExists($tableName)) {
            return $this;
        }

        // Create table
        $table = $connection->newTable($tableName)
            ->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity ID'
            )
            ->addColumn(
                'vendor_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Vendor ID'
            )
            ->addColumn(
                'package_size',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Requested Package Size'
            )
            ->addColumn(
                'reason',
                Table::TYPE_TEXT,
                null,
                ['nullable' => true],
                'Reason for Request'
            )
            ->addColumn(
                'status',
                Table::TYPE_TEXT,
                20,
                ['nullable' => false, 'default' => 'pending'],
                'Status (pending, approved, rejected)'
            )
            ->addColumn(
                'admin_notes',
                Table::TYPE_TEXT,
                null,
                ['nullable' => true],
                'Admin Notes/Rejection Reason'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                'Updated At'
            )
            ->addIndex(
                $this->schemaSetup->getIdxName($tableName, ['vendor_id']),
                ['vendor_id']
            )
            ->addIndex(
                $this->schemaSetup->getIdxName($tableName, ['status']),
                ['status']
            )
            ->addIndex(
                $this->schemaSetup->getIdxName($tableName, ['package_size']),
                ['package_size']
            )
            ->setComment('Vendor Package Size Requests');

        $connection->createTable($table);

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
