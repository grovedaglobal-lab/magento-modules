<?php
namespace Tax\IndianGST\Plugin\Sales\Model\Order\Invoice\Total;

class TaxPlugin
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice\Total\Tax $subject
     * @param \Closure $proceed
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return mixed
     */
    public function aroundCollect(
        \Magento\Sales\Model\Order\Invoice\Total\Tax $subject,
        \Closure $proceed,
        \Magento\Sales\Model\Order\Invoice $invoice
    ) {
        $order = $invoice->getOrder();
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress && $shippingAddress->getCountryId() === 'IN') {
            // Standard tax is replaced by IndianGST for India
            return $subject;
        }

        return $proceed($invoice);
    }
}
