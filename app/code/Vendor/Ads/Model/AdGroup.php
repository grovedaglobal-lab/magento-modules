<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\AdGroupInterface;

class AdGroup extends AbstractModel implements AdGroupInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\AdGroup::class);
    }

    public function getGroupId()    { return $this->getData(self::GROUP_ID); }
    public function setGroupId($v)  { return $this->setData(self::GROUP_ID, $v); }
    public function getVendorId()   { return $this->getData(self::VENDOR_ID); }
    public function setVendorId($v) { return $this->setData(self::VENDOR_ID, $v); }
    public function getName()       { return $this->getData(self::NAME); }
    public function setName($v)     { return $this->setData(self::NAME, $v); }
    public function getDailyBudget(){ return $this->getData(self::DAILY_BUDGET); }
    public function setDailyBudget($v){ return $this->setData(self::DAILY_BUDGET, $v); }
    public function getTotalBudget(){ return $this->getData(self::TOTAL_BUDGET); }
    public function setTotalBudget($v){ return $this->setData(self::TOTAL_BUDGET, $v); }
    public function getStatus()     { return $this->getData(self::STATUS); }
    public function setStatus($v)   { return $this->setData(self::STATUS, $v); }
    public function getCreatedAt()  { return $this->getData(self::CREATED_AT); }
}
