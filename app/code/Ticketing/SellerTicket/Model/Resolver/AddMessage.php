<?php
namespace Ticketing\SellerTicket\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Ticketing\SellerTicket\Model\MessageFactory;

class AddMessage implements ResolverInterface
{
    protected $messageFactory;

    public function __construct(
        MessageFactory $messageFactory
    ) {
        $this->messageFactory = $messageFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($args['input'])) {
            throw new GraphQlInputException(__('Specify input.'));
        }
        $input = $args['input'];

        $messageModel = $this->messageFactory->create();
        $messageModel->setData([
            'ticket_id' => $input['ticket_id'],
            'sender_type' => $input['sender_type'],
            'sender_name' => $input['sender_name'],
            'message' => $input['message']
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
