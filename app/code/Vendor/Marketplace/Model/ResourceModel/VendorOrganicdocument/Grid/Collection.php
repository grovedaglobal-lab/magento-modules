<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorOrganicdocument\Grid;

use Vendor\Marketplace\Model\ResourceModel\VendorDocument\Grid\Collection as DocumentCollection;

class Collection extends DocumentCollection
{
    protected $_excludeOrganic = false;
    protected $_excludeCoa = false; // It extends DocumentCollection which has $_excludeCoa = true, but DocumentCollection will now have $_excludeOrganic.

    protected function _initSelect()
    {
        parent::_initSelect();

        // Filter the collection to only show organic certification documents
        $this->getSelect()->where('main_table.document_type = ?', 'organic_certification');

        return $this;
    }
}
