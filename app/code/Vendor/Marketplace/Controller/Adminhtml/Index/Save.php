<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\Vendor as VendorResource;

class Save extends Action
{
    /**
     * @var VendorFactory
     */
    protected $vendorFactory;

    /**
     * @var VendorResource
     */
    protected $vendorResource;

    /**
     * @var \Vendor\Marketplace\Model\VendorProfileFactory
     */
    protected $vendorProfileFactory;

    /**
     * @var \Vendor\Marketplace\Model\ResourceModel\VendorProfile
     */
    protected $vendorProfileResource;


    /**
     * @var \Vendor\Marketplace\Model\VendorDocumentFactory
     */
    protected $vendorDocumentFactory;

    /**
     * @var \Vendor\Marketplace\Model\ResourceModel\VendorDocument
     */
    protected $vendorDocumentResource;

    /**
     * @param Context $context
     * @param VendorFactory $vendorFactory
     * @param VendorResource $vendorResource
     * @param \Vendor\Marketplace\Model\VendorProfileFactory $vendorProfileFactory
     * @param \Vendor\Marketplace\Model\ResourceModel\VendorProfile $vendorProfileResource
     * @param \Vendor\Marketplace\Model\VendorDocumentFactory $vendorDocumentFactory
     * @param \Vendor\Marketplace\Model\ResourceModel\VendorDocument $vendorDocumentResource
     */
    public function __construct(
        Context $context,
        VendorFactory $vendorFactory,
        VendorResource $vendorResource,
        \Vendor\Marketplace\Model\VendorProfileFactory $vendorProfileFactory,
        \Vendor\Marketplace\Model\ResourceModel\VendorProfile $vendorProfileResource,
        \Vendor\Marketplace\Model\VendorDocumentFactory $vendorDocumentFactory,
        \Vendor\Marketplace\Model\ResourceModel\VendorDocument $vendorDocumentResource
    ) {
        parent::__construct($context);
        $this->vendorFactory = $vendorFactory;
        $this->vendorResource = $vendorResource;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->vendorProfileResource = $vendorProfileResource;
        $this->vendorDocumentFactory = $vendorDocumentFactory;
        $this->vendorDocumentResource = $vendorDocumentResource;
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();

        // Handle data wrapper if present (standard Magento UI behavior with dataScope="data")
        $data = isset($postData['data']) ? $postData['data'] : $postData;

        // Merge standard top-level keys if likely missing from 'data' wrapper but present in root
        if (isset($postData['form_key'])) {
            $data['form_key'] = $postData['form_key'];
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data) {
            $model = $this->vendorFactory->create();

            // entity_id might be top level or in data
            $id = $this->getRequest()->getParam('entity_id');
            if (!$id && isset($data['entity_id'])) {
                $id = $data['entity_id'];
            }

            if ($id) {
                $this->vendorResource->load($model, $id);
            }

            // Check if customer is already linked to another vendor (only for new vendors or when changing customer)
            if (isset($data['customer_id']) && !empty($data['customer_id'])) {
                $customerId = $data['customer_id'];

                // Check if this customer is already linked to a different vendor
                $existingVendor = $this->vendorFactory->create();
                $existingVendor->load($customerId, 'customer_id');

                // If a vendor exists with this customer_id and it's not the current vendor being edited
                if ($existingVendor->getId() && $existingVendor->getId() != $id) {
                    // Load vendor profile to get shop name
                    $existingProfile = $this->vendorProfileFactory->create()->load($existingVendor->getId(), 'vendor_id');
                    $shopName = $existingProfile->getShopName() ?: 'Unknown Shop';

                    $this->messageManager->addErrorMessage(
                        __(
                            'This customer is already linked to another vendor: "%1" (Vendor ID: %2). Please select a different customer.',
                            $shopName,
                            $existingVendor->getId()
                        )
                    );
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
                }
            }

            // Auto-generate shop_url from shop_name if provided
            if (!empty($data['shop_name'])) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['shop_name'])));
                $slug = preg_replace('/-+/', '-', $slug);
                $data['shop_url'] = $slug;
            }

            // Use addData to update model with new data
            $model->addData($data);

            try {
                $this->vendorResource->save($model);

                // Save Profile Data
                $profileModel = $this->vendorProfileFactory->create();
                $existingProfile = $this->vendorProfileFactory->create()->load($model->getId(), 'vendor_id');
                if ($existingProfile->getId()) {
                    $profileModel = $existingProfile;
                }

                $profileData = ['vendor_id' => $model->getId()];
                // extended list of profile fields to capture address, website etc
                $fieldsToSave = [
                    'shop_name',
                    'company_name',
                    'phone',
                    'website',
                    'email',
                    'address',
                    'city',
                    'country',
                    'state',
                    'zip_code',
                    'shop_url',
                    'pickup_address',
                    'pickup_city',
                    'pickup_state',
                    'pickup_zip_code',
                    'pickup_country',
                    'pickup_phone',
                    'pickup_email',
                    'business_license',
                    'tax_id',
                    'description',
                    'return_policy',
                    'shipping_policy',
                    'meta_keywords',
                    'meta_description',
                    'payment_info',
                    'google_analytics_id',
                    'min_order_amount',
                    'low_stock_notification',
                    'fulfillment_text',
                    'signature',
                    'authorized_name'
                ];

                foreach ($fieldsToSave as $field) {
                    if (isset($data[$field])) {
                        $value = $data[$field];
                        // Handle signature image data from uploader
                        if ($field === 'signature' && is_array($value)) {
                            if (isset($value[0]['name'])) {
                                $value = $value[0]['name'];
                            } else {
                                $value = null;
                            }
                        }
                        $profileData[$field] = $value;
                    }
                }

                $profileModel->addData($profileData);
                $this->vendorProfileResource->save($profileModel);

                // Note: Commission rules are managed globally via the Commission admin grid
                // (vendor_commission table has no vendor_id column - it is category-based only)

                // Initialize documents array for merging
                $documentsToSave = isset($data['documents']['documents']) ? $data['documents']['documents'] : [];

                // Merge Organic Certifications into documents array for saving
                if (isset($data['organic_documents']['organic_documents'])) {
                    $documentsToSave = array_merge($documentsToSave, $data['organic_documents']['organic_documents']);
                }

                // Merge COA Reports into documents array for saving
                if (isset($data['coa_documents']['coa_documents'])) {
                    $documentsToSave = array_merge($documentsToSave, $data['coa_documents']['coa_documents']);
                }

                // Assign the merged documents back to $data for saveDocuments method
                $data['documents']['documents'] = $documentsToSave;

                $this->saveDocuments($model->getId(), $data);

                $this->messageManager->addSuccessMessage(__('You saved the vendor.'));
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Save vendor documents
     *
     * @param int $vendorId
     * @param array $data
     */
    protected function saveDocuments($vendorId, $data)
    {
        // DEBUG: Log documents data structure
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/vendor_save_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('=== SAVE DOCUMENTS CALLED ===');
        $logger->info('Vendor ID: ' . $vendorId);

        // Handle potentially nested [documents][documents]
        $docs = [];
        if (isset($data['documents'])) {
            if (is_array($data['documents']) && isset($data['documents']['documents'])) {
                $docs = $data['documents']['documents'];
            } elseif (is_array($data['documents'])) {
                $docs = $data['documents'];
            }
        }

        $logger->info('Documents found in POST: ' . count($docs));
        $logger->info('Documents data: ' . print_r($docs, true));

        // Get existing documents from database
        $existingDocs = $this->getExistingDocuments($vendorId);
        $logger->info('Existing documents in DB: ' . count($existingDocs));

        // Track which document IDs are in the submission
        $submittedDocIds = [];

        foreach ($docs as $index => $docData) {
            $logger->info("Processing document index: $index");
            $logger->info('Document data: ' . print_r($docData, true));

            // Handle explicit deletion flag
            if (isset($docData['delete']) && $docData['delete'] == 1) {
                $logger->info('Document marked for deletion with delete flag');
                if (isset($docData['entity_id']) && !empty($docData['entity_id'])) {
                    $this->deleteDocument($docData['entity_id']);
                    $logger->info('Document deleted: ' . $docData['entity_id']);
                }
                continue;
            }

            // Extract file path from uploader structure - IMPROVED LOGIC
            $filePath = '';

            if (isset($docData['file_path'])) {
                $logger->info('file_path exists, type: ' . gettype($docData['file_path']));

                if (is_array($docData['file_path']) && !empty($docData['file_path'])) {
                    $logger->info('file_path is array with ' . count($docData['file_path']) . ' elements');

                    // Check if it's a numeric array (uploaded files)
                    if (isset($docData['file_path'][0])) {
                        $fileInfo = $docData['file_path'][0];
                        $logger->info('First element: ' . print_r($fileInfo, true));

                        if (is_array($fileInfo)) {
                            // Standard uploader response: ['name' => '...', 'file' => '...', 'url' => '...']
                            if (isset($fileInfo['file'])) {
                                $filePath = $fileInfo['file'];
                                $logger->info('Extracted file path from array[0][file]: ' . $filePath);
                            } elseif (isset($fileInfo['name'])) {
                                // Fallback to name if file key doesn't exist
                                $filePath = $fileInfo['name'];
                                $logger->info('Extracted file path from array[0][name]: ' . $filePath);
                            }
                        } elseif (is_string($fileInfo)) {
                            // Sometimes it's just an array of strings
                            $filePath = $fileInfo;
                            $logger->info('Extracted file path from array[0] as string: ' . $filePath);
                        }
                    } else {
                        // Associative array - might be the file info directly
                        if (isset($docData['file_path']['file'])) {
                            $filePath = $docData['file_path']['file'];
                            $logger->info('Extracted file path from [file_path][file]: ' . $filePath);
                        } elseif (isset($docData['file_path']['name'])) {
                            $filePath = $docData['file_path']['name'];
                            $logger->info('Extracted file path from [file_path][name]: ' . $filePath);
                        }
                    }
                } elseif (is_string($docData['file_path'])) {
                    $filePath = $docData['file_path'];
                    $logger->info('file_path is string: ' . $filePath);
                }
            }

            $logger->info('Final extracted file path: ' . ($filePath ?: 'EMPTY'));

            // If we have an existing row (entity_id) but no new file path, we keep existing.
            // Skip only if it's a new document with no file
            if (empty($filePath) && !isset($docData['entity_id'])) {
                $logger->info('Skipping - no file path and no entity_id (new document without file)');
                continue;
            }

            $docModel = $this->vendorDocumentFactory->create();
            if (isset($docData['entity_id']) && !empty($docData['entity_id'])) {
                $this->vendorDocumentResource->load($docModel, $docData['entity_id']);
                $logger->info('Loading existing document: ' . $docData['entity_id']);
                $submittedDocIds[] = $docData['entity_id'];
            } else {
                $logger->info('Creating new document');
            }

            $docModel->setVendorId($vendorId);
            if (isset($docData['document_type'])) {
                $docModel->setDocumentType($docData['document_type']);
                $logger->info('Set document_type: ' . $docData['document_type']);
            }
            if (isset($docData['label'])) {
                $docModel->setLabel($docData['label']);
                $logger->info('Set label: ' . $docData['label']);
            }
            if (isset($docData['registration_date'])) {
                $docModel->setRegistrationDate($docData['registration_date']);
                $logger->info('Set registration_date: ' . $docData['registration_date']);
            }
            if (isset($docData['expiry_date'])) {
                $docModel->setExpiryDate($docData['expiry_date']);
                $logger->info('Set expiry_date: ' . $docData['expiry_date']);
            }
            if (isset($docData['certificate_number'])) {
                $docModel->setCertificateNumber($docData['certificate_number']);
                $logger->info('Set certificate_number: ' . $docData['certificate_number']);
            }
            if (isset($docData['issuer'])) {
                $docModel->setIssuer($docData['issuer']);
                $logger->info('Set issuer: ' . $docData['issuer']);
            }
            if (!empty($filePath)) {
                $docModel->setFilePath($filePath);
                $logger->info('Set file_path: ' . $filePath);
            }
            if (isset($docData['is_visible'])) {
                $docModel->setIsVisible($docData['is_visible']);
                $logger->info('Set is_visible: ' . $docData['is_visible']);
            } else {
                // Default to visible if not set
                if (!$docModel->getId()) {
                    $docModel->setIsVisible(1);
                }
            }

            if (!$docModel->getId()) {
                $docModel->setStatus(0);
                $logger->info('New document - set status to 0');
            }

            try {
                $this->vendorDocumentResource->save($docModel);
                $logger->info('Document saved successfully! ID: ' . $docModel->getId());
                if ($docModel->getId()) {
                    $submittedDocIds[] = $docModel->getId();
                }
            } catch (\Exception $e) {
                $logger->err('Error saving document: ' . $e->getMessage());
                $logger->err('Stack trace: ' . $e->getTraceAsString());
            }
        }

        // Delete documents that exist in DB but weren't in the submission
        // This handles the case where dynamicRows removes deleted items from POST
        $logger->info('Submitted document IDs: ' . print_r($submittedDocIds, true));
        foreach ($existingDocs as $existingDocId) {
            if (!in_array($existingDocId, $submittedDocIds)) {
                $logger->info('Document ID ' . $existingDocId . ' not in submission - deleting');
                $this->deleteDocument($existingDocId);
            }
        }

        $logger->info('=== SAVE DOCUMENTS COMPLETED ===');
    }

    /**
     * Get existing document IDs for a vendor
     * 
     * @param int $vendorId
     * @return array
     */
    private function getExistingDocuments($vendorId)
    {
        $collection = $this->vendorDocumentFactory->create()->getCollection()
            ->addFieldToFilter('vendor_id', $vendorId);

        $docIds = [];
        foreach ($collection as $doc) {
            $docIds[] = $doc->getId();
        }

        return $docIds;
    }

    /**
     * Delete a document by ID
     * 
     * @param int $documentId
     */
    private function deleteDocument($documentId)
    {
        try {
            $docModel = $this->vendorDocumentFactory->create();
            $this->vendorDocumentResource->load($docModel, $documentId);
            if ($docModel->getId()) {
                $this->vendorDocumentResource->delete($docModel);
            }
        } catch (\Exception $e) {
            // Log error but don't throw - continue processing other documents
        }
    }

    /**
     * Check permission for action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::dashboard');
    }
}
