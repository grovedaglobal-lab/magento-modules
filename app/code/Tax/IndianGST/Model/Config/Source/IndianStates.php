<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IndianStates implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'DL', 'label' => __('Delhi')],
            ['value' => 'MH', 'label' => __('Maharashtra')],
            ['value' => 'KA', 'label' => __('Karnataka')],
            ['value' => 'TN', 'label' => __('Tamil Nadu')],
            ['value' => 'UP', 'label' => __('Uttar Pradesh')],
            ['value' => 'GJ', 'label' => __('Gujarat')],
            ['value' => 'WB', 'label' => __('West Bengal')],
            // Add other states as needed
        ];
    }
}
