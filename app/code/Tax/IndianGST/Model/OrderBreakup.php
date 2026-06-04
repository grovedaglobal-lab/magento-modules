<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model;

use Magento\Framework\Model\AbstractModel;

class OrderBreakup extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('Tax\IndianGST\Model\ResourceModel\OrderBreakup');
    }
}
