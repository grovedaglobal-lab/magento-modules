<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Api\Data\VendorProfileInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile as ResourceVendorProfile;
use Magento\Framework\DataObject\IdentityInterface;

class VendorProfile extends AbstractModel implements VendorProfileInterface, IdentityInterface
{
    const CACHE_TAG = 'vendor_marketplace_vendor_profile';
    protected $_cacheTag = 'vendor_marketplace_vendor_profile';
    protected $_eventPrefix = 'vendor_marketplace_vendor_profile';

    protected function _construct()
    {
        $this->_init(ResourceVendorProfile::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getProfileId()
    {
        return $this->getData(self::PROFILE_ID);
    }
    public function setProfileId($id)
    {
        return $this->setData(self::PROFILE_ID, $id);
    }

    public function getVendorId()
    {
        return $this->getData(self::VENDOR_ID);
    }
    public function setVendorId($vendorId)
    {
        return $this->setData(self::VENDOR_ID, $vendorId);
    }

    public function getShopName()
    {
        return $this->getData(self::SHOP_NAME);
    }
    public function setShopName($shopName)
    {
        return $this->setData(self::SHOP_NAME, $shopName);
    }

    public function getCompanyName()
    {
        return $this->getData(self::COMPANY_NAME);
    }
    public function setCompanyName($companyName)
    {
        return $this->setData(self::COMPANY_NAME, $companyName);
    }

    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }
    public function setDescription($description)
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getLogo()
    {
        return $this->getData(self::LOGO);
    }
    public function setLogo($logo)
    {
        return $this->setData(self::LOGO, $logo);
    }

    public function getAddress()
    {
        return $this->getData(self::ADDRESS);
    }
    public function setAddress($address)
    {
        return $this->setData(self::ADDRESS, $address);
    }

    public function getPhone()
    {
        return $this->getData(self::PHONE);
    }
    public function setPhone($phone)
    {
        return $this->setData(self::PHONE, $phone);
    }
}
