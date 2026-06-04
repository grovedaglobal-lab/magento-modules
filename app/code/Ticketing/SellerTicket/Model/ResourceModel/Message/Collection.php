<?php
namespace Ticketing\SellerTicket\Model\ResourceModel\Message;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'message_id';

    protected function _construct()
    {
        $this->_init(
            \Ticketing\SellerTicket\Model\Message::class,
            \Ticketing\SellerTicket\Model\ResourceModel\Message::class
        );
    }
}
