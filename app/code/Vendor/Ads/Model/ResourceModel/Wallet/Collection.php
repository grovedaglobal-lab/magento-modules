<?php
namespace Vendor\Ads\Model\ResourceModel\Wallet;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Ads\Model\Wallet as Model;
use Vendor\Ads\Model\ResourceModel\Wallet as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
