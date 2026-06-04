<?php
namespace Tax\IndianGST\Block\Adminhtml\Order\Totals;

use Magento\Framework\View\Element\Template;
use Magento\Framework\DataObject;

class Gst extends Template
{
    /**
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Initialize all totals using the parent block
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource(); // The Order object

        if (!$source) {
            return $this;
        }

        $cgst = (float)$source->getData('indiangst_cgst');
        $sgst = (float)$source->getData('indiangst_sgst');
        $igst = (float)$source->getData('indiangst_igst');

        if ($cgst > 0.001) {
            $parent->addTotal(
                new DataObject([
                    'code' => 'indiangst_cgst',
                    'value' => $cgst,
                    'base_value' => $cgst,
                    'label' => __('CGST'),
                ]),
                'subtotal'
            );
        }
        
        if ($sgst > 0.001) {
            $parent->addTotal(
                new DataObject([
                    'code' => 'indiangst_sgst',
                    'value' => $sgst,
                    'base_value' => $sgst,
                    'label' => __('SGST')
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
                    'label' => __('IGST')
                ]),
                'subtotal'
            );
        }

        return $this;
    }
}
