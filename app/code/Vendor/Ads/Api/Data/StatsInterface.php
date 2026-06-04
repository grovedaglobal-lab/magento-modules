<?php
namespace Vendor\Ads\Api\Data;

interface StatsInterface
{
    const STAT_ID = 'stat_id';
    const BID_ID = 'bid_id';
    const IMPRESSIONS = 'impressions';
    const CLICKS = 'clicks';
    const CONVERSIONS = 'conversions';
    const CTR = 'ctr';
    const DATE = 'date';

    public function getStatId();
    public function setStatId($statId);
    public function getBidId();
    public function setBidId($bidId);
    public function getImpressions();
    public function setImpressions($impressions);
    public function getClicks();
    public function setClicks($clicks);
    public function getConversions();
    public function setConversions($conversions);
    public function getCtr();
    public function setCtr($ctr);
    public function getDate();
    public function setDate($date);
}
