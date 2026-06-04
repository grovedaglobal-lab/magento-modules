<?php
namespace Vendor\Marketplace\Model\Commission;

class Calculator
{
    /**
     * Calculate commission
     *
     * @param float $subtotal
     * @param int $vendorId
     * @return float
     */
    public function calculate($subtotal, $vendorId)
    {
        // 1. Check specific vendor rule
        // 2. Check category rule
        // 3. Fallback to global config

        $globalPercent = 10; // Example: 10%

        return ($subtotal * $globalPercent) / 100;
    }
}
