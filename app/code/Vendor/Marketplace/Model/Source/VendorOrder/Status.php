<?php
namespace Vendor\Marketplace\Model\Source\VendorOrder;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'pending', 'label' => __('Pending')],
            ['value' => 'processing', 'label' => __('Processing')],
            ['value' => 'complete', 'label' => __('Complete')],
            ['value' => 'canceled', 'label' => __('Canceled')]
        ];
    }
}
