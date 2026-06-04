<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;

class VendorSalesRule extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Vendor\Marketplace\Model\ResourceModel\VendorSalesRule::class);
    }
}
