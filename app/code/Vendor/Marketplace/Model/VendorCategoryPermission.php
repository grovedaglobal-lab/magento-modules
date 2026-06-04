<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Api\Data\VendorCategoryPermissionInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorCategoryPermission as ResourcePermission;
use Magento\Framework\DataObject\IdentityInterface;

class VendorCategoryPermission extends AbstractModel implements VendorCategoryPermissionInterface, IdentityInterface
{
    const CACHE_TAG = 'vendor_marketplace_cat_perm';
    protected $_cacheTag = 'vendor_marketplace_cat_perm';
    protected $_eventPrefix = 'vendor_marketplace_cat_perm';

    protected function _construct()
    {
        $this->_init(ResourcePermission::class);
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

    public function getCategoryId()
    {
        return $this->getData(self::CATEGORY_ID);
    }
    public function setCategoryId($categoryId)
    {
        return $this->setData(self::CATEGORY_ID, $categoryId);
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
}
