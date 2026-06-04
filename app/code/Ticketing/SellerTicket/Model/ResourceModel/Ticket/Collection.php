<?php
namespace Ticketing\SellerTicket\Model\ResourceModel\Ticket;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(
            \Ticketing\SellerTicket\Model\Ticket::class,
            \Ticketing\SellerTicket\Model\ResourceModel\Ticket::class
        );
    }
}
