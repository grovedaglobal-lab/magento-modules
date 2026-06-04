<?php
namespace Ticketing\SellerTicket\Block\Ticket;

use Magento\Framework\View\Element\Template;
use Vendor\Marketplace\Model\Session\VendorSession;
use Ticketing\SellerTicket\Model\ResourceModel\Ticket\CollectionFactory as TicketCollectionFactory;

class ListTicket extends Template
{
    protected $vendorSession;
    protected $ticketCollectionFactory;

    public function __construct(
        Template\Context $context,
        VendorSession $vendorSession,
        TicketCollectionFactory $ticketCollectionFactory,
        array $data = []
    ) {
        $this->vendorSession = $vendorSession;
        $this->ticketCollectionFactory = $ticketCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getTickets()
    {
        $vendorId = $this->vendorSession->getVendorId();
        if (!$vendorId) {
            return false;
        }

        $collection = $this->ticketCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->setOrder('created_at', 'DESC');
        return $collection;
    }

    public function getViewUrl($ticket)
    {
        return $this->getUrl('sellerticket/ticket/view', ['id' => $ticket->getId()]);
    }
}
