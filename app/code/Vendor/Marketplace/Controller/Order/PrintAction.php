<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Helper\Data as VendorHelper;

class PrintAction extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $resultPageFactory;
    protected $vendorHelper;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        PageFactory $resultPageFactory,
        VendorHelper $vendorHelper
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->vendorHelper = $vendorHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $orderId = $this->getRequest()->getParam('id');
        if (!$orderId) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        // Logic to verify permission
        $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
        if (!$vendorOrder->getId()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if ($vendorOrder->getVendorId() != $vendor->getId()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        // Basic Print View
        // For now, reuse Order View but with Print Layout or just standard view
        // Ideally should use a specific print layout (popup or simplified)

        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('print'); // Add print handle if defined, or just rely on CSS media print
        $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
        $resultPage->getConfig()->getTitle()->set(__('Print Order #%1', $order->getIncrementId()));

        return $resultPage;
    }
}
