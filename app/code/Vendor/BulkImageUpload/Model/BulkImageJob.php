<?php
namespace Vendor\BulkImageUpload\Model;

use Magento\Framework\Model\AbstractModel;

class BulkImageJob extends AbstractModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected function _construct()
    {
        $this->_init(\Vendor\BulkImageUpload\Model\ResourceModel\BulkImageJob::class);
    }
}
