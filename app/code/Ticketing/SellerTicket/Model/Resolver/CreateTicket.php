<?php
namespace Ticketing\SellerTicket\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Ticketing\SellerTicket\Model\TicketFactory;
use Ticketing\SellerTicket\Model\MessageFactory;

class CreateTicket implements ResolverInterface
{
    protected $ticketFactory;
    protected $messageFactory;

    public function __construct(
        TicketFactory $ticketFactory,
        MessageFactory $messageFactory
    ) {
        $this->ticketFactory = $ticketFactory;
        $this->messageFactory = $messageFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('Customer must be logged in to create a ticket.'));
        }

        if (!isset($args['input'])) {
            throw new GraphQlInputException(__('Specify input.'));
        }

        $input = $args['input'];
        $customerId = $context->getUserId();

        // 1. Create the Ticket
        $ticket = $this->ticketFactory->create();
        $ticket->setData([
            'customer_id' => $customerId,
            'vendor_id' => $input['vendor_id'] ?? 0,
            'subject' => $input['subject'],
            'category' => $input['category'],
            'priority' => $input['priority'],
            'status' => 'Open'
        ]);
        $ticket->save();

        // 2. Create the initial Message
        $messageModel = $this->messageFactory->create();
        $messageModel->setData([
            'ticket_id' => $ticket->getId(),
            'sender_type' => 'Customer',
            'sender_name' => 'Customer',
            'message' => $input['message']
        ]);
        $messageModel->save();

        return [
            'entity_id' => (int) $ticket->getId(),
            'vendor_id' => (int) $ticket->getVendorId(),
            'subject' => $ticket->getSubject(),
            'category' => $ticket->getCategory(),
            'status' => $ticket->getStatus(),
            'priority' => $ticket->getPriority(),
            'created_at' => $ticket->getCreatedAt() ?? date('Y-m-d H:i:s'),
            'updated_at' => $ticket->getUpdatedAt() ?? date('Y-m-d H:i:s')
        ];
    }
}
