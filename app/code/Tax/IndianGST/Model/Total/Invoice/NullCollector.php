<?php
namespace Tax\IndianGST\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class NullCollector extends AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $invoice->setTaxAmount(0);
        $invoice->setBaseTaxAmount(0);
        return $this;
    }
}
