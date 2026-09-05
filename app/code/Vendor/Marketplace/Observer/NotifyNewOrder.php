<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\NotificationManagement;
use Vendor\Marketplace\Model\Notification;
use Magento\Sales\Model\Order;

class NotifyNewOrder implements ObserverInterface
{
    /**
     * @var NotificationManagement
     */
    protected $notificationManagement;

    /**
     * @param NotificationManagement $notificationManagement
     */
    public function __construct(
        NotificationManagement $notificationManagement
    ) {
        $this->notificationManagement = $notificationManagement;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $vendorOrder = $observer->getEvent()->getVendorOrder();
        $order = $observer->getEvent()->getOrder();

        if ($vendorOrder && $order) {
            $payment = $order->getPayment();
            $method = $payment ? $payment->getMethod() : '';
            $isOffline = in_array($method, ['cashondelivery', 'checkmo', 'banktransfer', 'purchaseorder']);

            if (!$isOffline) {
                $state = $order->getState();
                $status = $order->getStatus();
                $isPaid = ($state === Order::STATE_PROCESSING || $state === Order::STATE_COMPLETE || (float)$order->getTotalPaid() > 0);

                if (!$isPaid || $state === Order::STATE_PENDING_PAYMENT || $status === 'pending_payment') {
                    return;
                }
            }

            $vendorId = $vendorOrder->getVendorId();
            $orderIncrementId = $order->getIncrementId();

            $title = __('New Order #%1', $orderIncrementId);
            $message = __('You have received a new order for your store.');
            $link = 'vendor_marketplace/order/view/id/' . $vendorOrder->getEntityId();

            $this->notificationManagement->addNotification(
                $vendorId,
                Notification::TYPE_NEW_ORDER,
                $title,
                $message,
                $link
            );
        }
    }
}
