<?php
namespace Vendor\Marketplace\Api\Data;

interface VendorCategoryPermissionInterface
{
    const ENTITY_ID = 'entity_id';
    const VENDOR_ID = 'vendor_id';
    const CATEGORY_ID = 'category_id';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';

    public function getEntityId();
    public function setEntityId($id);

    public function getVendorId();
    public function setVendorId($vendorId);

    public function getCategoryId();
    public function setCategoryId($categoryId);

    public function getStatus();
    public function setStatus($status);

    public function getCreatedAt();
    public function setCreatedAt($createdAt);
}
