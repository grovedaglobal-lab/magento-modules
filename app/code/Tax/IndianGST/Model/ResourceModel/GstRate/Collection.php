<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel\GstRate;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'rate_id';

    protected function _construct()
    {
        $this->_init(
            'Tax\IndianGST\Model\GstRate',
            'Tax\IndianGST\Model\ResourceModel\GstRate'
        );
    }
}
