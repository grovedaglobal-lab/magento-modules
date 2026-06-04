<?php
namespace Vendor\Ads\Model\ResourceModel\Stats;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Ads\Model\Stats as Model;
use Vendor\Ads\Model\ResourceModel\Stats as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
