<?php
namespace Vendor\Ads\Model\ResourceModel\Wallet\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        
        $this->getSelect()->joinLeft(
            ['ve' => $this->getTable('vendor_entity')],
            'main_table.vendor_id = ve.entity_id',
            []
        )->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            've.customer_id = ce.entity_id',
            []
        )->joinLeft(
            ['vp' => $this->getTable('vendor_profile')],
            'main_table.vendor_id = vp.vendor_id',
            ['vendor_name' => new \Zend_Db_Expr('COALESCE(NULLIF(vp.shop_name, ""), CONCAT(ce.firstname, " ", ce.lastname), CONCAT("Vendor #", main_table.vendor_id))')]
        );

        // Calculate total spent from bids for this vendor
        $this->getSelect()->joinLeft(
            ['bid' => $this->getTable('vendor_ads_bid')],
            'main_table.vendor_id = bid.vendor_id',
            ['total_spent' => new \Zend_Db_Expr('SUM(IFNULL(bid.spent_amount, 0))')]
        )->group('main_table.vendor_id');

        return $this;
    }
}
