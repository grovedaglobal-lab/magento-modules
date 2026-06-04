<?php
namespace Vendor\Marketplace\Controller\Notification;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Model\Session\VendorSession;

class All extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param VendorSession $vendorSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        VendorSession $vendorSession
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->vendorSession = $vendorSession;
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/notification_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('=== NOTIFICATION INDEX CONTROLLER START ===');

        $vendorId = $this->vendorSession->getVendorId();
        $logger->info('Vendor ID found in session: ' . ($vendorId ?: 'NULL'));

        if (!$vendorId) {
            $logger->info('No vendor ID, redirecting to login');
            return $this->_redirect('customer/account/login');
        }

        $logger->info('Creating result page');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My Notifications'));
        $logger->info('Result page created');
        return $resultPage;
    }
}
