<?php
namespace Vendor\Ads\Plugin;

use Vendor\Marketplace\Block\Seller\Navigation;
use Vendor\Ads\Helper\Config as AdsConfig;

class NavigationPlugin
{
    protected $adsConfig;

    public function __construct(AdsConfig $adsConfig)
    {
        $this->adsConfig = $adsConfig;
    }

    /**
     * Append Ads menu items to the vendor seller sidebar
     */
    public function afterGetMenuItems(Navigation $subject, array $result): array
    {
        if (!$this->adsConfig->isEnabled()) {
            return $result;
        }

        // Insert Ads section after the last item (before end)
        $result['ads_manage'] = [
            'label' => __('My Advertisements'),
            'url'   => 'vendor_ads/vendor/index',
            'id'    => 'ads_manage',
            'icon'  => 'megaphone'
        ];

        $result['ads_report'] = [
            'label' => __('Ad Performance Report'),
            'url'   => 'vendor_ads/vendor/report',
            'id'    => 'ads_report',
            'icon'  => 'chart'
        ];

        $result['ads_wallet'] = [
            'label' => __('Ad Wallet Balance'),
            'url'   => 'vendor_ads/vendor/wallet',
            'id'    => 'ads_wallet',
            'icon'  => 'wallet'
        ];

        return $result;
    }
}
