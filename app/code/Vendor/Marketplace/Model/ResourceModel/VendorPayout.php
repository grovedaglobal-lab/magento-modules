<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorPayout extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_payout', 'entity_id');
    }
}
