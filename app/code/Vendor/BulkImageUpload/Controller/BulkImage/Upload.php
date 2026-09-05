<?php
namespace Vendor\BulkImageUpload\Controller\BulkImage;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Vendor\BulkImageUpload\Model\ZipValidator;
use Vendor\BulkImageUpload\Model\Queue\Publisher;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Vendor\BulkImageUpload\Model\BulkImageJobFactory;
use ZipArchive;

class Upload extends AbstractVendor implements HttpPostActionInterface
{
    protected $zipValidator;
    protected $publisher;
    protected $jsonFactory;
    protected $filesystem;
    protected $jobFactory;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        ZipValidator $zipValidator,
        Publisher $publisher,
        JsonFactory $jsonFactory,
        Filesystem $filesystem,
        BulkImageJobFactory $jobFactory
    ) {
        $this->zipValidator = $zipValidator;
        $this->publisher = $publisher;
        $this->jsonFactory = $jsonFactory;
        $this->filesystem = $filesystem;
        $this->jobFactory = $jobFactory;
        parent::__construct($context, $vendorSession);
    }

    public function execute()
    {
        try {
            $resultJson = $this->jsonFactory->create();
            $vendorId = $this->_vendorSession->getVendorId();
            
            if (!isset($_FILES['bulk_images'])) {
                if (isset($_FILES['bulk_image_zip'])) {
                    $filesArray = [
                        'name' => [$_FILES['bulk_image_zip']['name']],
                        'type' => [$_FILES['bulk_image_zip']['type']],
                        'tmp_name' => [$_FILES['bulk_image_zip']['tmp_name']],
                        'error' => [$_FILES['bulk_image_zip']['error']],
                        'size' => [$_FILES['bulk_image_zip']['size']]
                    ];
                } else {
                    return $resultJson->setData([
                        'success' => false, 
                        'error' => __('Please select one or more files (ZIP archives or images) to upload.')
                    ]);
                }
            } else {
                $filesArray = $_FILES['bulk_images'];
            }

            $fileCount = count($filesArray['name']);
            $hasValidFile = false;
            $zipPaths = [];
            $looseImages = [];

            $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $jobId = uniqid('job_');
            $targetDir = 'vendor_bulk_image/' . $jobId;
            $mediaDir->create($targetDir);
            $targetAbsDir = $mediaDir->getAbsolutePath($targetDir);

            for ($i = 0; $i < $fileCount; $i++) {
                $error = $filesArray['error'][$i];
                
                if ($error === UPLOAD_ERR_NO_FILE) continue;

                if ($error !== UPLOAD_ERR_OK) {
                    $errMsgs = [
                        UPLOAD_ERR_INI_SIZE   => __('The uploaded file exceeds the maximum allowed file size (256MB). Please reduce the file size or split into smaller ZIP archives and try again.'),
                        UPLOAD_ERR_FORM_SIZE  => __('The uploaded file exceeds the form size limit. Please upload a smaller file.'),
                        UPLOAD_ERR_PARTIAL    => __('The file was only partially uploaded due to a network interruption. Please try uploading again.'),
                        UPLOAD_ERR_NO_TMP_DIR => __('Server temporary folder is missing. Please contact support.'),
                        UPLOAD_ERR_CANT_WRITE => __('Failed to write uploaded file to server storage. Please try again or contact support.'),
                        UPLOAD_ERR_EXTENSION  => __('File upload was blocked by server security settings. Please verify you are only uploading valid image or ZIP files.'),
                    ];
                    $msg = isset($errMsgs[$error]) ? $errMsgs[$error] : __('An error occurred while uploading your file (Code: %1). Please try again.', $error);
                    return $resultJson->setData(['success' => false, 'error' => (string)$msg]);
                }

                $hasValidFile = true;
                $name = $filesArray['name'][$i];
                $tmpName = $filesArray['tmp_name'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($ext === 'zip') {
                    $validation = $this->zipValidator->validate($tmpName);
                    if (!$validation['isValid']) {
                        return $resultJson->setData([
                            'success' => false, 
                            'error' => __('Invalid ZIP archive (%1): %2', $name, implode(', ', $validation['errors']))
                        ]);
                    }
                    
                    $targetZipPath = $targetAbsDir . '/' . basename($name);
                    move_uploaded_file($tmpName, $targetZipPath);
                    $zipPaths[] = $targetZipPath;
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $looseImages[] = [
                        'tmp_name' => $tmpName,
                        'name' => $name
                    ];
                } else {
                    return $resultJson->setData([
                        'success' => false, 
                        'error' => __('Unsupported file format "%1". Only .zip archives and image files (.jpg, .jpeg, .png, .webp) are allowed.', $name)
                    ]);
                }
            }

            if (!$hasValidFile) {
                return $resultJson->setData([
                    'success' => false, 
                    'error' => __('No valid files were selected for upload. Please choose a ZIP file or image.')
                ]);
            }

            // Bundle loose images into a zip
            if (count($looseImages) > 0) {
                if (count($looseImages) > 1000) {
                    return $resultJson->setData([
                        'success' => false, 
                        'error' => __('You cannot upload more than 1,000 individual images at once. Please use ZIP archives for larger batches.')
                    ]);
                }
                
                $looseZipPath = $targetAbsDir . '/loose_images.zip';
                $zip = new ZipArchive();
                if ($zip->open($looseZipPath, ZipArchive::CREATE) === true) {
                    foreach ($looseImages as $img) {
                        $zip->addFile($img['tmp_name'], $img['name']);
                    }
                    $zip->close();
                    $zipPaths[] = $looseZipPath;
                } else {
                    return $resultJson->setData([
                        'success' => false, 
                        'error' => __('Failed to prepare the uploaded images for processing. Please try again.')
                    ]);
                }
            }

            $job = $this->jobFactory->create();
            $job->setData([
                'job_id' => $jobId,
                'vendor_id' => $vendorId,
                'zip_filename' => (count($zipPaths) > 1 || count($looseImages) > 0) ? count($zipPaths).' archives' : $filesArray['name'][0],
                'status' => 'pending'
            ]);
            $job->save();

            $this->publisher->execute($jobId, $vendorId, $zipPaths, $targetAbsDir);

            return $resultJson->setData(['success' => true, 'job_id' => $jobId]);
        } catch (\Throwable $e) {
            \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->critical($e);
            return $this->jsonFactory->create()->setData([
                'success' => false, 
                'error' => __('An unexpected error occurred during upload. Please try again.')
            ]);
        }
    }
}
