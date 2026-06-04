<?php
namespace Vendor\Ads\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Ads\Api\Data\WalletInterface;

class Wallet extends AbstractModel implements WalletInterface
{
    protected function _construct()
    {
        $this->_init(\Vendor\Ads\Model\ResourceModel\Wallet::class);
    }

    public function getVendorId() { return $this->getData(self::VENDOR_ID); }
    public function setVendorId($vendorId) { return $this->setData(self::VENDOR_ID, $vendorId); }
    public function getBalance() { return $this->getData(self::BALANCE); }
    public function setBalance($balance) { return $this->setData(self::BALANCE, $balance); }
    public function getCurrency() { return $this->getData(self::CURRENCY); }
    public function setCurrency($currency) { return $this->setData(self::CURRENCY, $currency); }
    public function getUpdatedAt() { return $this->getData(self::UPDATED_AT); }
    public function setUpdatedAt($updatedAt) { return $this->setData(self::UPDATED_AT, $updatedAt); }
}
