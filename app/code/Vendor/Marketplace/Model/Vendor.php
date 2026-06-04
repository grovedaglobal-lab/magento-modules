<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Api\Data\VendorInterface;
use Vendor\Marketplace\Model\ResourceModel\Vendor as ResourceVendor;
use Magento\Framework\DataObject\IdentityInterface;

class Vendor extends AbstractModel implements VendorInterface, IdentityInterface
{
    const CACHE_TAG = 'vendor_marketplace_vendor';
    protected $_cacheTag = 'vendor_marketplace_vendor';
    protected $_eventPrefix = 'vendor_marketplace_vendor';

    protected function _construct()
    {
        $this->_init(ResourceVendor::class);
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

    public function getCustomerId()
    {
        return $this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId($customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getShopUrl()
    {
        return $this->getData(self::SHOP_URL);
    }

    public function setShopUrl($shopUrl)
    {
        return $this->setData(self::SHOP_URL, $shopUrl);
    }

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getShopName()
    {
        return $this->getData('shop_name');
    }

    public function setShopName($shopName)
    {
        return $this->setData('shop_name', $shopName);
    }

    public function getLogo()
    {
        return $this->getData('logo');
    }

    public function setLogo($logo)
    {
        return $this->setData('logo', $logo);
    }

    public function getDescription()
    {
        return $this->getData('description');
    }

    public function setDescription($description)
    {
        return $this->setData('description', $description);
    }

    public function getBanner()
    {
        return $this->getData('banner');
    }

    public function setBanner($banner)
    {
        return $this->setData('banner', $banner);
    }
}
