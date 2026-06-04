<?php
namespace Tax\IndianGST\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\App\ResourceConnection;
use Tax\IndianGST\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;

class VendorName extends Column
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var HelperData
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resource
     * @param HelperData $helper
     * @param LoggerInterface $logger
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resource,
        HelperData $helper,
        LoggerInterface $logger,
        array $components = [],
        array $data = []
    ) {
        $this->resource = $resource;
        $this->helper = $helper;
        $this->logger = $logger;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $tableName = $this->helper->getVendorTableName();
            $idCol = $this->helper->getVendorIdColumn();
            $nameCol = $this->helper->getVendorCodeColumn(); // Uses the "Display Column" setting (e.g. shop_name)

            if ($tableName && $idCol && $nameCol) {
                try {
                    $connection = $this->resource->getConnection();
                    $tbl = $this->resource->getTableName($tableName);

                    foreach ($dataSource['data']['items'] as &$item) {
                        if (isset($item['vendor_code'])) {
                            // Fetch name for this ID
                            // Optimization: In real world, we should fetch all IDs in one query to avoid N+1 problem.
                            // But for admin grid (20 items), simple query is acceptable for dynamic table logic.

                            $select = $connection->select()
                                ->from($tbl, [$nameCol])
                                ->where($idCol . ' = ?', $item['vendor_code']);

                            $name = $connection->fetchOne($select);

                            if ($name) {
                                $item['vendor_code'] = $name;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error fetching vendor name in grid: ' . $e->getMessage());
                }
            }
        }

        return $dataSource;
    }
}
