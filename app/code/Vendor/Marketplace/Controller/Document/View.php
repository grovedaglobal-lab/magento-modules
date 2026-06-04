<?php
namespace Vendor\Marketplace\Controller\Document;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Controller\Result\RawFactory;
use Vendor\Marketplace\Model\VendorDocumentFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Framework\File\Mime;

class View extends Action
{
    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var VendorDocumentFactory
     */
    protected $vendorDocumentFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var VendorFactory
     */
    protected $vendorFactory;

    /**
     * @var Mime
     */
    protected $mime;

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param Filesystem $filesystem
     * @param VendorDocumentFactory $vendorDocumentFactory
     * @param CustomerSession $customerSession
     * @param VendorFactory $vendorFactory
     * @param Mime $mime
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        Filesystem $filesystem,
        VendorDocumentFactory $vendorDocumentFactory,
        CustomerSession $customerSession,
        VendorFactory $vendorFactory,
        Mime $mime
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->filesystem = $filesystem;
        $this->vendorDocumentFactory = $vendorDocumentFactory;
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->mime = $mime;
    }

    /**
     * View document action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            return $this->resultRedirectFactory->create()->setPath('vendor_marketplace/seller/profile');
        }

        // Check if customer is logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        // Get current vendor associated with customer
        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            $this->messageManager->addErrorMessage(__('Vendor account not found.'));
            return $this->resultRedirectFactory->create()->setPath('customer/account');
        }

        // Load document
        $document = $this->vendorDocumentFactory->create()->load($id);

        // Check ownership
        if (!$document->getId() || $document->getVendorId() != $vendor->getId()) {
            $this->messageManager->addErrorMessage(__('Document not found or access denied.'));
            return $this->resultRedirectFactory->create()->setPath('vendor_marketplace/seller/profile');
        }

        $fileName = $document->getFilePath();
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $filePath = 'vendor/documents/' . $fileName;

        if (!$directory->isFile($filePath)) {
            $this->messageManager->addErrorMessage(__('File not found: %1', $fileName));
            return $this->resultRedirectFactory->create()->setPath('vendor_marketplace/seller/profile');
        }

        /** @var \Magento\Framework\Controller\Result\Raw $resultRaw */
        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setHttpResponseCode(200);
        $resultRaw->setHeader('Content-Type', $this->mime->getMimeType($directory->getAbsolutePath($filePath)));
        // Use 'inline' to try to open in browser, fallback to 'attachment' if needed (user preference usually)
        // Adding filename to Content-Disposition
        $resultRaw->setHeader('Content-Disposition', 'inline; filename="' . basename($fileName) . '"');
        $resultRaw->setHeader('Content-Length', $directory->stat($filePath)['size']);
        $resultRaw->setContents($directory->readFile($filePath));

        return $resultRaw;
    }
}
