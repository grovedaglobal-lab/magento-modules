<?php
namespace Ticketing\SellerTicket\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Ticketing\SellerTicket\Model\ResourceModel\Message\CollectionFactory;
use Ticketing\SellerTicket\Model\TicketFactory;

class GetMessages implements ResolverInterface
{
    protected $messageCollectionFactory;
    protected $ticketFactory;

    public function __construct(
        CollectionFactory $messageCollectionFactory,
        TicketFactory $ticketFactory
    ) {
        $this->messageCollectionFactory = $messageCollectionFactory;
        $this->ticketFactory = $ticketFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('Customer must be logged in to view ticket messages.'));
        }

        $ticketId = $args['ticket_id'] ?? null;
        if (!$ticketId) {
            return [];
        }

        $customerId = $context->getUserId();
        $ticket = $this->ticketFactory->create()->load((int)$ticketId);

        if (!$ticket->getId() || (int)$ticket->getCustomerId() !== (int)$customerId) {
            throw new GraphQlAuthorizationException(__('You are not authorized to view messages for this ticket.'));
        }

        $collection = $this->messageCollectionFactory->create();
        $collection->addFieldToFilter('ticket_id', (int)$ticketId);
        $collection->setOrder('created_at', 'ASC');

        $messages = [];
        foreach ($collection as $message) {
            $messages[] = [
                'message_id' => $message->getId(),
                'ticket_id' => $message->getTicketId(),
                'sender_type' => $message->getSenderType(),
                'sender_name' => $message->getSenderName(),
                'message' => $message->getMessage(),
                'created_at' => $message->getCreatedAt()
            ];
        }

        return $messages;
    }
}