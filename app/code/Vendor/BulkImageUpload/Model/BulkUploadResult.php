<?php
namespace Vendor\BulkImageUpload\Model;
class BulkUploadResult
{
    public $successCount = 0; public $skippedFiles = []; public $unauthorizedSkus = [];
    public $notFoundSkus = []; public $skippedSkus = []; public $failedSkus = [];
    public $exceededSkus = []; public $globalErrors = [];
    public function incrementSuccess() { $this->successCount++; }
    public function addSkippedFile($f, $r) { $this->skippedFiles[$f] = $r; }
    public function addUnauthorizedSku($s) { $this->unauthorizedSkus[] = $s; }
    public function addNotFoundSku($s) { $this->notFoundSkus[] = $s; }
    public function addSkippedSku($s, $r) { $this->skippedSkus[$s] = $r; }
    public function addFailure($s, $m) { $this->failedSkus[$s] = $m; }
    public function addGlobalError($m) { $this->globalErrors[] = $m; }
    public function addExceededSku($s, $a, $m) { $this->exceededSkus[$s] = ['attempted' => $a, 'max' => $m]; }
    public function toArray() { return get_object_vars($this); }
}
