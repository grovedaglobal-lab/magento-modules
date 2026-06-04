<?php
namespace Vendor\Marketplace\Model\Vendor\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @var \Vendor\Marketplace\Model\VendorProfileFactory
     */
    protected $vendorProfileFactory;


    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param \Vendor\Marketplace\Model\VendorProfileFactory $vendorProfileFactory
     * @param ResourceConnection $resourceConnection
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        \Vendor\Marketplace\Model\VendorProfileFactory $vendorProfileFactory,
        ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $data = $item->getData();

            // Load Profile Data
            $profile = $this->vendorProfileFactory->create()->load($item->getId(), 'vendor_id');
            if ($profile->getId()) {
                $data = array_merge($data, $profile->getData());

                // Format Signature for FileUploader
                if (isset($data['signature']) && $data['signature']) {
                    $name = $data['signature'];
                    $data['signature'] = [
                        [
                            'name' => $name,
                            'url' => $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'vendor/signature' . $name
                        ]
                    ];
                }
            }

            // Note: Commission rules are global/category-based (no per-vendor commissions)

            // Load Documents Data
            $allDocuments = $this->loadVendorDocuments($item->getId());
            $generalDocuments = [];
            $organicCertifications = [];
            $coaReports = [];
            foreach ($allDocuments as $doc) {
                if ($doc['document_type'] === 'organic_certification') {
                    $organicCertifications[] = $doc;
                } elseif ($doc['document_type'] === 'coa_report') {
                    $coaReports[] = $doc;
                } else {
                    $generalDocuments[] = $doc;
                }
            }

            if (!empty($generalDocuments)) {
                $data['documents']['documents'] = $generalDocuments;
            }
            if (!empty($organicCertifications)) {
                $data['organic_documents']['organic_documents'] = $organicCertifications;
            }
            if (!empty($coaReports)) {
                $data['coa_documents']['coa_documents'] = $coaReports;
            }

            $this->loadedData[$item->getId()] = $data;
        }

        return $this->loadedData;
    }

    /**
     * Load vendor documents
     * 
     * @param int $vendorId
     * @return array
     */
    private function loadVendorDocuments($vendorId)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('vendor_document');

        $select = $connection->select()
            ->from($tableName)
            ->where('vendor_id = ?', $vendorId)
            ->order('created_at DESC');

        $documents = [];
        foreach ($connection->fetchAll($select) as $doc) {
            $documents[] = [
                'entity_id' => $doc['entity_id'],
                'document_type' => $doc['document_type'],
                'label' => $doc['label'],
                'registration_date' => $doc['registration_date'],
                'expiry_date' => $doc['expiry_date'],
                'certificate_number' => $doc['certificate_number'],
                'issuer' => $doc['issuer'],
                'is_visible' => isset($doc['is_visible']) ? $doc['is_visible'] : 1,
                'file_path' => $this->prepareFileResult($doc['file_path'])
            ];
        }

        return $documents;
    }

    /**
     * Prepare file result
     *
     * @param string $filePath
     * @return array
     */
    private function prepareFileResult($filePath)
    {
        if (!$filePath) {
            return [];
        }

        // Fix: Removed illegal call to getCatalogMediaConfig
        $folderName = 'vendor/documents';
        // Since we don't have easy access to DirectoryList here comfortably without injecting, let's assume standard path for now or use what works.
        // Actually, just constructing the URL and basic checks is safer.

        $folderName = 'vendor/documents';
        $storeBaseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        // Clean path to avoid double slashes
        $cleanFilePath = ltrim($filePath, '/');
        $fileUrl = $storeBaseUrl . $folderName . '/' . $cleanFilePath;

        return [
            [
                'name' => basename($filePath),
                'url' => $fileUrl,
                'file' => $filePath,
                'size' => 1024, // Placeholder, calculating real size requires Filesystem usage which we can add if needed
                'type' => 'application/octet-stream' // Generic type
            ]
        ];
    }
}
