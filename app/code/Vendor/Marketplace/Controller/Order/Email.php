<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class Email extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $orderSender;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        OrderSender $orderSender
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
        $this->orderSender = $orderSender;
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

        try {
            $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
            if (!$vendorOrder->getId())
                throw new \Exception(__('Vendor Order not found.'));

            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if ($vendorOrder->getVendorId() != $vendor->getId()) {
                throw new \Exception(__('Access Denied.'));
            }

            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());

            // Resend Order Email
            $this->orderSender->send($order, true);

            $this->messageManager->addSuccessMessage(__('Order email sent.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not send email: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $orderId]);
    }
}
