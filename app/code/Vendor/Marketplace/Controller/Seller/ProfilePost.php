<?php
namespace Vendor\Marketplace\Controller\Seller;

use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class ProfilePost extends \Magento\Framework\App\Action\Action
{
    protected $customerSession;
    protected $vendorRepository;
    protected $vendorProfileFactory;
    protected $uploaderFactory;
    protected $filesystem;
    protected $vendorDocumentFactory;


    protected $imageFactory;
    protected $logger;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        \Vendor\Marketplace\Api\VendorRepositoryInterface $vendorRepository,
        VendorProfileFactory $vendorProfileFactory,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        \Vendor\Marketplace\Model\VendorDocumentFactory $vendorDocumentFactory,
        \Magento\Framework\Image\AdapterFactory $imageFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->vendorRepository = $vendorRepository;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->vendorDocumentFactory = $vendorDocumentFactory;
        $this->imageFactory = $imageFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('*/*/profile');
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $formKeyValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Data\Form\FormKey\Validator::class);
        if (!$formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page and try again.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/profile');
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorRepository->getByCustomerId($customerId);

            if (!$vendor->getId()) {
                throw new \Exception(__('You are not a registered vendor.'));
            }

            $currentProfile = $this->vendorProfileFactory->create();
            $currentProfile->load($vendor->getId(), 'vendor_id');

            // If profile doesn't exist, create new
            if (!$currentProfile->getId()) {
                $currentProfile->setVendorId($vendor->getId());
            }

            $data = $this->getRequest()->getPostValue();

            // Handle Checkboxes (if unchecked, set to 0)
            $checkboxes = [
                'twitter_active',
                'facebook_active',
                'instagram_active',
                'youtube_active',
                'vimeo_active',
                'pinterest_active'
            ];
            foreach ($checkboxes as $checkbox) {
                if (!isset($data[$checkbox])) {
                    $data[$checkbox] = 0;
                }
            }

            // Handle Logic for File Upload helper
            $fileFields = [
                'logo' => 'vendor/logo',
                'banner' => 'vendor/banner',
                'fulfillment_image' => 'vendor/fulfillment',
                'signature' => 'vendor/signature',
                'payment_document' => 'vendor/payment_document',
                'brand_header_image' => 'vendor/brand',
                'brand_slider_image_1' => 'vendor/brand',
                'brand_slider_image_2' => 'vendor/brand',
                'brand_slider_image_3' => 'vendor/brand',
            ];

            $files = $this->getRequest()->getFiles();

            foreach ($fileFields as $field => $path) {
                if (isset($files[$field]) && !empty($files[$field]['name'])) {
                    try {
                        $uploader = $this->uploaderFactory->create(['fileId' => $field]);
                        if ($field === 'payment_document') {
                            $uploader->setAllowedExtensions(['pdf']);
                        } else {
                            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'webp']);
                        }
                        $uploader->setAllowRenameFiles(true);
                        $uploader->setFilesDispersion(false);

                        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
                        $savePath = $mediaDirectory->getAbsolutePath($path);

                        $result = $uploader->save($savePath);
                        $data[$field] = $result['file'];

                        // Resize Image - Fixed Banner compression
                        if ($field === 'logo' || $field === 'banner') {
                            $imagePath = $savePath . DIRECTORY_SEPARATOR . ltrim($result['file'], DIRECTORY_SEPARATOR);
                            try {
                                $imageFactory = $this->imageFactory->create();
                                $imageFactory->open($imagePath);
                                $imageFactory->constrainOnly(true);
                                $imageFactory->keepTransparency(true);
                                $imageFactory->keepFrame(false);
                                $imageFactory->keepAspectRatio(true);

                                // Proper sizing to prevent squashing
                                if ($field === 'logo') {
                                    $imageFactory->resize(400, 400);
                                } else {
                                    $imageFactory->resize(1200, 450); // Better banner aspect ratio
                                }

                                $imageFactory->save($imagePath); // Overwrite original
                            } catch (\Exception $e) {
                                // Log error but don't fail the whole process
                                $this->logger->error('Image processing failed: ' . $e->getMessage());
                            }
                        }
                    } catch (\Exception $e) {
                        $this->messageManager->addErrorMessage(__($field . ' upload failed: %1', $e->getMessage()));
                    }
                }
            }

            // Handle asset deletions
            $assetsToDelete = ['logo', 'banner', 'fulfillment_image', 'brand_header_image', 'brand_slider_image_1', 'brand_slider_image_2', 'brand_slider_image_3'];
            foreach ($assetsToDelete as $asset) {
                if (isset($data['delete_' . $asset]) && $data['delete_' . $asset]) {
                    $data[$asset] = ''; // Clear in DB
                }
            }

            // Set Data
            $currentProfile->addData($data);
            $currentProfile->save();

            // Handle Document Deletions
            if (isset($data['delete_documents']) && is_array($data['delete_documents'])) {
                foreach ($data['delete_documents'] as $docId) {
                    try {
                        $docToDelete = $this->vendorDocumentFactory->create()->load($docId);
                        if ($docToDelete->getId() && $docToDelete->getVendorId() == $vendor->getId()) {
                            $docToDelete->delete();
                        }
                    } catch (\Exception $e) {
                        $this->messageManager->addErrorMessage(__('Error deleting document: %1', $e->getMessage()));
                    }
                }
            }

            // Handle New Dynamic Document Uploads
            if (isset($data['new_documents']) && is_array($data['new_documents'])) {
                foreach ($data['new_documents'] as $key => $docData) {
                    $fileId = isset($docData['file_id']) ? $docData['file_id'] : '';

                    if ($fileId && isset($files[$fileId]) && !empty($files[$fileId]['name'])) {
                        try {
                            $docUploader = $this->uploaderFactory->create(['fileId' => $fileId]);
                            $docUploader->setAllowRenameFiles(true);
                            $docUploader->setFilesDispersion(false);

                            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
                            $docSavePath = $mediaDirectory->getAbsolutePath('vendor/documents');

                            $docResult = $docUploader->save($docSavePath);
                            $uploadedFileName = $docResult['file'];

                            // Create or update Document Record (Prevents duplicate row insertions on rapid multi-submit)
                            $document = null;
                            if (!empty($docData['certificate_number'])) {
                                $existing = $this->vendorDocumentFactory->create()->getCollection()
                                    ->addFieldToFilter('vendor_id', $vendor->getId())
                                    ->addFieldToFilter('document_type', isset($docData['type']) ? $docData['type'] : '')
                                    ->addFieldToFilter('certificate_number', $docData['certificate_number'])
                                    ->getFirstItem();
                                if ($existing->getId()) {
                                    $document = $existing;
                                }
                            }

                            if (!$document) {
                                $document = $this->vendorDocumentFactory->create();
                            }

                            $document->setVendorId($vendor->getId());
                            $document->setLabel(isset($docData['label']) ? $docData['label'] : '');
                            $document->setDocumentType(isset($docData['type']) ? $docData['type'] : '');

                            if (!empty($docData['registration_date'])) {
                                $document->setRegistrationDate($docData['registration_date']);
                            }
                            if (!empty($docData['expiry_date'])) {
                                $document->setExpiryDate($docData['expiry_date']);
                            }
                            if (!empty($docData['certificate_number'])) {
                                $document->setCertificateNumber($docData['certificate_number']);
                            }
                            if (!empty($docData['issuer'])) {
                                $document->setIssuer($docData['issuer']);
                            }
                            if (!empty($docData['product_name'])) {
                                $document->setData('product_name', $docData['product_name']);
                            }
                            if (!empty($docData['product_desc'])) {
                                $document->setData('product_desc', $docData['product_desc']);
                            }

                            $document->setFilePath($uploadedFileName);
                            $document->setStatus(0); // Pending
                            $document->save();

                        } catch (\Exception $e) {
                            $this->logger->error("Document save failed: " . $e->getMessage());
                            $this->messageManager->addErrorMessage(__('Document upload failed for %1: %2', $docData['label'], $e->getMessage()));
                        }
                    }
                }
                $this->messageManager->addSuccessMessage(__('Documents updated successfully.'));
            }

            $this->messageManager->addSuccessMessage(__('Profile saved successfully.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        // Preserve active tab after redirect
        $activeTab = $this->getRequest()->getPostValue('active_tab', 'profile-info');
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('*/*/profile', ['tab' => $activeTab]);
        return $redirect;
    }
}
