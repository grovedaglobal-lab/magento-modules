<?php
namespace Vendor\Ads\Model\ResourceModel\Keyword;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Ads\Model\Keyword as Model;
use Vendor\Ads\Model\ResourceModel\Keyword as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
