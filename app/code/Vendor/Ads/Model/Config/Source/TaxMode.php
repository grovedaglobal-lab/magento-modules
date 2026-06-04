<?php
namespace Vendor\Ads\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TaxMode implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'exclusive', 'label' => __('Exclusive')],
            ['value' => 'inclusive', 'label' => __('Inclusive')],
        ];
    }
}
