<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorSalesRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(
            \Vendor\Marketplace\Model\VendorSalesRule::class,
            \Vendor\Marketplace\Model\ResourceModel\VendorSalesRule::class
        );
    }
}
