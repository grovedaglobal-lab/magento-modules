<?php
namespace Vendor\Ads\Model\ResourceModel\SearchLink;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Vendor\Ads\Model\SearchLink::class,
            \Vendor\Ads\Model\ResourceModel\SearchLink::class
        );
    }
}
