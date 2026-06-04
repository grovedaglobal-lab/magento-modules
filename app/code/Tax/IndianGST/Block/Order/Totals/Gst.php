<?php
namespace Tax\IndianGST\Block\Order\Totals;

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
        $source = $parent->getSource(); // The Order object

        if (!$source) {
            return $this;
        }

        if ($source instanceof \Magento\Quote\Model\Quote) {
            $address = $source->isVirtual() ? $source->getBillingAddress() : $source->getShippingAddress();
            $cgst = (float) $address->getData('indiangst_cgst');
            $sgst = (float) $address->getData('indiangst_sgst');
            $igst = (float) $address->getData('indiangst_igst');
        } else {
            // For Order Object
            $cgst = (float) $source->getData('indiangst_cgst');
            $sgst = (float) $source->getData('indiangst_sgst');
            $igst = (float) $source->getData('indiangst_igst');

            // Fallback: Calculate from items if header is 0
            if ($cgst == 0 && $sgst == 0 && $igst == 0) {
                foreach ($source->getAllItems() as $item) {
                    if ($item->getParentItem()) {
                        continue;
                    }
                    $cgst += (float) $item->getData('indiangst_cgst_amount');
                    $sgst += (float) $item->getData('indiangst_sgst_amount');
                    $igst += (float) $item->getData('indiangst_igst_amount');
                }
            }

            // Fallback Level 2: Check Invoices if Order Items are also 0
            if ($cgst == 0 && $sgst == 0 && $igst == 0 && method_exists($source, 'getInvoiceCollection')) {
                foreach ($source->getInvoiceCollection() as $invoice) {
                    // Check invoice header
                    $invCgst = (float) $invoice->getData('indiangst_cgst');
                    $invSgst = (float) $invoice->getData('indiangst_sgst');
                    $invIgst = (float) $invoice->getData('indiangst_igst');

                    // Or invoice items if header missing
                    if ($invCgst == 0 && $invSgst == 0 && $invIgst == 0) {
                        foreach ($invoice->getAllItems() as $invItem) {
                            $invCgst += (float) $invItem->getData('indiangst_cgst_amount');
                            $invSgst += (float) $invItem->getData('indiangst_sgst_amount');
                            $invIgst += (float) $invItem->getData('indiangst_igst_amount');
                        }
                    }

                    $cgst += $invCgst;
                    $sgst += $invSgst;
                    $igst += $invIgst;
                }
            }
        }

        $isInclusive = $this->gstCalculator->isDisplayInclusive();
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

        // Grand Total Correction removed to prevent double-taxation on inclusive prices

        return $this;
    }
}
