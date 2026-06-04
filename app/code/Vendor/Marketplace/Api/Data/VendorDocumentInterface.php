<?php
namespace Vendor\Marketplace\Api\Data;

interface VendorDocumentInterface
{
    const ENTITY_ID = 'entity_id';
    const VENDOR_ID = 'vendor_id';
    const DOCUMENT_TYPE = 'document_type';
    const FILE_PATH = 'file_path';
    const LABEL = 'label';
    const CERTIFICATE_NUMBER = 'certificate_number';
    const ISSUER = 'issuer';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';

    public function getEntityId();
    public function setEntityId($id);

    public function getVendorId();
    public function setVendorId($vendorId);

    public function getDocumentType();
    public function setDocumentType($type);

    public function getFilePath();
    public function setFilePath($path);

    public function getLabel();
    public function setLabel($label);

    public function getStatus();
    public function setStatus($status);

    public function getCertificateNumber();
    public function setCertificateNumber($number);

    public function getIssuer();
    public function setIssuer($issuer);

    public function getRegistrationDate();
    public function setRegistrationDate($date);

    public function getExpiryDate();
    public function setExpiryDate($date);

    public function getIsVisible();
    public function setIsVisible($isVisible);

    public function getCreatedAt();
    public function setCreatedAt($createdAt);
}
