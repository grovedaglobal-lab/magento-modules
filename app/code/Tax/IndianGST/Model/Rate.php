<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model;

use Magento\Framework\Model\AbstractModel;

class Rate extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('Tax\IndianGST\Model\ResourceModel\Rate');
    }
}
