<?php
namespace Ticketing\SellerTicket\Model\ResourceModel\Ticket\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    /**
     * Init collection select
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        // Join vendor profile for vendor tickets
        $this->getSelect()->joinLeft(
            ['vp' => $this->getTable('vendor_profile')],
            'main_table.vendor_id = vp.vendor_id AND main_table.vendor_id > 0',
            ['vendor_name' => 'vp.shop_name']
        );

        // Join customer entity for customer tickets
        $this->getSelect()->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            ['customer_name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)")]
        );

        // Use customer name when no vendor
        $this->getSelect()->columns([
            'vendor_name' => new \Zend_Db_Expr(
                "COALESCE(NULLIF(vp.shop_name, ''), CONCAT(ce.firstname, ' ', ce.lastname))"
            )
        ]);

        $this->addFilterToMap('vendor_name', 'vp.shop_name');
        $this->addFilterToMap('vendor_id', 'main_table.vendor_id');

        return $this;
    }
}
