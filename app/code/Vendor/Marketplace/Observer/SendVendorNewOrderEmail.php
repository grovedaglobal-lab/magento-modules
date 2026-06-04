<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\Order\Email\Sender\VendorOrderSender;
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
                $this->logger->info("Vendor Marketplace: Sending new order email for Vendor Order #{$vendorOrder->getId()}");
                $this->vendorOrderSender->send($order, $vendorOrder);
            }
        } catch (\Exception $e) {
            $this->logger->error("Vendor Marketplace: Observer SendVendorNewOrderEmail Error: " . $e->getMessage());
        }
    }
}
