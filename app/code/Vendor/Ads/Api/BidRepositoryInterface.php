<?php
namespace Vendor\Ads\Api;

use Vendor\Ads\Api\Data\BidInterface;

interface BidRepositoryInterface
{
    public function save(BidInterface $bid);
    public function getById($bidId);
    public function delete(BidInterface $bid);
}
