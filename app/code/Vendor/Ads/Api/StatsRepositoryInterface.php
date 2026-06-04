<?php
namespace Vendor\Ads\Api;

interface StatsRepositoryInterface
{
    /**
     * Get consolidated stats for a vendor
     *
     * @param int $vendorId
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function getVendorStats($vendorId, $fromDate = null, $toDate = null);

    /**
     * Get stats for a specific bid
     *
     * @param int $bidId
     * @return array
     */
    public function getBidStats($bidId);
}
