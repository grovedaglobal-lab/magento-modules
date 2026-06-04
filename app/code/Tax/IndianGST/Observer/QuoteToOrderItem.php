<?php
namespace Tax\IndianGST\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class QuoteToOrderItem implements ObserverInterface
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
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getEvent()->getItem();
        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        $orderItem = $observer->getEvent()->getOrderItem();

        $this->logger->info('IndianGST Force Copy: Processing SKU ' . $quoteItem->getSku());

        // List of fields to copy
        $keys = [
            'indiangst_cgst_amount',
            'indiangst_sgst_amount',
            'indiangst_igst_amount',
            'indiangst_percent',
            'indiangst_breakup'
        ];

        foreach ($keys as $key) {
            $value = $quoteItem->getData($key);
            $this->logger->info("  $key: " . ($value ?? 'NULL'));
            if ($value !== null) {
                $orderItem->setData($key, $value);
            }
        }
    }
}
