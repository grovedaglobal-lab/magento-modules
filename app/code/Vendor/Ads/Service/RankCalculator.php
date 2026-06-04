<?php
namespace Vendor\Ads\Service;

class RankCalculator
{
    /**
     * Ranking Formula: rank_score = (bid_amount * 0.6) + (popularity * 0.2) + (ctr * 0.2)
     * 
     * @param float $bidAmount
     * @param int $popularity
     * @param float $ctr
     * @return float
     */
    public function calculate($bidAmount, $popularity, $ctr)
    {
        // Normalize popularity (rough estimation: 1000 searches = 1.0)
        $normalizedPopularity = min(1.0, $popularity / 1000);
        
        // CTR is already 0.0 to 1.0
        // Bid amount can be normalized or used as raw weight (depending on typical range)
        // Assume bid amount is usually between 0 and 100 INR. Normalize to 1.0?
        $normalizedBid = min(1.0, $bidAmount / 100);

        return ($normalizedBid * 0.6) + ($normalizedPopularity * 0.2) + ($ctr * 0.2);
    }
}
