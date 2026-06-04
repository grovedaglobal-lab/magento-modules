<?php
namespace Ticketing\SellerTicket\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Ticket extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('seller_ticket', 'entity_id');
    }
}
