<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorProfile extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_profile', 'profile_id');
    }
}
