<?php
namespace Ticketing\SellerTicket\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Ticketing\SellerTicket\Model\MessageFactory;
use Ticketing\SellerTicket\Model\TicketFactory;

class AddMessage implements ResolverInterface
{
    protected $messageFactory;
    protected $ticketFactory;
    protected $customerRepository;

    public function __construct(
        MessageFactory $messageFactory,
        TicketFactory $ticketFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->messageFactory = $messageFactory;
        $this->ticketFactory = $ticketFactory;
        $this->customerRepository = $customerRepository;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('Customer must be logged in to reply to a ticket.'));
        }

        if (!isset($args['input'])) {
            throw new GraphQlInputException(__('Specify input.'));
        }
        $input = $args['input'];
        $customerId = (int)$context->getUserId();
        $ticketId = (int)($input['ticket_id'] ?? 0);

        if (!$ticketId) {
            throw new GraphQlInputException(__('Invalid ticket ID.'));
        }

        $ticket = $this->ticketFactory->create()->load($ticketId);
        if (!$ticket->getId() || (int)$ticket->getCustomerId() !== $customerId) {
            throw new GraphQlAuthorizationException(__('You are not authorized to reply to this ticket.'));
        }

        $senderName = 'Customer';
        try {
            $customer = $this->customerRepository->getById($customerId);
            $fullName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            if (!empty($fullName)) {
                $senderName = $fullName;
            }
        } catch (\Exception $e) {
            // fallback to 'Customer'
        }

        $cleanMessage = strip_tags((string)($input['message'] ?? ''));
        if (trim($cleanMessage) === '') {
            throw new GraphQlInputException(__('Message cannot be empty.'));
        }

        $messageModel = $this->messageFactory->create();
        $messageModel->setData([
            'ticket_id' => $ticketId,
            'sender_type' => 'Customer',
            'sender_name' => $senderName,
            'message' => $cleanMessage
        ]);
        $messageModel->save();

        return [
            'message_id' => $messageModel->getId(),
            'ticket_id' => $messageModel->getTicketId(),
            'sender_type' => $messageModel->getSenderType(),
            'sender_name' => $messageModel->getSenderName(),
            'message' => $messageModel->getMessage(),
            'created_at' => $messageModel->getCreatedAt()
        ];
    }
}