<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;

class TableColumns implements OptionSourceInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ResourceConnection $resource
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resource = $resource;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        // Get the saved table name
        $tableName = $this->scopeConfig->getValue('tax_indiangst/marketplace_integration_continued/vendor_table');

        if ($tableName) {
            try {
                $connection = $this->resource->getConnection();

                if ($connection->isTableExists($tableName)) {
                    $describe = $connection->describeTable($tableName);
                    foreach ($describe as $columnName => $columnData) {
                        $options[] = [
                            'value' => $columnName,
                            'label' => $columnName
                        ];
                    }

                    // Sort alphabetically
                    usort($options, function ($a, $b) {
                        return strcmp($a['label'], $b['label']);
                    });
                }
            } catch (\Exception $e) {
                // Return empty if table doesn't exist or error
            }
        }

        // Add default option
        array_unshift($options, ['value' => '', 'label' => __('-- Select Column --')]);

        return $options;
    }
}
