<?php
namespace Vendor\Marketplace\Plugin\Shipping;

class HideFreeShippingPlugin
{
    /**
     * Hide Free Shipping if Vendor Shipping is present
     *
     * @param \Magento\Shipping\Model\Shipping $subject
     * @param \Magento\Shipping\Model\Shipping $result
     * @return \Magento\Shipping\Model\Shipping
     */
    public function afterCollectRates(
        \Magento\Shipping\Model\Shipping $subject,
        $result
    ) {
        $rateResult = $subject->getResult();

        if ($rateResult) {
            $rates = $rateResult->getAllRates();
            $hasVendorShipping = false;

            // Check if vendor shipping exists in the collected rates
            foreach ($rates as $rate) {
                if ($rate->getCarrier() == 'vendorshipping') {
                    $hasVendorShipping = true;
                    break;
                }
            }

            // If vendor shipping is present, remove free shipping
            if ($hasVendorShipping) {
                $rateResult->reset();
                foreach ($rates as $rate) {
                    if ($rate->getCarrier() !== 'freeshipping') {
                        $rateResult->append($rate);
                    }
                }
            }
        }

        return $result;
    }
}
