<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorSalesRule extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_sales_rule', 'entity_id');
    }
}
