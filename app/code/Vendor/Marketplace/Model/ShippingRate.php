<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;

class ShippingRate extends AbstractModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Vendor\Marketplace\Model\ResourceModel\ShippingRate::class);
    }
}
