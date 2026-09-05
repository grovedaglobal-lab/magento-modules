<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorDocumentManagementInterface;
use Vendor\Marketplace\Model\VendorDocumentFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument as ResourceDocument;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument\CollectionFactory as DocumentCollectionFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Authorization\Model\UserContextInterface;
use Vendor\Marketplace\Model\VendorRepository;
use Magento\Framework\Exception\LocalizedException;

class VendorDocumentManagement implements VendorDocumentManagementInterface
{
    protected $documentFactory;
    protected $resourceDocument;
    protected $collectionFactory;
    protected $filesystem;
    protected $userContext;
    protected $vendorRepository;

    public function __construct(
        VendorDocumentFactory $documentFactory,
        ResourceDocument $resourceDocument,
        DocumentCollectionFactory $collectionFactory,
        Filesystem $filesystem,
        UserContextInterface $userContext,
        VendorRepository $vendorRepository
    ) {
        $this->documentFactory = $documentFactory;
        $this->resourceDocument = $resourceDocument;
        $this->collectionFactory = $collectionFactory;
        $this->filesystem = $filesystem;
        $this->userContext = $userContext;
        $this->vendorRepository = $vendorRepository;
    }

    protected function getVendorId()
    {
        $customerId = $this->userContext->getUserId();
        $vendor = $this->vendorRepository->getByCustomerId($customerId);
        if (!$vendor->getEntityId()) {
            throw new LocalizedException(__('Vendor account not found.'));
        }
        return $vendor->getEntityId();
    }

    public function uploadDocument($fileContentBase64, $fileName, $documentType)
    {
        $vendorId = $this->getVendorId();

        // 1. Decode File
        $fileContent = base64_decode($fileContentBase64, true);
        if (!$fileContent) {
            throw new LocalizedException(__('Invalid base64 content.'));
        }

        // 2. Validate File Type (Basic check)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new LocalizedException(__('Invalid file extension. Allowed: jpg, jpeg, png, pdf'));
        }

        // 3. Save File (Sanitize filename to prevent directory traversal)
        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($fileName));
        if (empty($cleanFileName)) {
            $cleanFileName = 'doc_' . uniqid() . '.' . $extension;
        }
        $relativePath = 'vendor/documents/' . (int)$vendorId . '/' . time() . '_' . $cleanFileName;
        $mediaDir->writeFile($relativePath, $fileContent);

        // 4. Save Record
        $document = $this->documentFactory->create();
        $document->setVendorId($vendorId);
        $document->setDocumentType($documentType);
        $document->setFilePath($relativePath);
        $document->setStatus(0); // Pending

        $this->resourceDocument->save($document);

        return $document;
    }

    public function getMyDocuments()
    {
        $vendorId = $this->getVendorId();
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        return $collection->getItems();
    }
}
