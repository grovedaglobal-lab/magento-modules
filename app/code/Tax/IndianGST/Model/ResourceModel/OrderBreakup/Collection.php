<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel\OrderBreakup;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Tax\IndianGST\Model\OrderBreakup',
            'Tax\IndianGST\Model\ResourceModel\OrderBreakup'
        );
    }
}
