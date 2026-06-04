<?php
namespace Ticketing\SellerTicket\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Message extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('seller_ticket_message', 'message_id');
    }
}
