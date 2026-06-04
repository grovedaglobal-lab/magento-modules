<?php
namespace Tax\IndianGST\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class QuoteSubmitBefore implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getOrder();
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $observer->getEvent()->getQuote();

            if (!$order || !$quote) {
                return;
            }

            $this->logger->info('IndianGST: QuoteSubmitBefore - Force Copy Start');

            foreach ($order->getAllItems() as $orderItem) {
                $quoteItemId = $orderItem->getQuoteItemId();
                if (!$quoteItemId) {
                    continue;
                }

                $quoteItem = $quote->getItemById($quoteItemId);
                if (!$quoteItem) {
                    continue;
                }

                $this->logger->info('Processing Item SKU: ' . $orderItem->getSku());

                // List of attributes to copy
                $attributes = [
                    'indiangst_cgst_amount',
                    'indiangst_sgst_amount',
                    'indiangst_igst_amount',
                    'indiangst_percent',
                    'indiangst_breakup'
                ];

                foreach ($attributes as $code) {
                    $val = $quoteItem->getData($code);
                    if ($val !== null) {
                        $orderItem->setData($code, $val);
                    }
                }
            }

            // Also copy Order-level totals if needed
            $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
            if ($address) {
                $order->setData('indiangst_amount', $address->getData('indiangst_amount'));
                $order->setData('indiangst_cgst', $address->getData('indiangst_cgst'));
                $order->setData('indiangst_sgst', $address->getData('indiangst_sgst'));
                $order->setData('indiangst_igst', $address->getData('indiangst_igst'));
            }

            $this->logger->info('IndianGST: QuoteSubmitBefore - Force Copy End');
        } catch (\Exception $e) {
            $this->logger->critical('IndianGST: QuoteSubmitBefore Error: ' . $e->getMessage());
        }
    }
}
