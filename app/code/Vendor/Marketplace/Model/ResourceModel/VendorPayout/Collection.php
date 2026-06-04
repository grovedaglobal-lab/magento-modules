<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorPayout;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Marketplace\Model\VendorPayout as VendorPayoutModel;
use Vendor\Marketplace\Model\ResourceModel\VendorPayout as VendorPayoutResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(VendorPayoutModel::class, VendorPayoutResource::class);
    }
}
