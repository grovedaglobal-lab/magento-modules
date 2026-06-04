<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorDocument;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Marketplace\Model\VendorDocument as DocumentModel;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument as DocumentResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(DocumentModel::class, DocumentResource::class);
    }
}
