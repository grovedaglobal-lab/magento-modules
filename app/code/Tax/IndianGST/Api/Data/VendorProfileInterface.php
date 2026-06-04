<?php
namespace Tax\IndianGST\Api\Data;

interface VendorProfileInterface
{
    const ENTITY_ID = 'entity_id';
    const VENDOR_CODE = 'vendor_code';
    const GSTIN = 'gstin';
    const PAN_NUMBER = 'pan_number';
    const BUSINESS_NAME = 'business_name';
    const REGION_ID = 'region_id';
    const IS_REGISTERED = 'is_registered';

    /**
     * Get Entity ID
     * @return int|null
     */
    public function getId();

    /**
     * Set Entity ID
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get Vendor Code
     * @return string|null
     */
    public function getVendorCode();

    /**
     * Set Vendor Code
     * @param string $vendorCode
     * @return $this
     */
    public function setVendorCode($vendorCode);

    /**
     * Get GSTIN
     * @return string|null
     */
    public function getGstin();

    /**
     * Set GSTIN
     * @param string $gstin
     * @return $this
     */
    public function setGstin($gstin);

    /**
     * Get Region ID
     * @return int|null
     */
    public function getRegionId();

    /**
     * Set Region ID
     * @param int $regionId
     * @return $this
     */
    public function setRegionId($regionId);

    /**
     * Is GST Registered
     * @return bool
     */
    public function getIsRegistered();

    /**
     * Set Is GST Registered
     * @param bool $isRegistered
     * @return $this
     */
    public function setIsRegistered($isRegistered);
}
