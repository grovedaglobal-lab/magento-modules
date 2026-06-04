<?php
namespace Tax\IndianGST\Model\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

class Gst extends AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress || $shippingAddress->getCountryId() !== 'IN') {
            return $this;
        }

        $creditmemo->setData('indiangst_amount', 0);
        $creditmemo->setData('indiangst_base_amount', 0);
        $creditmemo->setData('indiangst_cgst', 0);
        $creditmemo->setData('indiangst_sgst', 0);
        $creditmemo->setData('indiangst_igst', 0);

        $totalGst = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItem()) {
                continue;
            }

            $qItemCgst = (float) $orderItem->getData('indiangst_cgst_amount');
            $qItemSgst = (float) $orderItem->getData('indiangst_sgst_amount');
            $qItemIgst = (float) $orderItem->getData('indiangst_igst_amount');
            $qtyOrdered = $orderItem->getQtyOrdered();
            $qtyRefunded = $item->getQty();

            if ($qtyOrdered > 0) {
                $ratio = $qtyRefunded / $qtyOrdered;
                $itemCgst = $qItemCgst * $ratio;
                $itemSgst = $qItemSgst * $ratio;
                $itemIgst = $qItemIgst * $ratio;

                $totalCgst += $itemCgst;
                $totalSgst += $itemSgst;
                $totalIgst += $itemIgst;

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

        $creditmemo->setData('indiangst_cgst', $totalCgst);
        $creditmemo->setData('indiangst_sgst', $totalSgst);
        $creditmemo->setData('indiangst_igst', $totalIgst);
        $creditmemo->setData('indiangst_amount', $totalGst);
        $creditmemo->setData('indiangst_base_amount', $totalGst);

        // Standard fields for UI
        $creditmemo->setTaxAmount($totalGst);
        $creditmemo->setBaseTaxAmount($totalGst);

        // Add to creditmemo grand total
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalGst);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $totalGst);

        return $this;
    }
}
