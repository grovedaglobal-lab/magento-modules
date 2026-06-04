<?php
declare(strict_types=1);

namespace Tax\IndianGST\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Tax\IndianGST\Model\OrderBreakupFactory;
use Tax\IndianGST\Model\ResourceModel\OrderBreakup as OrderBreakupResource;

class SaveOrderGstDetails implements ObserverInterface
{
    /**
     * @var OrderBreakupFactory
     */
    protected $orderBreakupFactory;

    /**
     * @var OrderBreakupResource
     */
    protected $orderBreakupResource;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        OrderBreakupFactory $orderBreakupFactory,
        OrderBreakupResource $orderBreakupResource,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->orderBreakupFactory = $orderBreakupFactory;
        $this->orderBreakupResource = $orderBreakupResource;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $quote = $observer->getEvent()->getQuote();

            if (!$order || !$order->getId()) {
                return;
            }

            foreach ($order->getAllItems() as $orderItem) {
                if ($orderItem->getParentItem()) {
                    continue;
                }

                $gstJson = $orderItem->getData('indiangst_breakup');

                if (!$gstJson && $quote) {
                    $quoteItem = $quote->getItemById($orderItem->getQuoteItemId());
                    if ($quoteItem) {
                        $gstJson = $quoteItem->getData('indiangst_breakup');
                    }
                }

                if ($gstJson) {
                    $gstData = json_decode($gstJson, true);
                    if (json_last_error() === JSON_ERROR_NONE && $orderItem->getId()) {
                        $breakupModel = $this->orderBreakupFactory->create();
                        $breakupModel->setData([
                            'order_id' => $order->getId(),
                            'order_item_id' => $orderItem->getId(),
                            'vendor_id' => $gstData['vendor_id'] ?? null,
                            'hsn_code' => $gstData['hsn'] ?? null,
                            'tax_type' => $gstData['type'] ?? null,
                            'taxable_amount' => $orderItem->getRowTotal(),
                            'cgst_amount' => $gstData['cgst_amount'] ?? 0,
                            'sgst_amount' => $gstData['sgst_amount'] ?? 0,
                            'igst_amount' => $gstData['igst_amount'] ?? 0,
                            'total_tax_amount' => $gstData['total_tax'] ?? 0
                        ]);
                        $this->orderBreakupResource->save($breakupModel);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently log and don't block order placement
            if (isset($this->logger)) {
                $this->logger->critical('IndianGST SaveOrderGstDetails Error: ' . $e->getMessage());
            }
        }
    }
}
