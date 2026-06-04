<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;

class View extends Action
{
    protected $resultPageFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Session $customerSession
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/vendor_order_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        // Register shutdown function to catch fatal errors
        register_shutdown_function(function () use ($logger) {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $logger->info('FATAL ERROR: ' . $error['message']);
                $logger->info('File: ' . $error['file'] . ' Line: ' . $error['line']);
            }
        });

        $logger->info('=== ORDER VIEW CONTROLLER START ===');
        $logger->info('Order ID: ' . $this->getRequest()->getParam('id'));

        if (!$this->customerSession->isLoggedIn()) {
            $logger->info('User not logged in, redirecting');
            return $this->resultRedirectFactory->create()->setPath('marketplace/account/login');
        }

        $logger->info('User is logged in, customer ID: ' . $this->customerSession->getCustomerId());
        $logger->info('Creating result page');

        try {
            $resultPage = $this->resultPageFactory->create();
            $logger->info('Result page created successfully');
            $resultPage->getConfig()->getTitle()->set(__('Order Details'));
            $logger->info('Page title set');
            return $resultPage;
        } catch (\Throwable $e) {
            $logger->info('THROWABLE ERROR creating page: ' . $e->getMessage());
            $logger->info('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
