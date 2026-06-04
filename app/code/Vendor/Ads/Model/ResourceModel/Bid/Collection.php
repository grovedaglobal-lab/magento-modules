<?php
namespace Vendor\Ads\Model\ResourceModel\Bid;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Ads\Model\Bid as Model;
use Vendor\Ads\Model\ResourceModel\Bid as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
