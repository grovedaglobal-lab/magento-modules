<?php
namespace Vendor\Marketplace\Api\Data;

interface VendorOrderInterface
{
    const ENTITY_ID = 'entity_id';
    const VENDOR_ID = 'vendor_id';
    const ORDER_ID = 'order_id';
    const SUBTOTAL = 'subtotal';
    const COMMISSION_AMOUNT = 'commission_amount';
    const VENDOR_EARNINGS = 'vendor_earnings';
    const STATUS = 'status';
    const CATEGORY_NAME = 'category_name';
    const COMMISSION_PERCENTAGE = 'commission_percentage';

    public function getEntityId();
    public function setEntityId($entityId);

    public function getVendorId();
    public function setVendorId($vendorId);

    public function getOrderId();
    public function setOrderId($orderId);

    public function getSubtotal();
    public function setSubtotal($subtotal);

    public function getCommissionAmount();
    public function setCommissionAmount($amount);

    public function getVendorEarnings();
    public function setVendorEarnings($earnings);

    public function getStatus();
    public function setStatus($status);

    /**
     * @return string|null
     */
    public function getCategoryName();

    /**
     * @param string $categoryName
     * @return $this
     */
    public function setCategoryName($categoryName);

    /**
     * @return float|null
     */
    public function getCommissionPercentage();

    /**
     * @param float $percent
     * @return $this
     */
    public function setCommissionPercentage($percent);
}
