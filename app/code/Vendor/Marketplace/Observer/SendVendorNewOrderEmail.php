<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\Order\Email\Sender\VendorOrderSender;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class SendVendorNewOrderEmail implements ObserverInterface
{
    /**
     * @var VendorOrderSender
     */
    protected $vendorOrderSender;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param VendorOrderSender $vendorOrderSender
     * @param LoggerInterface $logger
     */
    public function __construct(
        VendorOrderSender $vendorOrderSender,
        LoggerInterface $logger
    ) {
        $this->vendorOrderSender = $vendorOrderSender;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $vendorOrder = $observer->getEvent()->getVendorOrder();
            $order = $observer->getEvent()->getOrder();

            if ($vendorOrder && $order) {
                // Never send new order email for wallet recharge
                if ((int)$order->getData("is_wallet_recharge") === 1) {
                    return;
                }
                foreach ($order->getAllItems() as $checkItem) {
                    if ($checkItem->getSku() === "wallet-recharge" || strpos($checkItem->getSku(), "wallet") !== false) {
                        return;
                    }
                }
                $payment = $order->getPayment();
                $method = $payment ? $payment->getMethod() : '';
                $isOffline = in_array($method, ['cashondelivery', 'checkmo', 'banktransfer', 'purchaseorder']);

                // For online payments (e.g. Razorpay), only send email if payment is verified
                if (!$isOffline) {
                    $state = $order->getState();
                    $status = $order->getStatus();
                    $isPaid = ($state === Order::STATE_PROCESSING || $state === Order::STATE_COMPLETE || (float)$order->getTotalPaid() > 0);

                    if (!$isPaid || $state === Order::STATE_PENDING_PAYMENT || $status === 'pending_payment') {
                        $this->logger->info("Vendor Marketplace: Order #{$order->getIncrementId()} has unconfirmed online payment ($method). Deferring vendor email until payment verification.");
                        return;
                    }
                }

                $this->logger->info("Vendor Marketplace: Sending new order email for Vendor Order #{$vendorOrder->getId()} (Order #{$order->getIncrementId()})");
                $this->vendorOrderSender->send($order, $vendorOrder);
            }
        } catch (\Exception $e) {
            $this->logger->error("Vendor Marketplace: Observer SendVendorNewOrderEmail Error: " . $e->getMessage());
        }
    }
}
