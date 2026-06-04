<?php
namespace Vendor\Ads\Api\Data;

interface BidInterface
{
    const BID_ID = 'bid_id';
    const VENDOR_ID = 'vendor_id';
    const PRODUCT_ID = 'product_id';
    const QUERY_ID = 'query_id';
    const BID_AMOUNT = 'bid_amount';
    const MATCH_TYPE = 'match_type';
    const DAILY_BUDGET = 'daily_budget';
    const TOTAL_BUDGET = 'total_budget';
    const SPENT_AMOUNT = 'spent_amount';
    const START_DATE = 'start_date';
    const END_DATE = 'end_date';
    const IS_ACTIVE = 'is_active';
    const LAST_CHARGED_AT = 'last_charged_at';
    const CREATED_AT = 'created_at';

    public function getBidId();
    public function setBidId($bidId);
    public function getVendorId();
    public function setVendorId($vendorId);
    public function getProductId();
    public function setProductId($productId);
    public function getQueryId();
    public function setQueryId($queryId);
    public function getBidAmount();
    public function setBidAmount($bidAmount);
    public function getMatchType();
    public function setMatchType($matchType);
    public function getDailyBudget();
    public function setDailyBudget($dailyBudget);
    public function getTotalBudget();
    public function setTotalBudget($totalBudget);
    public function getSpentAmount();
    public function setSpentAmount($spentAmount);
    public function getStartDate();
    public function setStartDate($startDate);
    public function getEndDate();
    public function setEndDate($endDate);
    public function getIsActive();
    public function setIsActive($isActive);
    public function getLastChargedAt();
    public function setLastChargedAt($lastChargedAt);
    public function getCreatedAt();
    public function setCreatedAt($createdAt);
}
