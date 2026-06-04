<?php
namespace Vendor\Ads\Model\ResourceModel\Campaign;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Vendor\Ads\Model\Campaign::class,
            \Vendor\Ads\Model\ResourceModel\Campaign::class
        );
    }
}
