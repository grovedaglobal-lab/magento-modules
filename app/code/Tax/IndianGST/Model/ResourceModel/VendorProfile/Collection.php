<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel\VendorProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(
            'Tax\IndianGST\Model\VendorProfile',
            'Tax\IndianGST\Model\ResourceModel\VendorProfile'
        );
    }
}
