<?php
namespace Vendor\Marketplace\Api;

interface VendorDocumentManagementInterface
{
    /**
     * Upload a document for the logged-in vendor.
     *
     * @param string $fileContentBase64 Base64 encoded file content
     * @param string $fileName Original file name (e.g. proof.jpg)
     * @param string $documentType Type of document (e.g. id_proof)
     * @return \Vendor\Marketplace\Api\Data\VendorDocumentInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function uploadDocument($fileContentBase64, $fileName, $documentType);

    /**
     * Get documents for the logged-in vendor.
     *
     * @return \Vendor\Marketplace\Api\Data\VendorDocumentInterface[]
     */
    public function getMyDocuments();
}
