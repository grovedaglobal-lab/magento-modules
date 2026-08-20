<?php
namespace Vendor\BulkImageUpload\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class BulkImageJob extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_bulk_image_job', 'job_id');
        $this->_isPkAutoIncrement = false;
    }
}
