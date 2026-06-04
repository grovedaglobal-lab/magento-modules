<?php
namespace Tax\IndianGST\Plugin\Sales\Model\Order\Creditmemo\Total;

class TaxPlugin
{
    /**
     * @param \Magento\Sales\Model\Order\Creditmemo\Total\Tax $subject
     * @param \Closure $proceed
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return mixed
     */
    public function aroundCollect(
        \Magento\Sales\Model\Order\Creditmemo\Total\Tax $subject,
        \Closure $proceed,
        \Magento\Sales\Model\Order\Creditmemo $creditmemo
    ) {
        $order = $creditmemo->getOrder();
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress && $shippingAddress->getCountryId() === 'IN') {
            // Standard tax is replaced by IndianGST for India
            return $subject;
        }

        return $proceed($creditmemo);
    }
}
