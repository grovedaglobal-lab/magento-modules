<?php
namespace Vendor\Ads\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Wallet extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_ads_wallet', 'vendor_id');
        $this->_isPkAutoIncrement = false;
    }
}
