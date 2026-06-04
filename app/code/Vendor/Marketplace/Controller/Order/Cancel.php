<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderManagementInterface;

class Cancel extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $orderManagement;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        OrderManagementInterface $orderManagement
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
        $this->orderManagement = $orderManagement;
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

            // CANCEL LOGIC
            // Warning: This cancels the entire Sales Order.
            // In a real marketplace, we might want to only cancel vendor items (complex).
            // For now, allow cancel if order allows it.

            $this->orderManagement->cancel($vendorOrder->getOrderId());

            // Updates status
            $vendorOrder->setStatus('canceled');
            $vendorOrder->save();

            $this->messageManager->addSuccessMessage(__('Order has been canceled.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not cancel order: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $orderId]);
    }
}
