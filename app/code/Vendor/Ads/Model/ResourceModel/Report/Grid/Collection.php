<?php
namespace Vendor\Ads\Model\ResourceModel\Report\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        
        // This report is Keyword-based revenue
        $this->getSelect()->joinInner(
            ['bid' => $this->getTable('vendor_ads_bid')],
            'main_table.query_id = bid.query_id',
            []
        );

        $this->getSelect()->joinLeft(
            ['stats' => $this->getTable('vendor_ads_stats')],
            'bid.bid_id = stats.bid_id',
            [
                'impressions' => new \Zend_Db_Expr('SUM(IFNULL(stats.impressions, 0))'),
                'clicks' => new \Zend_Db_Expr('SUM(IFNULL(stats.clicks, 0))'),
                'ctr' => new \Zend_Db_Expr('SUM(IFNULL(stats.clicks, 0)) / SUM(IFNULL(stats.impressions, 0))'),
                'revenue' => new \Zend_Db_Expr('SUM(IFNULL(bid.spent_amount, 0))') // Rough estimation or use actual transaction logs
            ]
        )->group('main_table.query_id');

        return $this;
    }
}
