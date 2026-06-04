<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;

class Tables implements OptionSourceInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $connection = $this->resource->getConnection();
        $tables = $connection->getTables();
        $options = [];

        // Keywords to filter relevant tables
        $keywords = ['vendor', 'seller', 'marketplace', 'shop', 'partner', 'merchant', 'udropship'];

        foreach ($tables as $table) {
            $isRelevant = false;
            foreach ($keywords as $keyword) {
                if (stripos($table, $keyword) !== false) {
                    $isRelevant = true;
                    break;
                }
            }

            if ($isRelevant) {
                $options[] = [
                    'value' => $table,
                    'label' => $table
                ];
            }
        }

        // Sort options alphabetically
        usort($options, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        // Add a generic 'custom' option just in case a table name doesn't match keys
        array_unshift($options, ['value' => '', 'label' => __('-- Select Vendor Table --')]);

        return $options;
    }
}
