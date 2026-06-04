<?php
namespace Tax\IndianGST\Block\Order\Invoice\Totals;

use Magento\Framework\View\Element\Template;
use Magento\Framework\DataObject;

class Gst extends Template
{
    /**
     * @var \Tax\IndianGST\Model\Calculator\GstCalculator
     */
    protected $gstCalculator;

    /**
     * @param Template\Context $context
     * @param \Tax\IndianGST\Model\Calculator\GstCalculator $gstCalculator
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Tax\IndianGST\Model\Calculator\GstCalculator $gstCalculator,
        array $data = []
    ) {
        $this->gstCalculator = $gstCalculator;
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource(); // The Invoice object

        if (!$source) {
            return $this;
        }

        $cgst = (float) $source->getData('indiangst_cgst');
        $sgst = (float) $source->getData('indiangst_sgst');
        $igst = (float) $source->getData('indiangst_igst');

        // Fallback: Calculate from items if header is 0
        if ($cgst == 0 && $sgst == 0 && $igst == 0) {
            foreach ($source->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                $cgst += (float) $item->getData('indiangst_cgst_amount');
                $sgst += (float) $item->getData('indiangst_sgst_amount');
                $igst += (float) $item->getData('indiangst_igst_amount');
            }
        }

        $isInclusive = $this->gstCalculator->isInclusivePricing();
        $suffix = $isInclusive ? __('(Incl)') : __('(Excl)');

        if ($cgst > 0.001) {
            $parent->addTotal(
                new DataObject([
                    'code' => 'indiangst_cgst',
                    'value' => $cgst,
                    'base_value' => $cgst,
                    'label' => __('CGST') . ' ' . $suffix,
                ]),
                'shipping'
            );
        }

        if ($sgst > 0.001) {
            $parent->addTotal(
                new DataObject([
                    'code' => 'indiangst_sgst',
                    'value' => $sgst,
                    'base_value' => $sgst,
                    'label' => __('SGST') . ' ' . $suffix
                ]),
                'indiangst_cgst'
            );
        }

        if ($igst > 0.001) {
            $parent->addTotal(
                new DataObject([
                    'code' => 'indiangst_igst',
                    'value' => $igst,
                    'base_value' => $igst,
                    'label' => __('IGST') . ' ' . $suffix
                ]),
                'shipping'
            );
        }

        // Correct Grand Total if needed
        $totals = $parent->getTotals();
        if (isset($totals['grand_total'])) {
            $currentGrandTotal = $totals['grand_total']->getValue();
            $calculatedTotalTax = $cgst + $sgst + $igst;

            // Heuristic: If GT is roughly (Subtotal + Shipping) and we have tax, add tax
            // Or simpler: If existing tax amount in invoice is 0 but we found calculated tax, add it.
            if ((float) $source->getTaxAmount() == 0 && $calculatedTotalTax > 0) {
                $totals['grand_total']->setValue($currentGrandTotal + $calculatedTotalTax);
                $totals['grand_total']->setBaseValue($currentGrandTotal + $calculatedTotalTax); // Assuming base currency same for simplicity or proportional
            }
        }

        return $this;
    }
}
