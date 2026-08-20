<?php
namespace Vendor\BulkImageUpload\Model\ResourceModel\BulkImageJob;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Vendor\BulkImageUpload\Model\BulkImageJob::class,
            \Vendor\BulkImageUpload\Model\ResourceModel\BulkImageJob::class
        );
    }
}
