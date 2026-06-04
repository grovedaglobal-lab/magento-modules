<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AllowedShippingSources implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'self', 'label' => __('Self Ship Only')],
            ['value' => 'easy', 'label' => __('Easy Ship Only')],
            ['value' => 'both', 'label' => __('Enable Both (Vendor Choice)')],
            ['value' => 'none', 'label' => __('Disable Shipping')]
        ];
    }
}
