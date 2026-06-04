<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorDocument\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected $_excludeCoa = true;
    protected $_excludeOrganic = true;

    protected function _initSelect()
    {
        parent::_initSelect();

        // Join with vendor_profile to get shop_name
        $this->getSelect()->joinLeft(
            ['vp' => $this->getTable('vendor_profile')],
            'main_table.vendor_id = vp.vendor_id',
            ['shop_name']
        );

        // Join with vendor_entity to get customer_id
        $this->getSelect()->joinLeft(
            ['ve' => $this->getTable('vendor_entity')],
            'main_table.vendor_id = ve.entity_id',
            ['customer_id']
        );

        // Join with customer_entity to get vendor name (firstname + lastname)
        $this->getSelect()->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            've.customer_id = ce.entity_id',
            [
                'vendor_name' => new \Zend_Db_Expr("CONCAT(IFNULL(ce.firstname, ''), ' ', IFNULL(ce.lastname, ''))")
            ]
        );

        if ($this->_excludeCoa) {
            $this->getSelect()->where('main_table.document_type != ?', 'coa_report');
        }

        if ($this->_excludeOrganic) {
            $this->getSelect()->where('main_table.document_type != ?', 'organic_certification');
        }

        $this->getSelect()->group('main_table.vendor_id');

        $this->getSelect()->columns([
            'documents_data' => new \Zend_Db_Expr('GROUP_CONCAT(CONCAT(main_table.entity_id, "::::", IFNULL(main_table.label, ""), "::::", IFNULL(main_table.document_type, ""), "::::", IFNULL(main_table.status, ""), "::::", IFNULL(main_table.file_path, ""), "::::", IFNULL(main_table.registration_date, ""), "::::", IFNULL(main_table.expiry_date, ""), "::::", IFNULL(main_table.certificate_number, ""), "::::", IFNULL(main_table.issuer, ""), "::::", IFNULL(main_table.is_visible, 1)) SEPARATOR "|")')
        ]);

        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('shop_name', 'vp.shop_name');
        $this->addFilterToMap('vendor_name', new \Zend_Db_Expr("CONCAT(IFNULL(ce.firstname, ''), ' ', IFNULL(ce.lastname, ''))"));
        $this->addFilterToMap('status', 'main_table.status');

        return $this;
    }
}
