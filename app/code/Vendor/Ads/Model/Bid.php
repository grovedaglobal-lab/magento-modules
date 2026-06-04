<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\BidInterface;

class Bid extends AbstractModel implements BidInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\Bid::class);
    }

    public function getBidId() { return $this->getData(self::BID_ID); }
    public function setBidId($bidId) { return $this->setData(self::BID_ID, $bidId); }
    public function getVendorId() { return $this->getData(self::VENDOR_ID); }
    public function setVendorId($vendorId) { return $this->setData(self::VENDOR_ID, $vendorId); }
    public function getProductId() { return $this->getData(self::PRODUCT_ID); }
    public function setProductId($productId) { return $this->setData(self::PRODUCT_ID, $productId); }
    public function getQueryId()
    {
        return $this->getData(self::QUERY_ID);
    }

    public function setQueryId($queryId)
    {
        return $this->setData(self::QUERY_ID, $queryId);
    }
    public function getBidAmount() { return $this->getData(self::BID_AMOUNT); }
    public function setBidAmount($bidAmount) { return $this->setData(self::BID_AMOUNT, $bidAmount); }
    public function getMatchType() { return $this->getData(self::MATCH_TYPE); }
    public function setMatchType($matchType) { return $this->setData(self::MATCH_TYPE, $matchType); }
    public function getDailyBudget() { return $this->getData(self::DAILY_BUDGET); }
    public function setDailyBudget($dailyBudget) { return $this->setData(self::DAILY_BUDGET, $dailyBudget); }
    public function getTotalBudget() { return $this->getData(self::TOTAL_BUDGET); }
    public function setTotalBudget($totalBudget) { return $this->setData(self::TOTAL_BUDGET, $totalBudget); }
    public function getSpentAmount() { return $this->getData(self::SPENT_AMOUNT); }
    public function setSpentAmount($spentAmount) { return $this->setData(self::SPENT_AMOUNT, $spentAmount); }
    public function getStartDate() { return $this->getData(self::START_DATE); }
    public function setStartDate($startDate) { return $this->setData(self::START_DATE, $startDate); }
    public function getEndDate() { return $this->getData(self::END_DATE); }
    public function setEndDate($endDate) { return $this->setData(self::END_DATE, $endDate); }
    public function getIsActive() { return $this->getData(self::IS_ACTIVE); }
    public function setIsActive($isActive) { return $this->setData(self::IS_ACTIVE, $isActive); }
    public function getLastChargedAt() { return $this->getData(self::LAST_CHARGED_AT); }
    public function setLastChargedAt($lastChargedAt) { return $this->setData(self::LAST_CHARGED_AT, $lastChargedAt); }
    public function getCreatedAt() { return $this->getData(self::CREATED_AT); }
    public function setCreatedAt($createdAt) { return $this->setData(self::CREATED_AT, $createdAt); }
}
