<?php
namespace Ticketing\SellerTicket\Controller\Ticket;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Magento\Framework\View\Result\PageFactory;

class NewAction extends AbstractVendor
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
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Create New Ticket'));
        return $resultPage;
    }
}
