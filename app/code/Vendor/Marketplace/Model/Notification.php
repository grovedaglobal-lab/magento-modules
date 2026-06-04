<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;

class Notification extends AbstractModel
{
    /**
     * Notification types
     */
    const TYPE_NEW_ORDER = 'new_order';
    const TYPE_NEW_REVIEW = 'new_review';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Vendor\Marketplace\Model\ResourceModel\Notification::class);
    }
}
