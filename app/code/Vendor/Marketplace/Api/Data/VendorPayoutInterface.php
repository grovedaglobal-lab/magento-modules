<?php
namespace Vendor\Marketplace\Api\Data;

interface VendorPayoutInterface
{
    const ENTITY_ID = 'entity_id';
    const VENDOR_ID = 'vendor_id';
    const AMOUNT = 'amount';
    const STATUS = 'status';
    const NOTES = 'notes';
    const CREATED_AT = 'created_at';

    public function getEntityId();
    public function setEntityId($id);

    public function getVendorId();
    public function setVendorId($vendorId);

    public function getAmount();
    public function setAmount($amount);

    public function getStatus();
    public function setStatus($status);

    public function getNotes();
    public function setNotes($notes);

    public function getCreatedAt();
    public function setCreatedAt($createdAt);
}
