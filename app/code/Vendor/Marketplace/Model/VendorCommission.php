<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Model\ResourceModel\VendorCommission as ResourceVendorCommission;

class VendorCommission extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceVendorCommission::class);
    }
}
