<?php
namespace Tax\IndianGST\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tax\IndianGST\Model\Service\GSTCalculator;
use Tax\IndianGST\Model\ResourceModel\OrderBreakup; // Assume model exists
use Tax\IndianGST\Model\OrderBreakupFactory;

class SaveOrderGstBreakup implements ObserverInterface
{
    protected $gstCalculator;
    protected $orderBreakupFactory;

    public function __construct(
        GSTCalculator $gstCalculator,
        OrderBreakupFactory $orderBreakupFactory
    ) {
        $this->gstCalculator = $gstCalculator;
        $this->orderBreakupFactory = $orderBreakupFactory;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $shippingAddress = $order->getShippingAddress();

        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            // Calculate GST
            // Note: We need to convert Order Item to Quote Item logic or reuse logic.
            // Simplified here: We just use the calculator with the order item data assuming compatibility or adapter.
            $taxDetails = $this->gstCalculator->calculateGST($item, $shippingAddress);

            // Save to Database
            $breakup = $this->orderBreakupFactory->create();
            $breakup->setData([
                'order_id' => $order->getId(),
                'order_item_id' => $item->getId(),
                'tax_type' => $taxDetails['type'],
                'cgst_amount' => $taxDetails['cgst_amount'],
                'sgst_amount' => $taxDetails['sgst_amount'],
                'igst_amount' => $taxDetails['igst_amount'],
                'total_tax_amount' => $taxDetails['total_tax'],
                'vendor_id' => null // Logic to fetch vendor ID again or store it on item
            ]);
            $breakup->save();
        }
    }
}
