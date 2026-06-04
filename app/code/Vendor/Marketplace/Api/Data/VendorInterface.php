<?php
namespace Vendor\Marketplace\Api\Data;

interface VendorInterface
{
    const ENTITY_ID = 'entity_id';
    const CUSTOMER_ID = 'customer_id';
    const SHOP_URL = 'shop_url';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get Entity ID
     * @return int|null
     */
    public function getEntityId();

    /**
     * Set Entity ID
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * Get Customer ID
     * @return int|null
     */
    public function getCustomerId();

    /**
     * Set Customer ID
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId);

    /**
     * Get Shop URL
     * @return string|null
     */
    public function getShopUrl();

    /**
     * Set Shop URL
     * @param string $shopUrl
     * @return $this
     */
    public function setShopUrl($shopUrl);

    /**
     * Get Status
     * @return int|null
     */
    public function getStatus();

    /**
     * Set Status
     * @param int $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get Created At
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set Created At
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get Updated At
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set Updated At
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * Get Shop Name
     * @return string|null
     */
    public function getShopName();

    /**
     * Set Shop Name
     * @param string $shopName
     * @return $this
     */
    public function setShopName($shopName);

    /**
     * Get Logo
     * @return string|null
     */
    public function getLogo();

    /**
     * Set Logo
     * @param string $logo
     * @return $this
     */
    public function setLogo($logo);

    /**
     * Get Description
     * @return string|null
     */
    public function getDescription();

    /**
     * Set Description
     * @param string $description
     * @return $this
     */
    public function setDescription($description);

    /**
     * Get Banner
     * @return string|null
     */
    public function getBanner();

    /**
     * Set Banner
     * @param string $banner
     * @return $this
     */
    public function setBanner($banner);
}
