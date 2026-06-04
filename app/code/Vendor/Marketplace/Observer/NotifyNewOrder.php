<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\NotificationManagement;
use Vendor\Marketplace\Model\Notification;

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
