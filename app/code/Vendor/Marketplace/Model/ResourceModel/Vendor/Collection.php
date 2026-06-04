<?php
namespace Vendor\Marketplace\Model\ResourceModel\Vendor;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Marketplace\Model\Vendor as VendorModel;
use Vendor\Marketplace\Model\ResourceModel\Vendor as VendorResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(VendorModel::class, VendorResource::class);
    }

    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['profile' => $this->getTable('vendor_profile')],
            'main_table.entity_id = profile.vendor_id',
            ['shop_name', 'logo', 'description', 'banner', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 'country', 'company_name', 'tax_id', 'business_license', 'signature', 'authorized_name']
        );
        return $this;
    }
}
