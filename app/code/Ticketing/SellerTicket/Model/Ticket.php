<?php
namespace Ticketing\SellerTicket\Model;

use Magento\Framework\Model\AbstractModel;

class Ticket extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Ticketing\SellerTicket\Model\ResourceModel\Ticket::class);
    }
}
