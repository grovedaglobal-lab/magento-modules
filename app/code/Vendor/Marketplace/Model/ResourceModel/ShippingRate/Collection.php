<?php
namespace Vendor\Marketplace\Model\ResourceModel\ShippingRate;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Vendor\Marketplace\Model\ShippingRate::class,
            \Vendor\Marketplace\Model\ResourceModel\ShippingRate::class
        );
    }
}
