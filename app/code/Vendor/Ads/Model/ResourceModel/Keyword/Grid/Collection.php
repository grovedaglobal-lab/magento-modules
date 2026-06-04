<?php
namespace Vendor\Ads\Model\ResourceModel\Keyword\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        
        $this->getSelect()->joinLeft(
            ['link' => $this->getTable('vendor_ads_search_link')],
            'main_table.query_id = link.query_id',
            ['is_ads_enabled' => new \Zend_Db_Expr('IFNULL(link.is_ads_enabled, 0)')]
        );

        $this->getSelect()->joinLeft(
            ['bid' => $this->getTable('vendor_ads_bid')],
            'main_table.query_id = bid.query_id',
            [
                'total_bids' => new \Zend_Db_Expr('COUNT(DISTINCT bid.bid_id)'),
                'top_bid_amount' => new \Zend_Db_Expr('MAX(bid.bid_amount)')
            ]
        )->group('main_table.query_id');

        return $this;
    }
}
