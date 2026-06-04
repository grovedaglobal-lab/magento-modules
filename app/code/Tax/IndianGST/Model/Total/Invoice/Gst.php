<?php
namespace Tax\IndianGST\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Gst extends AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress || $shippingAddress->getCountryId() !== 'IN') {
            return $this;
        }

        $invoice->setData('indiangst_amount', 0);
        $invoice->setData('indiangst_base_amount', 0);
        $invoice->setData('indiangst_cgst', 0);
        $invoice->setData('indiangst_sgst', 0);
        $invoice->setData('indiangst_igst', 0);

        $totalGst = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItem()) {
                continue;
            }

            // Calculate proportional GST for this invoice item
            // Since we stored the totals on the order item, we can use them
            $qItemCgst = (float) $orderItem->getData('indiangst_cgst_amount');
            $qItemSgst = (float) $orderItem->getData('indiangst_sgst_amount');
            $qItemIgst = (float) $orderItem->getData('indiangst_igst_amount');
            $qtyOrdered = $orderItem->getQtyOrdered();
            $qtyInvoiced = $item->getQty();

            if ($qtyOrdered > 0) {
                $ratio = $qtyInvoiced / $qtyOrdered;
                $itemCgst = $qItemCgst * $ratio;
                $itemSgst = $qItemSgst * $ratio;
                $itemIgst = $qItemIgst * $ratio;

                $totalCgst += $itemCgst;
                $totalSgst += $itemSgst;
                $totalIgst += $itemIgst;

                // Set on invoice item for persistence
                $item->setData('indiangst_cgst_amount', $itemCgst);
                $item->setData('indiangst_sgst_amount', $itemSgst);
                $item->setData('indiangst_igst_amount', $itemIgst);
                $item->setData('indiangst_percent', $orderItem->getData('indiangst_percent'));

                // Standard fields for UI
                $item->setTaxAmount($itemCgst + $itemSgst + $itemIgst);
                $item->setBaseTaxAmount($itemCgst + $itemSgst + $itemIgst);
            }
        }

        $totalGst = $totalCgst + $totalSgst + $totalIgst;

        $invoice->setData('indiangst_cgst', $totalCgst);
        $invoice->setData('indiangst_sgst', $totalSgst);
        $invoice->setData('indiangst_igst', $totalIgst);
        $invoice->setData('indiangst_amount', $totalGst);
        $invoice->setData('indiangst_base_amount', $totalGst);

        // Standard fields for UI
        $invoice->setTaxAmount($totalGst);
        $invoice->setBaseTaxAmount($totalGst);

        // LOGGING
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/indiangst_invoice.log');
        $logger = new \Zend_Log($writer);
        $logger->info("Invoice GST Collect: Total GST = $totalGst");
        $logger->info("Invoice Grand Total Before: " . $invoice->getGrandTotal());

        // Add to invoice grand total
        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalGst);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $totalGst);

        return $this;
    }
}
