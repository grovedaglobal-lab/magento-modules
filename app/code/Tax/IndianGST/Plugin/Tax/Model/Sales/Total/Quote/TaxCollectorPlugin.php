<?php
namespace Tax\IndianGST\Plugin\Tax\Model\Sales\Total\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;

class TaxCollectorPlugin
{
    /**
     * Skip native tax calculation for Indian orders
     *
     * @param mixed $subject
     * @param \Closure $proceed
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return mixed
     */
    protected $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function aroundCollect(
        $subject,
        \Closure $proceed,
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        $address = $shippingAssignment->getShipping()->getAddress();
        $countryId = $address->getCountryId();
        $regionId = $address->getRegionId();
        $postcode = $address->getPostcode();

        $this->logger->info("TaxCollectorPlugin Check: Country='{$countryId}', RegionID='{$regionId}', Postcode='{$postcode}'");

        if ($countryId === 'IN') {
            // Clear standard tax fields to be safe
            $total->setTaxAmount(0);
            $total->setBaseTaxAmount(0);
            return $subject;
        }

        return $proceed($quote, $shippingAssignment, $total);
    }
}
