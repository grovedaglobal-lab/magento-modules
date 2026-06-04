<?php
namespace Vendor\Ads\Model\ResourceModel\Bid\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        
        // Join with search_query for query_text
        $this->getSelect()->joinLeft(
            ['sq' => $this->getTable('search_query')],
            'main_table.query_id = sq.query_id',
            ['query_text']
        );

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

        // Join with catalog_product_entity_varchar for product_name
        $this->getSelect()->joinLeft(
            ['cpev' => $this->getTable('catalog_product_entity_varchar')],
            'main_table.product_id = cpev.entity_id AND cpev.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = "name" AND entity_type_id = (SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = "catalog_product")) AND cpev.store_id = 0',
            ['product_name' => 'cpev.value']
        );

        return $this;
    }
}
