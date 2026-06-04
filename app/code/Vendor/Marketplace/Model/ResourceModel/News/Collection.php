<?php
namespace Vendor\Marketplace\Model\ResourceModel\News;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Vendor\Marketplace\Model\News::class,
            \Vendor\Marketplace\Model\ResourceModel\News::class
        );
    }
}
