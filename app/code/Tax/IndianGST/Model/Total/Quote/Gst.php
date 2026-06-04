<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Total\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Tax\IndianGST\Model\Calculator\GstCalculator;

class Gst extends AbstractTotal
{
    /**
     * @var GstCalculator
     */
    protected $gstCalculator;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        GstCalculator $gstCalculator,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->setCode('tax_indiangst');
        $this->gstCalculator = $gstCalculator;
        $this->logger = $logger;
    }

    /**
     * Collect GST totals
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
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        $address = $shippingAssignment->getShipping()->getAddress();

        // CRITICAL FIX: Only run for India. Return early for others to preserve native Tax.
        if (!$address || $address->getCountryId() !== 'IN') {
            return $this;
        }

        $totalTaxAmount = 0;
        $baseTotalTaxAmount = 0;
        $shippingTax = 0;
        $baseShippingTax = 0;

        $cgstTotal = 0;
        $sgstTotal = 0;
        $igstTotal = 0;

        // Reset address tax data
        $address->setData('indiangst_cgst', 0);
        $address->setData('indiangst_sgst', 0);
        $address->setData('indiangst_igst', 0);
        $address->setData('indiangst_amount', 0);

        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            // Calculate tax for this item
            $taxDetails = $this->gstCalculator->calculateItemTax($item, $address);

            // Defaults (Safety initialization)
            $itemTax = 0;
            $baseItemTax = 0;
            $rate = 0;

            if (!empty($taxDetails)) {
                $itemTax = $taxDetails['total_tax'];
                $baseItemTax = $itemTax;
                $rate = $taxDetails['rate'];

                // Add to totals
                $totalTaxAmount += $itemTax;
                $baseTotalTaxAmount += $baseItemTax;

                // Save details to item for later use
                if (isset($taxDetails['cgst_amount']))
                    $cgstTotal += $taxDetails['cgst_amount'];
                if (isset($taxDetails['sgst_amount']))
                    $sgstTotal += $taxDetails['sgst_amount'];
                if (isset($taxDetails['igst_amount']))
                    $igstTotal += $taxDetails['igst_amount'];
            }

            // Store details on item
            $item->setTaxAmount($itemTax);
            $item->setBaseTaxAmount($baseItemTax);
            $item->setTaxPercent($rate);

            // BREAKDOWN: Set explicitly for persistence in DB (Quote/Order Item)
            if (!empty($taxDetails)) {
                $item->setData('indiangst_cgst_amount', $taxDetails['cgst_amount'] ?? 0);
                $item->setData('indiangst_sgst_amount', $taxDetails['sgst_amount'] ?? 0);
                $item->setData('indiangst_igst_amount', $taxDetails['igst_amount'] ?? 0);
                $item->setData('indiangst_percent', $rate);
                $item->setData('indiangst_breakup', json_encode($taxDetails));
            }

            // Populate Inclusive/Exclusive fields (Required because Native Collector is disabled)
            $isInclusive = $this->gstCalculator->isInclusivePricing();
            $qty = $item->getQty() ?: 1;

            if ($isInclusive) {
                // If Inclusive Config: DB Price is Gross.
                // We must strip tax to get Net Price.

                // 1. Set Gross (Incl) Fields = Original DB Values
                $item->setPriceInclTax($item->getPrice());
                $item->setBasePriceInclTax($item->getBasePrice());
                $item->setRowTotalInclTax($item->getRowTotal());
                $item->setBaseRowTotalInclTax($item->getBaseRowTotal());

                // 2. Adjust Net (Excl) Fields
                $netPrice = $item->getPrice() - ($itemTax / $qty);
                $netBasePrice = $item->getBasePrice() - ($baseItemTax / $qty);
                $netRowTotal = $item->getRowTotal() - $itemTax;
                $netBaseRowTotal = $item->getBaseRowTotal() - $baseItemTax;

                $item->setPrice($netPrice);
                $item->setBasePrice($netBasePrice);
                $item->setRowTotal($netRowTotal);
                $item->setBaseRowTotal($netBaseRowTotal);
            } else {
                // If Exclusive Config: DB Price is Net.
                // We must add tax to get Gross Price.

                $item->setPriceInclTax($item->getPrice() + ($itemTax / $qty));
                $item->setBasePriceInclTax($item->getBasePrice() + ($baseItemTax / $qty));

                $item->setRowTotalInclTax($item->getRowTotal() + $itemTax);
                $item->setBaseRowTotalInclTax($item->getBaseRowTotal() + $baseItemTax);
            }

            // SAFETY NET: Ensure Incl Price is not 0 (unless Price is 0)
            if ($item->getPrice() > 0 && $item->getPriceInclTax() <= 0) {
                $item->setPriceInclTax($item->getPrice());
                $item->setBasePriceInclTax($item->getBasePrice());
                $item->setRowTotalInclTax($item->getRowTotal());
                $item->setBaseRowTotalInclTax($item->getBaseRowTotal());
            }

            // Logging for Debug
            $this->logger->info(
                "[IndianGST] Item: " . $item->getSku() .
                " | Price: " . $item->getPrice() .
                " | Tax: " . $itemTax .
                " | Incl: " . $item->getPriceInclTax()
            );

            // Custom attribute for reference
            $item->setData('indiangst_tax_amount', $itemTax);
        }

        // --- SECTION START: SHIPPING TAX HANDLING (Explicit 0 Tax as requested) ---
        $shippingAmount = $address->getShippingAmount();
        $baseShippingAmount = $address->getBaseShippingAmount();

        // Ensure Shipping Inclusive is set (since native collector might be disabled/overridden)
        // With 0 Tax, Incl = Amount.
        $address->setShippingInclTax($shippingAmount);
        $address->setBaseShippingInclTax($baseShippingAmount);

        $total->setShippingInclTax($shippingAmount);
        $total->setBaseShippingInclTax($baseShippingAmount);

        $address->setShippingTaxAmount(0);
        $address->setBaseShippingTaxAmount(0);
        $total->setShippingTaxAmount(0);
        $total->setBaseShippingTaxAmount(0);
        // --- SECTION END: SHIPPING TAX HANDLING ---

        // Save totals to address for Checkout Summary Block
        $address->setData('indiangst_cgst', $cgstTotal);
        $address->setData('indiangst_sgst', $sgstTotal);
        $address->setData('indiangst_igst', $igstTotal);
        $address->setData('indiangst_amount', $totalTaxAmount);

        // Add to grand total ONLY if Exclusive
        $isInclusive = $this->gstCalculator->isInclusivePricing();

        // $this->logger->info("[IndianGST] Collect Start. Is Inclusive Pricing (Config)? " . ($isInclusive ? 'YES' : 'NO'));
        // $this->logger->info("[IndianGST] Subtotal BEFORE: " . $total->getSubtotal());
        // $this->logger->info("[IndianGST] Calculated Tax: " . $totalTaxAmount);

        if ($isInclusive) {
            // Logic for Inclusive:
            // We MUST reduce Subtotal to Net so that Net Subtotal + Tax + Shipping = Grand Total
            // Note: totalTaxAmount includes Shipping Tax (which is 0 now), but Subtotal ONLY includes Item Tax.
            // So we must subtract (TotalTax - ShippingTax) from Subtotal.
            $netSubtotal = $total->getSubtotal() - ($totalTaxAmount - $shippingTax);
            $netBaseSubtotal = $total->getBaseSubtotal() - ($baseTotalTaxAmount - $baseShippingTax);

            $this->logger->info("[IndianGST] Adjusting Subtotal for Inclusive Display. New Net Subtotal: " . $netSubtotal);

            // Adjust subtotal to net value (price minus tax)
            $total->setSubtotal($netSubtotal);
            $total->setBaseSubtotal($netBaseSubtotal);

            // ALSO update the Address object, as GrandTotal collector often reads from here
            $address->setSubtotal($netSubtotal);
            $address->setBaseSubtotal($netBaseSubtotal);
            $address->setTotalAmount('subtotal', $netSubtotal);
            $address->setBaseTotalAmount('subtotal', $netBaseSubtotal);

            // FIX: Explicitly update 'subtotal' in the Total object's amounts array
            // detailed: GrandTotal collector sums up values from getAllTotalAmounts()
            $total->setTotalAmount('subtotal', $netSubtotal);
            $total->setBaseTotalAmount('subtotal', $netBaseSubtotal);

            // Set explicit Incl/Excl values for display
            $total->setSubtotalExclTax($netSubtotal);
            $total->setSubtotalInclTax($total->getSubtotal() + $totalTaxAmount);
            $total->setBaseSubtotalExclTax($netBaseSubtotal);
            $total->setBaseSubtotalInclTax($netBaseSubtotal + $baseTotalTaxAmount);
        } else {
            $total->setSubtotalExclTax($total->getSubtotal());
            $total->setSubtotalInclTax($total->getSubtotal() + $totalTaxAmount);
        }

        // CRITICAL: Zero out native tax to prevent double-addition to Grand Total
        $total->setTaxAmount(0);
        $total->setBaseTaxAmount(0);
        $address->setTaxAmount(0);
        $address->setBaseTaxAmount(0);

        // FIX: Explicitly remove from totals array so GrandTotal collector ignores it
        $total->setTotalAmount('tax', 0);
        $total->setBaseTotalAmount('tax', 0);
        $address->setTotalAmount('tax', 0);
        $address->setBaseTotalAmount('tax', 0);

        // Store data for TotalsConverterPlugin to pick up
        // Use isDisplayInclusive for label (respects tax/display/type config)
        $isDisplayInclusive = $this->gstCalculator->isDisplayInclusive();

        $gstData = [
            'cgst' => $cgstTotal,
            'sgst' => $sgstTotal,
            'igst' => $igstTotal,
            'is_inclusive' => $isDisplayInclusive ? 1 : 0
        ];

        // $total->setData('extension_attributes', $gstData); // DO NOT SET ARRAY TO EXTENSION ATTRIBUTES - CAUSES CRASH
        $total->setData('indiangst_temp_data', $gstData);
        $address->setData('indiangst_temp_data', $gstData);

        $this->logger->info("[IndianGST] Set Temp Data on Total Object: ", $gstData);

        // Register our GST segment
        $total->setTotalAmount($this->getCode(), $totalTaxAmount);
        $total->setBaseTotalAmount($this->getCode(), $baseTotalTaxAmount);

        $this->logger->info("[IndianGST] Set Total Amount: " . $total->getTotalAmount($this->getCode()));
        $this->logger->info("[IndianGST] --------------------------------------------------");

        return $this;
    }

    /**
     * Assign tax breakdown to address for display
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        // Read directly from address data because $total passed here is a fresh object in getTotals()
        $address = $quote->getShippingAddress();
        $amount = $address->getData('indiangst_amount');
        $gstData = $address->getData('indiangst_temp_data');

        // Fallback: If amount is missing in address data (e.g. fresh load), try to get from total if populated
        if ($amount === null) {
            $amount = $total->getTotalAmount($this->getCode());
        }

        // CRITICAL FIX: Fallback for gstData if missing in address but present in Total (or vice versa)
        if (!$gstData) {
            $gstData = $total->getData('indiangst_temp_data');
        }

        if ($amount != 0) {
            return [
                'code' => $this->getCode(),
                'title' => __('Indian GST'),
                'value' => $amount,
                'indiangst_temp_data' => $gstData, // Pass data to TotalsConverter
                // Also pass 'extension_attributes' key directly just in case native logic picks it up
                'extension_attributes' => $gstData
            ];
        }
        return [];
    }

    /**
     * Get Subtotal label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Indian GST');
    }
}
