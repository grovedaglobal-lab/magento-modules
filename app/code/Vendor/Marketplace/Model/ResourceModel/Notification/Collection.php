<?php
namespace Vendor\Marketplace\Model\ResourceModel\Notification;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Vendor\Marketplace\Model\Notification::class,
            \Vendor\Marketplace\Model\ResourceModel\Notification::class
        );
    }
}
