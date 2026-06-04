<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ShippingSource implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'self_ship', 'label' => __('Self Ship (Vendor Manage Rates)')],
            ['value' => 'easy_ship', 'label' => __('Easy Ship (Admin Managed)')]
        ];
    }
}
