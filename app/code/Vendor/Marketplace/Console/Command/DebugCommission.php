<?php
namespace Vendor\Marketplace\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;
use Vendor\Marketplace\Model\ResourceModel\VendorCommission\CollectionFactory;

class DebugCommission extends Command
{
    /**
     * @var State
     */
    protected $state;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param State $state
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        State $state,
        CollectionFactory $collectionFactory
    ) {
        $this->state = $state;
        $this->collectionFactory = $collectionFactory;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('vendor:marketplace:debug')
             ->setDescription('Debug Vendor Commission Data');
    }

    /**
     * Execute command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set
        }

        $output->writeln("Debugging Commission Data...");

        try {
            $collection = $this->collectionFactory->create();
            $connection = $collection->getConnection();
            $tableName = $collection->getMainTable();
            
            $output->writeln("Table Name: " . $tableName);
            
            // Check if table exists
            if (!$connection->isTableExists($tableName)) {
                $output->writeln("ERROR: Table does not exist!");
                return 1;
            }
            
            $output->writeln("Table exists.");
            
            // Describe the table
            $description = $connection->describeTable($tableName);
            $output->writeln("\nTable Structure:");
            foreach ($description as $columnName => $info) {
                $output->writeln(sprintf(
                    "%-20s | Type: %-15s | Nullable: %s | Default: %s | Identity: %s",
                    $columnName,
                    $info['DATA_TYPE'],
                    $info['NULLABLE'] ? 'YES' : 'NO',
                    isset($info['DEFAULT']) ? $info['DEFAULT'] : 'NULL',
                    isset($info['IDENTITY']) && $info['IDENTITY'] ? 'YES' : 'NO'
                ));
            }
            
            // Check data
            $count = $collection->getSize();
            $output->writeln("\nTotal Records: " . $count);
            
            if ($count > 0) {
                foreach ($collection as $item) {
                    $output->writeln(print_r($item->getData(), true));
                }
            }

            return 0;

        } catch (\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
        }

        return 0;
    }
}
