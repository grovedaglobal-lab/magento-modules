<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model;

use Magento\Framework\Model\AbstractModel;

class VendorProfile extends AbstractModel
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init('Tax\IndianGST\Model\ResourceModel\VendorProfile');
    }
}
