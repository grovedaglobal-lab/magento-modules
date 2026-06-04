<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class GstRate extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tax_indiangst_rates', 'rate_id');
    }
}
