<?php
namespace Vendor\Ads\Api\Data;

interface AdGroupInterface
{
    const GROUP_ID     = 'group_id';
    const VENDOR_ID    = 'vendor_id';
    const NAME         = 'name';
    const DAILY_BUDGET = 'daily_budget';
    const TOTAL_BUDGET = 'total_budget';
    const STATUS       = 'status';
    const CREATED_AT   = 'created_at';

    public function getGroupId();
    public function setGroupId($id);
    public function getVendorId();
    public function setVendorId($vendorId);
    public function getName();
    public function setName($name);
    public function getDailyBudget();
    public function setDailyBudget($budget);
    public function getTotalBudget();
    public function setTotalBudget($budget);
    public function getStatus();
    public function setStatus($status);
    public function getCreatedAt();
}
