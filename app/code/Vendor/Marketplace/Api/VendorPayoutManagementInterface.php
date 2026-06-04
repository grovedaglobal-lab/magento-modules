<?php
namespace Vendor\Marketplace\Api;

interface VendorPayoutManagementInterface
{
    /**
     * Get Total Earnings for Vendor
     * @return float
     */
    public function getEarnings();

    /**
     * Request a Payout
     * @param float $amount
     * @return \Vendor\Marketplace\Api\Data\VendorPayoutInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestPayout($amount);
}
