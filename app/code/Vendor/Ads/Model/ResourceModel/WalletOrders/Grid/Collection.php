<?php
declare(strict_types=1);

namespace Vendor\Ads\Model\ResourceModel\WalletOrders\Grid;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OriginalCollection;

class Collection extends OriginalCollection
{
    /**
     * _initSelect
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        
        $this->getSelect()->where('main_table.is_wallet_recharge = ?', 1);

        return $this;
    }
}
