<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductTypes implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'simple', 'label' => __('Simple Product')],
            ['value' => 'configurable', 'label' => __('Configurable Product')],
            ['value' => 'virtual', 'label' => __('Virtual Product')],
            ['value' => 'grouped', 'label' => __('Grouped Product')],
            ['value' => 'bundle', 'label' => __('Bundle Product')],
            ['value' => 'downloadable', 'label' => __('Downloadable Product')]
        ];
    }
}
