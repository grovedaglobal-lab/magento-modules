<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorCommission extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_commission', 'entity_id');
    }
}
