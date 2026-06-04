<?php
namespace Vendor\Ads\Api\Data;

interface WalletInterface
{
    const VENDOR_ID = 'vendor_id';
    const BALANCE = 'balance';
    const CURRENCY = 'currency';
    const UPDATED_AT = 'updated_at';

    public function getVendorId();
    public function setVendorId($vendorId);
    public function getBalance();
    public function setBalance($balance);
    public function getCurrency();
    public function setCurrency($currency);
    public function getUpdatedAt();
    public function setUpdatedAt($updatedAt);
}
