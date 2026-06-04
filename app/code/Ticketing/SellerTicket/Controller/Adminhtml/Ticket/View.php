<?php
namespace Ticketing\SellerTicket\Controller\Adminhtml\Ticket;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class View extends Action
{
    const ADMIN_RESOURCE = 'Ticketing_SellerTicket::ticket_manage';
    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid ticket ID.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ticketing_SellerTicket::ticket_manage');
        $resultPage->getConfig()->getTitle()->prepend(__('View Ticket #%1', $id));

        return $resultPage;
    }
}
