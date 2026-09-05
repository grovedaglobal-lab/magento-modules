<?php
namespace Ticketing\SellerTicket\Controller\Ticket;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Ticketing\SellerTicket\Model\TicketFactory;
use Ticketing\SellerTicket\Model\MessageFactory;

class Save extends AbstractVendor
{
    protected $ticketFactory;
    protected $messageFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        TicketFactory $ticketFactory,
        MessageFactory $messageFactory,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context, $vendorSession);
        $this->ticketFactory = $ticketFactory;
        $this->messageFactory = $messageFactory;
        $this->customerSession = $customerSession;
    }

    public function execute()
    {
        $redirectUrl = '*/*/index';

        if (!$this->getRequest()->isPost()) {
            return $this->_redirect($redirectUrl);
        }

        $formKeyValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Data\Form\FormKey\Validator::class);
        if (!$formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
            return $this->_redirect($redirectUrl);
        }

        $vendorId = $this->_vendorSession->getVendorId();
        $subject = $this->getRequest()->getParam('subject');
        $category = $this->getRequest()->getParam('category');
        $priority = $this->getRequest()->getParam('priority');
        $messageText = $this->getRequest()->getParam('message');

        if (trim($subject) && trim($messageText) && trim($category) && trim($priority)) {
            try {
                // Initialize and save the new ticket object
                $ticket = $this->ticketFactory->create();
                $ticket->setData([
                    'vendor_id' => $vendorId,
                    'subject' => trim($subject),
                    'category' => trim($category),
                    'status' => 'Open',
                    'priority' => trim($priority)
                ]);
                $ticket->save();

                $ticketId = $ticket->getId();

                // Initialize and save the initial message
                if ($ticketId) {
                    $senderName = 'Seller';
                    $customer = $this->customerSession->getCustomer();
                    if ($customer && $customer->getId()) {
                        $senderName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
                    }

                    $message = $this->messageFactory->create();
                    $message->setData([
                        'ticket_id' => $ticketId,
                        'sender_type' => 'Vendor',
                        'sender_name' => $senderName,
                        'message' => trim($messageText)
                    ]);
                    $message->save();
                }

                $this->messageManager->addSuccessMessage(__('Ticket created successfully.'));
                $redirectUrl = '*/*/view';
                return $this->_redirect($redirectUrl, ['id' => $ticketId]);

            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error creating ticket: %1', $e->getMessage()));
                return $this->_redirect('*/*/newAction');
            }
        } else {
            $this->messageManager->addErrorMessage(__('Please select a Category, Priority, and provide both a Subject and a Message.'));
            return $this->_redirect('*/*/newAction');
        }
    }
}
