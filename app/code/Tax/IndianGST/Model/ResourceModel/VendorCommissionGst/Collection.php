<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel\VendorCommissionGst;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Tax\IndianGST\Model\VendorCommissionGst',
            'Tax\IndianGST\Model\ResourceModel\VendorCommissionGst'
        );
    }
}
