<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorCategoryPermission extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_category_permission', 'entity_id');
    }
}
