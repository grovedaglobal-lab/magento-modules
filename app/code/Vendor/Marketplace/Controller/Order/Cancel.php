<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Vendor\Marketplace\Helper\Data as VendorHelper;

class Cancel extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $vendorHelper;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        VendorHelper $vendorHelper
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
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

        try {
            $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
            if (!$vendorOrder->getId()) {
                throw new \Exception(__('Vendor Order not found.'));
            }

            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if ($vendorOrder->getVendorId() != $vendor->getId()) {
                throw new \Exception(__('Access Denied.'));
            }

            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
            $vendorItems = $this->vendorHelper->getVendorOrderItems($order, $vendor->getId());

            $anyCanceled = false;
            foreach ($vendorItems as $item) {
                $qtyToCancel = (float)$item->getQtyOrdered() - (float)$item->getQtyShipped() - (float)$item->getQtyCanceled();
                if ($qtyToCancel > 0) {
                    $item->setQtyCanceled((float)$item->getQtyCanceled() + $qtyToCancel);
                    $item->save();
                    $anyCanceled = true;
                }
            }

            if (!$anyCanceled) {
                throw new \Exception(__('No unshipped items available to cancel for this order.'));
            }

            // Vendor order status: all items canceled means canceled sub-order
            $vendorOrder->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
            $vendorOrder->save();

            // Check master order across all vendors
            $allMasterItemsDone = true;
            $anyMasterShipped = false;
            foreach ($order->getAllItems() as $mItem) {
                if ($mItem->getParentItem()) {
                    continue;
                }
                if ((float)$mItem->getQtyShipped() > 0) {
                    $anyMasterShipped = true;
                }
                $mRem = (float)$mItem->getQtyOrdered() - (float)$mItem->getQtyShipped() - (float)$mItem->getQtyCanceled();
                if ($mRem > 0) {
                    $allMasterItemsDone = false;
                    break;
                }
            }

            if ($allMasterItemsDone) {
                if ($anyMasterShipped) {
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);
                    $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
                } else {
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                    $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
                }
                $order->save();
            }

            $this->messageManager->addSuccessMessage(__('All items for this order have been canceled.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not cancel items: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $orderId]);
    }
}
