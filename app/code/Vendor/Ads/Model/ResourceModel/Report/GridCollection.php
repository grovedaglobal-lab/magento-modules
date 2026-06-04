<?php
namespace Vendor\Ads\Model\ResourceModel\Report;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Grid collection for the Admin Performance Reports UI component.
 */
class GridCollection extends SearchResult
{
    /**
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     * @param string $identifierName
     * @param string $connectionName
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        $mainTable = 'vendor_ads_stats',
        $resourceModel = \Vendor\Ads\Model\ResourceModel\Stats::class,
        $identifierName = null,
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $statsTable = $this->getMainTable();
        $queryTable = $this->getConnection()->getTableName('search_query');
        $bidTable   = $this->getConnection()->getTableName('vendor_ads_bid');

        // We join vendor_ads_stats (main_table) with bid and keywords
        $this->getSelect()
            ->joinLeft(
                ['b' => $bidTable],
                'main_table.bid_id = b.bid_id',
                ['vendor_id', 'bid_amount', 'match_type']
            )
            ->joinLeft(
                ['q' => $queryTable],
                'b.query_id = q.query_id',
                ['query_text']
            )
            ->columns([
                'stat_id'     => 'main_table.stat_id',
                'impressions' => 'main_table.impressions',
                'clicks'      => 'main_table.clicks',
                'ctr'         => 'main_table.ctr',
                'revenue'     => new \Zend_Db_Expr('main_table.clicks * b.bid_amount'),
                'date'        => 'main_table.date',
            ]);

        return $this;
    }
}
