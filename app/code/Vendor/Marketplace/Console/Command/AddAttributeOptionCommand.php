<?php
/**
 * CLI Command: Add Package Size Option
 * 
 * Usage: php bin/magento vendor:attribute:add-option package_size "3kg"
 */
namespace Vendor\Marketplace\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vendor\Marketplace\Model\Product\Attribute\PackageSizeManager;

class AddAttributeOptionCommand extends Command
{
    const ARGUMENT_ATTRIBUTE = 'attribute';
    const ARGUMENT_VALUE = 'value';

    /**
     * @var PackageSizeManager
     */
    private $packageSizeManager;

    /**
     * @param PackageSizeManager $packageSizeManager
     * @param string|null $name
     */
    public function __construct(
        PackageSizeManager $packageSizeManager,
        $name = null
    ) {
        $this->packageSizeManager = $packageSizeManager;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('vendor:attribute:add-option')
            ->setDescription('Add a new option to vendor product attributes')
            ->setDefinition([
                new InputArgument(
                    self::ARGUMENT_ATTRIBUTE,
                    InputArgument::REQUIRED,
                    'Attribute code (e.g., package_size)'
                ),
                new InputArgument(
                    self::ARGUMENT_VALUE,
                    InputArgument::REQUIRED,
                    'Option value to add (e.g., "3kg")'
                )
            ]);

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $attribute = $input->getArgument(self::ARGUMENT_ATTRIBUTE);
        $value = $input->getArgument(self::ARGUMENT_VALUE);

        if ($attribute !== 'package_size') {
            $output->writeln('<error>Currently only package_size attribute is supported</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Adding package size option: ' . $value . '</info>');

        try {
            // Validate format
            if (!$this->packageSizeManager->validateFormat($value)) {
                $output->writeln('<comment>Warning: Format may not be standard. Recommended formats:</comment>');
                $output->writeln('  - Weight: 100g, 1kg, 2.5kg');
                $output->writeln('  - Volume: 250ml, 1L, 1.5L');
                $output->writeln('  - Count: Pack of 12');
                $output->writeln('  - Size: Small, Medium, Large');
            }

            // Check if exists
            if ($this->packageSizeManager->optionExists($value)) {
                $output->writeln('<error>Package size "' . $value . '" already exists</error>');
                return Command::FAILURE;
            }

            // Add option
            $optionId = $this->packageSizeManager->addOption($value);

            if ($optionId) {
                $output->writeln('<info>Successfully added package size: ' . $value . '</info>');
                $output->writeln('<info>Option ID: ' . $optionId . '</info>');
                $output->writeln('');
                $output->writeln('<comment>Next steps:</comment>');
                $output->writeln('  1. Flush cache: php bin/magento cache:flush');
                $output->writeln('  2. Reindex: php bin/magento indexer:reindex');
                $output->writeln('  3. Vendors can now use this option in their products');
                
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>Failed to add package size option</error>');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
