<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorOrder extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_order', 'entity_id');
    }
}
