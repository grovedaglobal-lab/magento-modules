<?php
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Marketplace\Api\Data\VendorDocumentInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument as ResourceDocument;
use Magento\Framework\DataObject\IdentityInterface;

class VendorDocument extends AbstractModel implements VendorDocumentInterface, IdentityInterface
{
    const CACHE_TAG = 'vendor_marketplace_document';
    protected $_cacheTag = 'vendor_marketplace_document';
    protected $_eventPrefix = 'vendor_marketplace_document';

    protected function _construct()
    {
        $this->_init(ResourceDocument::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }
    public function setEntityId($id)
    {
        return $this->setData(self::ENTITY_ID, $id);
    }

    public function getVendorId()
    {
        return $this->getData(self::VENDOR_ID);
    }
    public function setVendorId($vendorId)
    {
        return $this->setData(self::VENDOR_ID, $vendorId);
    }

    public function getDocumentType()
    {
        return $this->getData(self::DOCUMENT_TYPE);
    }
    public function setDocumentType($type)
    {
        return $this->setData(self::DOCUMENT_TYPE, $type);
    }

    public function getFilePath()
    {
        return $this->getData(self::FILE_PATH);
    }
    public function setFilePath($path)
    {
        return $this->setData(self::FILE_PATH, $path);
    }

    public function getLabel()
    {
        return $this->getData(self::LABEL);
    }
    public function setLabel($label)
    {
        return $this->setData(self::LABEL, $label);
    }

    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCertificateNumber()
    {
        return $this->getData(self::CERTIFICATE_NUMBER);
    }
    public function setCertificateNumber($number)
    {
        return $this->setData(self::CERTIFICATE_NUMBER, $number);
    }

    public function getIssuer()
    {
        return $this->getData(self::ISSUER);
    }
    public function setIssuer($issuer)
    {
        return $this->setData(self::ISSUER, $issuer);
    }

    public function getRegistrationDate()
    {
        return $this->getData('registration_date');
    }
    public function setRegistrationDate($date)
    {
        return $this->setData('registration_date', $date);
    }

    public function getExpiryDate()
    {
        return $this->getData('expiry_date');
    }
    public function setExpiryDate($date)
    {
        return $this->setData('expiry_date', $date);
    }

    public function getIsVisible()
    {
        return $this->getData('is_visible');
    }
    public function setIsVisible($isVisible)
    {
        return $this->setData('is_visible', $isVisible);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
