<?php
namespace Ticketing\SellerTicket\Block\Ticket;

use Magento\Framework\View\Element\Template;
use Ticketing\SellerTicket\Model\TicketFactory;
use Ticketing\SellerTicket\Model\ResourceModel\Message\CollectionFactory as MessageCollectionFactory;
use Vendor\Marketplace\Model\Session\VendorSession;

class ViewTicket extends Template
{
    protected $ticketFactory;
    protected $messageCollectionFactory;
    protected $vendorSession;

    public function __construct(
        Template\Context $context,
        TicketFactory $ticketFactory,
        MessageCollectionFactory $messageCollectionFactory,
        VendorSession $vendorSession,
        array $data = []
    ) {
        $this->ticketFactory = $ticketFactory;
        $this->messageCollectionFactory = $messageCollectionFactory;
        $this->vendorSession = $vendorSession;
        parent::__construct($context, $data);
    }

    public function getTicket()
    {
        $id = $this->getRequest()->getParam('id');
        $ticket = $this->ticketFactory->create()->load($id);

        $vendorId = $this->vendorSession->getVendorId();

        // Ensure the ticket exists and belongs to the logged-in vendor
        if ($ticket->getId() && $ticket->getVendorId() == $vendorId) {
            return $ticket;
        }

        return false;
    }

    public function getMessages($ticketId)
    {
        $collection = $this->messageCollectionFactory->create();
        $collection->addFieldToFilter('ticket_id', $ticketId);
        $collection->setOrder('created_at', 'ASC');
        return $collection;
    }

    public function getPostActionUrl()
    {
        return $this->getUrl('sellerticket/ticket/savereply');
    }
}
