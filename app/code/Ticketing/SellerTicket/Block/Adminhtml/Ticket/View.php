<?php
namespace Ticketing\SellerTicket\Block\Adminhtml\Ticket;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Ticketing\SellerTicket\Model\TicketFactory;
use Ticketing\SellerTicket\Model\ResourceModel\Message\CollectionFactory as MessageCollectionFactory;

class View extends Template
{
    protected $ticketFactory;
    protected $messageCollectionFactory;

    public function __construct(
        Context $context,
        TicketFactory $ticketFactory,
        MessageCollectionFactory $messageCollectionFactory,
        array $data = []
    ) {
        $this->ticketFactory = $ticketFactory;
        $this->messageCollectionFactory = $messageCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getTicket()
    {
        $id = $this->getRequest()->getParam('id');
        return $this->ticketFactory->create()->load($id);
    }

    public function getMessages()
    {
        $id = $this->getRequest()->getParam('id');
        $collection = $this->messageCollectionFactory->create();
        $collection->addFieldToFilter('ticket_id', $id);
        $collection->setOrder('created_at', 'ASC');
        return $collection;
    }

    public function getSaveReplyUrl()
    {
        return $this->getUrl('*/*/savereply', ['id' => $this->getRequest()->getParam('id')]);
    }
}
