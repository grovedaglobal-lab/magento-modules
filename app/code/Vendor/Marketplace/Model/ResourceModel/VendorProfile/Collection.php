<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Marketplace\Model\VendorProfile as VendorProfileModel;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile as VendorProfileResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'profile_id';

    protected function _construct()
    {
        $this->_init(VendorProfileModel::class, VendorProfileResource::class);
    }
}
