<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Api\Data\VendorPayoutInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorPayout as ResourceVendorPayout;
use Magento\Framework\DataObject\IdentityInterface;

class VendorPayout extends AbstractModel implements VendorPayoutInterface, IdentityInterface
{
    const CACHE_TAG = 'vendor_marketplace_vendor_payout';
    protected $_cacheTag = 'vendor_marketplace_vendor_payout';
    protected $_eventPrefix = 'vendor_marketplace_vendor_payout';

    protected function _construct()
    {
        $this->_init(ResourceVendorPayout::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }
    public function setEntityId($id)
    {
        return $this->setData(self::ENTITY_ID, $id);
    }

    public function getVendorId()
    {
        return $this->getData(self::VENDOR_ID);
    }
    public function setVendorId($vendorId)
    {
        return $this->setData(self::VENDOR_ID, $vendorId);
    }

    public function getAmount()
    {
        return $this->getData(self::AMOUNT);
    }
    public function setAmount($amount)
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getNotes()
    {
        return $this->getData(self::NOTES);
    }
    public function setNotes($notes)
    {
        return $this->setData(self::NOTES, $notes);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
