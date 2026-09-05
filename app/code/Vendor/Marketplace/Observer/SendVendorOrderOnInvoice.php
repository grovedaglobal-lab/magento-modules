<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder\CollectionFactory as VendorOrderCollectionFactory;
use Vendor\Marketplace\Model\Order\Email\Sender\VendorOrderSender;
use Vendor\Marketplace\Model\NotificationManagement;
use Vendor\Marketplace\Model\Notification;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class SendVendorOrderOnInvoice implements ObserverInterface
{
    /**
     * @var VendorOrderCollectionFactory
     */
    protected $vendorOrderCollectionFactory;

    /**
     * @var VendorOrderSender
     */
    protected $vendorOrderSender;

    /**
     * @var NotificationManagement
     */
    protected $notificationManagement;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        VendorOrderCollectionFactory $vendorOrderCollectionFactory,
        VendorOrderSender $vendorOrderSender,
        NotificationManagement $notificationManagement,
        LoggerInterface $logger
    ) {
        $this->vendorOrderCollectionFactory = $vendorOrderCollectionFactory;
        $this->vendorOrderSender = $vendorOrderSender;
        $this->notificationManagement = $notificationManagement;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order) {
                $invoice = $observer->getEvent()->getInvoice();
                if ($invoice) {
                    $order = $invoice->getOrder();
                }
            }

            if (!$order || !$order->getId()) {
                return;
            }

            // Do NOT send marketplace vendor order emails for wallet recharge
            if ((int)$order->getData("is_wallet_recharge") === 1) {
                return;
            }
            foreach ($order->getAllItems() as $checkItem) {
                if ($checkItem->getSku() === "wallet-recharge" || strpos($checkItem->getSku(), "wallet") !== false) {
                    return;
                }
            }

            $state = $order->getState();
            $status = $order->getStatus();
            $isPaid = ($state === Order::STATE_PROCESSING || $state === Order::STATE_COMPLETE || (float)$order->getTotalPaid() > 0);

            // Only trigger if order is confirmed / paid
            if (!$isPaid || $state === Order::STATE_PENDING_PAYMENT || $status === 'pending_payment') {
                return;
            }

            $payment = $order->getPayment();
            $method = $payment ? $payment->getMethod() : '';
            $isOffline = in_array($method, ['cashondelivery', 'checkmo', 'banktransfer', 'purchaseorder']);

            // For offline methods (COD), SendVendorNewOrderEmail already sent on order placement
            if ($isOffline) {
                return;
            }

            // Find all vendor orders for this order
            $vendorOrders = $this->vendorOrderCollectionFactory->create()
                ->addFieldToFilter('order_id', $order->getId());

            foreach ($vendorOrders as $vendorOrder) {
                // If vendor order was already marked as processing, skip to avoid duplicate emails
                if ($vendorOrder->getStatus() === 'processing') {
                    continue;
                }

                $this->logger->info("Vendor Marketplace: Payment verified for Order #{$order->getIncrementId()}. Dispatching verified vendor email for Vendor Order #{$vendorOrder->getId()}");

                // Send Email to seller
                $this->vendorOrderSender->send($order, $vendorOrder);

                // Add in-app notification
                $vendorId = $vendorOrder->getVendorId();
                $title = __('New Order #%1', $order->getIncrementId());
                $message = __('You have received a new order for your store.');
                $link = 'vendor_marketplace/order/view/id/' . $vendorOrder->getEntityId();

                $this->notificationManagement->addNotification(
                    $vendorId,
                    Notification::TYPE_NEW_ORDER,
                    $title,
                    $message,
                    $link
                );

                // Mark vendor order as processing
                $vendorOrder->setStatus('processing');
                $vendorOrder->save();
            }
        } catch (\Exception $e) {
            $this->logger->error("Vendor Marketplace: SendVendorOrderOnInvoice Error: " . $e->getMessage());
        }
    }
}
