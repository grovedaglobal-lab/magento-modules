<?php
namespace Ticketing\SellerTicket\Controller\Adminhtml\Ticket;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
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
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ticketing_SellerTicket::ticket_manage');
        $resultPage->getConfig()->getTitle()->prepend(__('Seller Tickets'));

        return $resultPage;
    }
}
