<?php
namespace Vendor\Marketplace\Controller\Print;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Filesystem\DirectoryList;

class Save extends Action
{
    protected $filesystem;
    protected $uploaderFactory;
    protected $vendorFactory;
    protected $vendorProfileFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        VendorFactory $vendorFactory,
        VendorProfileFactory $vendorProfileFactory,
        CustomerSession $customerSession
    ) {
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->vendorFactory = $vendorFactory;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/account/login');
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            $this->messageManager->addErrorMessage(__('Vendor profile not found.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/manage');
        }

        $profile = $this->vendorProfileFactory->create()->load($vendor->getId(), 'vendor_id');
        if (!$profile->getId()) {
            // Should create if not exists
            $profile->setVendorId($vendor->getId());
        }

        try {
            // 1. Save Text Fields
            $authorizedName = $this->getRequest()->getParam('authorized_name');
            $profile->setData('authorized_name', $authorizedName);

            // 2. Handle Delete Signature
            if ($this->getRequest()->getParam('delete_signature')) {
                // Ideally delete file from disk too, but just clearing DB ref is safe for now
                $profile->setData('signature', '');
            }

            // 3. Handle File Upload
            $files = $this->getRequest()->getFiles('signature');
            if ($files && isset($files['name']) && !empty($files['name'])) {
                try {
                    $uploader = $this->uploaderFactory->create(['fileId' => 'signature']);
                    $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif']);
                    $uploader->setAllowRenameFiles(true);
                    $uploader->setFilesDispersion(false);

                    $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
                    $targetPath = $mediaDirectory->getAbsolutePath('vendor/signature');

                    $result = $uploader->save($targetPath);

                    if ($result['file']) {
                        $profile->setData('signature', $result['file']);
                    }
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(__('Error uploading signature: ' . $e->getMessage()));
                }
            }

            $profile->save();
            $this->messageManager->addSuccessMessage(__('Print settings saved successfully.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error saving settings: ' . $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/manage');
    }
}
