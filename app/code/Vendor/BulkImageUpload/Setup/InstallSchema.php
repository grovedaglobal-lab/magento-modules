<?php
namespace Vendor\BulkImageUpload\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (!$installer->tableExists('vendor_bulk_image_job')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('vendor_bulk_image_job')
            )
            ->addColumn(
                'job_id',
                Table::TYPE_TEXT,
                32,
                ['nullable' => false, 'primary' => true],
                'Job ID'
            )
            ->addColumn(
                'vendor_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false],
                'Vendor ID'
            )
            ->addColumn(
                'zip_filename',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Original ZIP Filename'
            )
            ->addColumn(
                'status',
                Table::TYPE_TEXT,
                32,
                ['nullable' => false, 'default' => 'pending'],
                'Status'
            )
            ->addColumn(
                'total_skus',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'default' => 0],
                'Total SKUs Found'
            )
            ->addColumn(
                'processed_skus',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'default' => 0],
                'Processed SKUs'
            )
            ->addColumn(
                'success_count',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'default' => 0],
                'Success Count'
            )
            ->addColumn(
                'result_json',
                Table::TYPE_TEXT,
                '16M',
                ['nullable' => true],
                'Full Result JSON'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addColumn(
                'completed_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => true],
                'Completed At'
            )
            ->setComment('Bulk Image Upload Jobs');
            
            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}
