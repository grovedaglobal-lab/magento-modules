<?php
namespace Vendor\Marketplace\Controller\Adminhtml\File;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Controller\ResultFactory;

class Upload extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Marketplace::vendor_manage';

    protected $uploaderFactory;
    protected $filesystem;
    protected $storeManager;
    protected $fileDriver;

    public function __construct(
        Context $context,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        FileDriver $fileDriver,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->fileDriver = $fileDriver;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        try {
            $fileParam = 'file'; // Standard uploader component sends 'file' by default

            $uploader = $this->uploaderFactory->create(['fileId' => $fileParam]);

            // Allowed file extensions
            $uploader->setAllowedExtensions(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            // Set upload directory
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $uploadDir = 'vendor/documents';

            $result = $uploader->save($mediaDir->getAbsolutePath($uploadDir));

            if (isset($result['file'])) {
                $result['url'] = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
                    . $uploadDir . '/' . $result['file'];
                $result['name'] = $result['file']; // Ensure name is returned for UI

                return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
            }

            throw new \Exception(__('Failed to upload file'));

        } catch (\Exception $e) {
            return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode()
            ]);
        }
    }
}
