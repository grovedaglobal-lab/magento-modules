<?php
namespace Ticketing\SellerTicket\Controller\Ticket;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Ticketing\SellerTicket\Model\MessageFactory;

class SaveReply extends AbstractVendor
{
    protected $messageFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        MessageFactory $messageFactory,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context, $vendorSession);
        $this->messageFactory = $messageFactory;
        $this->customerSession = $customerSession;
    }

    public function execute()
    {
        $request = $this->getRequest();
        $ticketId = $request->getParam('ticket_id');
        $messageText = $request->getParam('message');

        if ($ticketId && trim($messageText)) {
            try {
                $senderName = 'Seller';
                $customer = $this->customerSession->getCustomer();
                if ($customer && $customer->getId()) {
                    $senderName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
                }

                $model = $this->messageFactory->create();
                $model->setData([
                    'ticket_id' => $ticketId,
                    'sender_type' => 'Vendor',
                    'sender_name' => $senderName,
                    'message' => trim($messageText)
                ]);
                $model->save();
                $this->messageManager->addSuccessMessage(__('Reply submitted successfully.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error submitting reply: %1', $e->getMessage()));
            }
        } else {
            $this->messageManager->addErrorMessage(__('Please enter a message.'));
        }

        return $this->_redirect('*/*/view', ['id' => $ticketId]);
    }
}
