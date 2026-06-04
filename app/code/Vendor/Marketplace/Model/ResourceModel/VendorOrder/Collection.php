<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Marketplace\Model\VendorOrder as VendorOrderModel;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder as VendorOrderResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(VendorOrderModel::class, VendorOrderResource::class);
    }
}
