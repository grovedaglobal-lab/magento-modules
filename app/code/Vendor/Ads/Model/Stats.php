<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\StatsInterface;

class Stats extends AbstractModel implements StatsInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\Stats::class);
    }

    public function getStatId() { return $this->getData(self::STAT_ID); }
    public function setStatId($statId) { return $this->setData(self::STAT_ID, $statId); }
    public function getBidId() { return $this->getData(self::BID_ID); }
    public function setBidId($bidId) { return $this->setData(self::BID_ID, $bidId); }
    public function getImpressions() { return $this->getData(self::IMPRESSIONS); }
    public function setImpressions($impressions) { return $this->setData(self::IMPRESSIONS, $impressions); }
    public function getClicks() { return $this->getData(self::CLICKS); }
    public function setClicks($clicks) { return $this->setData(self::CLICKS, $clicks); }
    public function getConversions() { return $this->getData(self::CONVERSIONS); }
    public function setConversions($conversions) { return $this->setData(self::CONVERSIONS, $conversions); }
    public function getCtr() { return $this->getData(self::CTR); }
    public function setCtr($ctr) { return $this->setData(self::CTR, $ctr); }
    public function getDate() { return $this->getData(self::DATE); }
    public function setDate($date) { return $this->setData(self::DATE, $date); }
}
