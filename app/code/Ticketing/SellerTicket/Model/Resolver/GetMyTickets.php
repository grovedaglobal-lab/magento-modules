<?php
namespace Ticketing\SellerTicket\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Ticketing\SellerTicket\Model\ResourceModel\Ticket\CollectionFactory;

class GetMyTickets implements ResolverInterface
{
    protected $ticketCollectionFactory;

    public function __construct(
        CollectionFactory $ticketCollectionFactory
    ) {
        $this->ticketCollectionFactory = $ticketCollectionFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('Customer must be logged in.'));
        }

        $customerId = $context->getUserId();

        $collection = $this->ticketCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');

        $tickets = [];
        foreach ($collection as $ticket) {
            $tickets[] = [
                'entity_id' => $ticket->getId(),
                'vendor_id' => (int) $ticket->getVendorId(),
                'subject' => $ticket->getSubject(),
                'category' => $ticket->getCategory(),
                'status' => $ticket->getStatus(),
                'priority' => $ticket->getPriority(),
                'created_at' => $ticket->getCreatedAt(),
                'updated_at' => $ticket->getUpdatedAt()
            ];
        }

        return $tickets;
    }
}
