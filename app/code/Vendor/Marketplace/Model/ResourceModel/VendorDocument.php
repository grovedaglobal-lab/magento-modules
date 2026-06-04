<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorDocument extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_document', 'entity_id');
    }
}
