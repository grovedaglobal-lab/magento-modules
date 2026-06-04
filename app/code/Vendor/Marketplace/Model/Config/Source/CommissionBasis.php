<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CommissionBasis implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'exclude_tax', 'label' => __('Row Total (Excluding Tax)')],
            ['value' => 'include_tax', 'label' => __('Row Total (Including Tax)')]
        ];
    }
}
