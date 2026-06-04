<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\CampaignInterface;

class Campaign extends AbstractModel implements CampaignInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\Campaign::class);
    }

    public function getCampaignId() { return $this->getData(self::CAMPAIGN_ID); }
    public function setCampaignId($id) { return $this->setData(self::CAMPAIGN_ID, $id); }
    public function getVendorId() { return $this->getData(self::VENDOR_ID); }
    public function setVendorId($vendorId) { return $this->setData(self::VENDOR_ID, $vendorId); }
    public function getName() { return $this->getData(self::NAME); }
    public function setName($name) { return $this->setData(self::NAME, $name); }
    public function getDailyBudget() { return $this->getData(self::DAILY_BUDGET); }
    public function setDailyBudget($budget) { return $this->setData(self::DAILY_BUDGET, $budget); }
    public function getTotalBudget() { return $this->getData(self::TOTAL_BUDGET); }
    public function setTotalBudget($budget) { return $this->setData(self::TOTAL_BUDGET, $budget); }
    public function getStatus() { return $this->getData(self::STATUS); }
    public function setStatus($status) { return $this->setData(self::STATUS, $status); }
    public function getCreatedAt() { return $this->getData(self::CREATED_AT); }
}
