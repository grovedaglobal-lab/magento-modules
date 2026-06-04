<?php
namespace Vendor\Ads\Model\ResourceModel\AdGroup;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Vendor\Ads\Model\AdGroup::class,
            \Vendor\Ads\Model\ResourceModel\AdGroup::class
        );
    }
}
