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
            // Fallback for single file input if HTML wasn't updated yet
            if (isset($_FILES['bulk_image_zip'])) {
                $filesArray = [
                    'name' => [$_FILES['bulk_image_zip']['name']],
                    'type' => [$_FILES['bulk_image_zip']['type']],
                    'tmp_name' => [$_FILES['bulk_image_zip']['tmp_name']],
                    'error' => [$_FILES['bulk_image_zip']['error']],
                    'size' => [$_FILES['bulk_image_zip']['size']]
                ];
            } else {
                return $resultJson->setData(['success' => false, 'error' => 'No files were uploaded.']);
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
                    UPLOAD_ERR_INI_SIZE   => 'A file exceeds the upload_max_filesize directive in php.ini.',
                    UPLOAD_ERR_FORM_SIZE  => 'A file exceeds the HTML form file size limit.',
                    UPLOAD_ERR_PARTIAL    => 'A file was only partially uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
                ];
                $msg = isset($errMsgs[$error]) ? $errMsgs[$error] : 'Unknown upload error code: ' . $error;
                return $resultJson->setData(['success' => false, 'error' => $msg]);
            }

            $hasValidFile = true;
            $name = $filesArray['name'][$i];
            $tmpName = $filesArray['tmp_name'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if ($ext === 'zip') {
                $validation = $this->zipValidator->validate($tmpName);
                if (!$validation['isValid']) {
                    return $resultJson->setData(['success' => false, 'error' => 'ZIP error (' . $name . '): ' . implode(', ', $validation['errors'])]);
                }
                
                $targetZipPath = $targetAbsDir . '/' . $name;
                move_uploaded_file($tmpName, $targetZipPath);
                $zipPaths[] = $targetZipPath;
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $looseImages[] = [
                    'tmp_name' => $tmpName,
                    'name' => $name
                ];
            } else {
                return $resultJson->setData(['success' => false, 'error' => 'Unsupported file type: ' . $name]);
            }
        }

        if (!$hasValidFile) {
            return $resultJson->setData(['success' => false, 'error' => 'No valid files were uploaded.']);
        }

        // Bundle loose images into a zip
        if (count($looseImages) > 0) {
            if (count($looseImages) > 1000) {
                return $resultJson->setData(['success' => false, 'error' => 'You cannot upload more than 1000 individual images at once.']);
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
                return $resultJson->setData(['success' => false, 'error' => 'Failed to create archive for individual images.']);
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
            return $this->jsonFactory->create()->setData(['success' => false, 'error' => 'FATAL ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()]);
        }
    }
}

