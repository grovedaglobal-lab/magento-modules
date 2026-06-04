<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Total\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;

class NullCollector extends AbstractTotal
{
    /**
     * Do nothing
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        // Explicitly clear native tax amounts to prevent interference
        $total->setTaxAmount(0);
        $total->setBaseTaxAmount(0);
        $total->setShippingTaxAmount(0);
        $total->setBaseShippingTaxAmount(0);
        $total->setExtraTaxAmount(0);
        $total->setBaseExtraTaxAmount(0);

        $address = $shippingAssignment->getShipping()->getAddress();
        $address->setTaxAmount(0);
        $address->setBaseTaxAmount(0);

        // CRITICAL: Ensure this collector (tax, tax_subtotal, etc.) explicitly registers 0
        $total->setTotalAmount($this->getCode(), 0);
        $total->setBaseTotalAmount($this->getCode(), 0);

        return $this;
    }

    /**
     * Do nothing
     *
     * @param Quote $quote
     * @param Total $total
     * @return array|null
     */
    public function fetch(Quote $quote, Total $total)
    {
        return null;
    }
}
