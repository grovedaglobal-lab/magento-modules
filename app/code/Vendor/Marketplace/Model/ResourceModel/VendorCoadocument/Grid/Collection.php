<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorCoadocument\Grid;

use Vendor\Marketplace\Model\ResourceModel\VendorDocument\Grid\Collection as DocumentCollection;

class Collection extends DocumentCollection
{
    protected $_excludeCoa = false;

    protected function _initSelect()
    {
        parent::_initSelect();

        // Filter the collection to only show COA documents
        $this->getSelect()->where('main_table.document_type = ?', 'coa_report');

        return $this;
    }
}
