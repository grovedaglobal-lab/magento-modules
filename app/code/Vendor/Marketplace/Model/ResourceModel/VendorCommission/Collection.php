<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorCommission;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Marketplace\Model\VendorCommission as VendorCommissionModel;
use Vendor\Marketplace\Model\ResourceModel\VendorCommission as VendorCommissionResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(VendorCommissionModel::class, VendorCommissionResource::class);
    }
}
