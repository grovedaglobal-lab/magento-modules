<?php
namespace Ticketing\SellerTicket\Controller\Adminhtml\Ticket;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Ticketing\SellerTicket\Model\TicketFactory;
use Ticketing\SellerTicket\Model\MessageFactory;
use Magento\Backend\Model\Auth\Session;

class SaveReply extends Action
{
    const ADMIN_RESOURCE = 'Ticketing_SellerTicket::ticket_manage';
    protected $ticketFactory;
    protected $messageFactory;
    protected $authSession;

    public function __construct(
        Context $context,
        TicketFactory $ticketFactory,
        MessageFactory $messageFactory,
        Session $authSession
    ) {
        parent::__construct($context);
        $this->ticketFactory = $ticketFactory;
        $this->messageFactory = $messageFactory;
        $this->authSession = $authSession;
    }

    public function execute()
    {
        $ticketId = $this->getRequest()->getParam('id');
        $messageText = $this->getRequest()->getParam('message');
        $status = $this->getRequest()->getParam('status');

        if (!$ticketId || empty($messageText)) {
            $this->messageManager->addErrorMessage(__('Invalid request. Ticket ID and Message are required.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/view', ['id' => $ticketId]);
        }

        try {
            $ticket = $this->ticketFactory->create()->load($ticketId);
            if (!$ticket->getId()) {
                throw new \Exception('Ticket not found.');
            }

            // Update status if changed
            if ($status && $ticket->getStatus() !== $status) {
                $ticket->setStatus($status);
                $ticket->save();
            }

            $user = $this->authSession->getUser();
            $adminName = $user ? $user->getFirstName() . ' ' . $user->getLastName() : 'Administrator';

            $messageModel = $this->messageFactory->create();
            $messageModel->setData([
                'ticket_id' => $ticketId,
                'sender_type' => 'Admin',
                'sender_name' => $adminName,
                'message' => $messageText
            ]);
            $messageModel->save();

            $this->messageManager->addSuccessMessage(__('Your reply has been added.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error saving reply: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/view', ['id' => $ticketId]);
    }
}
