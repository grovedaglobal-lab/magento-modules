<?php
namespace Vendor\Marketplace\Api\Data;

interface VendorProfileInterface
{
    const PROFILE_ID = 'profile_id';
    const VENDOR_ID = 'vendor_id';
    const SHOP_NAME = 'shop_name';
    const COMPANY_NAME = 'company_name';
    const DESCRIPTION = 'description';
    const LOGO = 'logo';
    const ADDRESS = 'address';
    const PHONE = 'phone';

    public function getProfileId();
    public function setProfileId($id);

    public function getVendorId();
    public function setVendorId($vendorId);

    public function getShopName();
    public function setShopName($shopName);

    public function getCompanyName();
    public function setCompanyName($companyName);

    public function getDescription();
    public function setDescription($description);

    public function getLogo();
    public function setLogo($logo);

    public function getAddress();
    public function setAddress($address);

    public function getPhone();
    public function setPhone($phone);
}
