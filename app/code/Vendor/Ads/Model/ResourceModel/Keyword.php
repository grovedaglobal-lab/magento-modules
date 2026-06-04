<?php
namespace Vendor\Ads\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Keyword extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_ads_keyword', 'keyword_id');
    }
}
