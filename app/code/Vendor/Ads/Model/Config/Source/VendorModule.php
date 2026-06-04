<?php
namespace Vendor\Ads\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class VendorModule implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'custom_marketplace',
                'label' => __('Custom Marketplace (vendor_entity — default)')
            ],
            [
                'value' => 'webkul',
                'label' => __('Webkul Marketplace (marketplace_userdata)')
            ],
            [
                'value' => 'cedcommerce',
                'label' => __('CedCommerce Marketplace (ced_csmarketplace_vendor)')
            ],
            [
                'value' => 'appjetty',
                'label' => __('Appjetty Marketplace (aj_marketplace_seller)')
            ],
            [
                'value' => 'custom',
                'label' => __('Custom / Other (configure table manually below)')
            ],
        ];
    }
}
