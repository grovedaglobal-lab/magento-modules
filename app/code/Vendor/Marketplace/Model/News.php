<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;

class News extends AbstractModel
{
    /**
     * Target type all vendors
     */
    const TARGET_ALL = 'all';

    /**
     * Target type specific vendors
     */
    const TARGET_SPECIFIC = 'specific';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Vendor\Marketplace\Model\ResourceModel\News::class);
    }
}
