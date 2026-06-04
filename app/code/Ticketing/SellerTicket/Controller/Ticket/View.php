<?php
namespace Ticketing\SellerTicket\Controller\Ticket;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Magento\Framework\View\Result\PageFactory;

class View extends AbstractVendor
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context, $vendorSession);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $ticketId = $this->getRequest()->getParam('id');
        if (!$ticketId) {
            $this->messageManager->addErrorMessage(__('Invalid ticket ID.'));
            return $this->_redirect('*/*/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('View Ticket #%1', $ticketId));
        return $resultPage;
    }
}
