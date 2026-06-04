<?php
namespace Vendor\Marketplace\Model\Source\News;

use Magento\Framework\Data\OptionSourceInterface;

class TargetType implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Vendor\Marketplace\Model\News::TARGET_ALL, 'label' => __('All Vendors')],
            ['value' => \Vendor\Marketplace\Model\News::TARGET_SPECIFIC, 'label' => __('Specific Vendors')]
        ];
    }
}
