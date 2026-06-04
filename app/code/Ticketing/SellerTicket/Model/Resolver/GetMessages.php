<?php
namespace Ticketing\SellerTicket\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Ticketing\SellerTicket\Model\ResourceModel\Message\CollectionFactory;

class GetMessages implements ResolverInterface
{
    protected $messageCollectionFactory;

    public function __construct(
        CollectionFactory $messageCollectionFactory
    ) {
        $this->messageCollectionFactory = $messageCollectionFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $ticketId = $args['ticket_id'] ?? null;
        if (!$ticketId) {
            return [];
        }

        $collection = $this->messageCollectionFactory->create();
        $collection->addFieldToFilter('ticket_id', $ticketId);
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
