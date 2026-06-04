<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Rate extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('tax_indiangst_rate', 'rate_id');
    }
}
