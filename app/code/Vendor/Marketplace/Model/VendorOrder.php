<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Api\Data\VendorOrderInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder as ResourceVendorOrder;
use Magento\Framework\DataObject\IdentityInterface;

class VendorOrder extends AbstractModel implements VendorOrderInterface, IdentityInterface
{
    const CACHE_TAG = 'vendor_marketplace_vendor_order';
    protected $_cacheTag = 'vendor_marketplace_vendor_order';
    protected $_eventPrefix = 'vendor_marketplace_vendor_order';

    protected function _construct()
    {
        $this->_init(ResourceVendorOrder::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getVendorId()
    {
        return $this->getData(self::VENDOR_ID);
    }
    public function setVendorId($vendorId)
    {
        return $this->setData(self::VENDOR_ID, $vendorId);
    }

    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getSubtotal()
    {
        return $this->getData(self::SUBTOTAL);
    }
    public function setSubtotal($subtotal)
    {
        return $this->setData(self::SUBTOTAL, $subtotal);
    }

    public function getCommissionAmount()
    {
        return $this->getData(self::COMMISSION_AMOUNT);
    }
    public function setCommissionAmount($amount)
    {
        return $this->setData(self::COMMISSION_AMOUNT, $amount);
    }

    public function getVendorEarnings()
    {
        return $this->getData(self::VENDOR_EARNINGS);
    }
    public function setVendorEarnings($earnings)
    {
        return $this->setData(self::VENDOR_EARNINGS, $earnings);
    }

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCategoryName()
    {
        return $this->getData(self::CATEGORY_NAME);
    }

    public function setCategoryName($categoryName)
    {
        return $this->setData(self::CATEGORY_NAME, $categoryName);
    }

    public function getCommissionPercentage()
    {
        return $this->getData(self::COMMISSION_PERCENTAGE);
    }

    public function setCommissionPercentage($percent)
    {
        return $this->setData(self::COMMISSION_PERCENTAGE, $percent);
    }
}
