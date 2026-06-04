<?php
namespace Vendor\Marketplace\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument\CollectionFactory as DocumentCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class VendorDocuments implements ResolverInterface
{
    protected $documentCollectionFactory;
    protected $storeManager;

    public function __construct(
        DocumentCollectionFactory $documentCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->documentCollectionFactory = $documentCollectionFactory;
        $this->storeManager = $storeManager;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $vendorId = null;
        if (isset($value['entity_id'])) {
            $vendorId = $value['entity_id'];
        } elseif (isset($value['vendor_id'])) {
            $vendorId = $value['vendor_id'];
        }

        if (!$vendorId) {
            return [];
        }

        $collection = $this->documentCollectionFactory->create()
            ->addFieldToFilter('vendor_id', $vendorId);

        $documents = [];
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        foreach ($collection as $document) {
            // Check if document is hidden by admin
            if ($document->getData('is_visible') === '0' || $document->getData('is_visible') === 0) {
                continue;
            }

            $filePath = $document->getData('file_path');
            $fullUrl = $mediaUrl . 'vendor/documents/' . ltrim($filePath, '/');

            $documents[] = [
                'document_id' => (int) $document->getData('entity_id'),
                'document_type' => $document->getData('document_type'),
                'label' => $document->getData('label'),
                'file_path' => $fullUrl,
                'status' => (int) $document->getData('status'),
                'registration_date' => $document->getData('registration_date'),
                'expiry_date' => $document->getData('expiry_date'),
                'certificate_number' => $document->getData('certificate_number'),
                'issuer' => $document->getData('issuer'),
                'product_name' => $document->getData('product_name'),
                'product_desc' => $document->getData('product_desc'),
            ];
        }

        return $documents;
    }
}
