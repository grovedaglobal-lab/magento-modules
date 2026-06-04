<?php
namespace Vendor\Ads\Model;

class SearchState
{
    /** @var array */
    protected $sponsoredProductBids = [];

    /**
     * Set sponsored product bids
     *
     * @param array $bids [productId => bidId]
     */
    public function setSponsoredProductBids(array $bids)
    {
        $this->sponsoredProductBids = $bids;
    }

    /**
     * Get bid ID for product
     *
     * @param int $productId
     * @return int|null
     */
    public function getBidId($productId)
    {
        return $this->sponsoredProductBids[$productId] ?? null;
    }

    /**
     * Check if product is sponsored
     */
    public function isSponsored($productId)
    {
        return isset($this->sponsoredProductBids[$productId]);
    }

    /**
     * Get all sponsored IDs
     *
     * @return array
     */
    public function getSponsoredProductIds()
    {
        return array_keys($this->sponsoredProductBids);
    }
}
