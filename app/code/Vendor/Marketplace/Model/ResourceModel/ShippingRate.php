<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ShippingRate extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('vendor_shipping_rate', 'rate_id');
    }
}
