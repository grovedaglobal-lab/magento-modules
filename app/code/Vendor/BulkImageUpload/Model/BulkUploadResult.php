<?php
namespace Vendor\BulkImageUpload\Model;

class BulkUploadResult
{
    public $successCount = 0;
    public $skippedFiles = [];
    public $unauthorizedSkus = [];
    public $notFoundSkus = [];
    public $skippedSkus = [];
    public $failedSkus = [];
    public $exceededSkus = [];
    public $globalErrors = [];

    public function incrementSuccess()
    {
        $this->successCount++;
    }

    public function addSkippedFile($filename, $reason)
    {
        $this->skippedFiles[$filename] = $reason;
    }

    public function addUnauthorizedSku($sku)
    {
        if (!in_array($sku, $this->unauthorizedSkus)) {
            $this->unauthorizedSkus[] = $sku;
        }
    }

    public function addNotFoundSku($sku)
    {
        if (!in_array($sku, $this->notFoundSkus)) {
            $this->notFoundSkus[] = $sku;
        }
    }

    public function addSkippedSku($sku, $reason)
    {
        $this->skippedSkus[$sku] = $reason;
    }

    public function addFailure($sku, $message)
    {
        $this->failedSkus[$sku] = $message;
    }

    public function addGlobalError($message)
    {
        $this->globalErrors[] = $message;
    }

    public function addExceededSku($sku, $attempted, $max)
    {
        $this->exceededSkus[$sku] = ['attempted' => $attempted, 'max' => $max];
    }

    public function getErrors()
    {
        $errors = [];

        foreach ($this->notFoundSkus as $sku) {
            $errors[$sku][] = "SKU '{$sku}' not found in catalog.";
        }

        foreach ($this->unauthorizedSkus as $sku) {
            $errors[$sku][] = "SKU '{$sku}' does not belong to your vendor account.";
        }

        foreach ($this->skippedFiles as $filename => $reason) {
            $errors[$filename][] = $reason;
        }

        foreach ($this->skippedSkus as $sku => $reason) {
            $errors[$sku][] = $reason;
        }

        foreach ($this->failedSkus as $sku => $reason) {
            $errors[$sku][] = $reason;
        }

        foreach ($this->exceededSkus as $sku => $data) {
            $errors[$sku][] = "Exceeded max images limit ({$data['attempted']} uploaded, max allowed {$data['max']}).";
        }

        return $errors;
    }

    public function hasErrors()
    {
        return !empty($this->notFoundSkus)
            || !empty($this->unauthorizedSkus)
            || !empty($this->skippedFiles)
            || !empty($this->skippedSkus)
            || !empty($this->failedSkus)
            || !empty($this->exceededSkus)
            || !empty($this->globalErrors);
    }

    public function getErrorCount()
    {
        return count($this->getErrors()) + count($this->globalErrors);
    }

    public function toArray()
    {
        $data = get_object_vars($this);
        $data['errors'] = $this->getErrors();
        $data['hasErrors'] = $this->hasErrors();
        $data['errorCount'] = $this->getErrorCount();
        return $data;
    }
}
