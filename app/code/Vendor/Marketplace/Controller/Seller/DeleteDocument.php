<?php
namespace Vendor\Marketplace\Controller\Seller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Model\VendorDocumentFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument as VendorDocumentResource;
use Vendor\Marketplace\Api\VendorRepositoryInterface;

class DeleteDocument extends Action
{
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var VendorDocumentFactory
     */
    protected $vendorDocumentFactory;

    /**
     * @var VendorDocumentResource
     */
    protected $vendorDocumentResource;

    /**
     * @var VendorRepositoryInterface
     */
    protected $vendorRepository;

    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param VendorDocumentFactory $vendorDocumentFactory
     * @param VendorDocumentResource $vendorDocumentResource
     * @param VendorRepositoryInterface $vendorRepository
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        VendorDocumentFactory $vendorDocumentFactory,
        VendorDocumentResource $vendorDocumentResource,
        VendorRepositoryInterface $vendorRepository
    ) {
        $this->customerSession = $customerSession;
        $this->vendorDocumentFactory = $vendorDocumentFactory;
        $this->vendorDocumentResource = $vendorDocumentResource;
        $this->vendorRepository = $vendorRepository;
        parent::__construct($context);
    }

    /**
     * Execute delete document action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        // Must be logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $resultRedirect->setPath('customer/account/login');
        }

        $documentId = (int) $this->getRequest()->getParam('id');
        if (!$documentId) {
            $this->messageManager->addErrorMessage(__('Invalid document ID.'));
            return $resultRedirect->setPath('marketplace/seller/profile');
        }

        try {
            // Get the current logged-in vendor
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorRepository->getByCustomerId($customerId);

            if (!$vendor || !$vendor->getId()) {
                $this->messageManager->addErrorMessage(__('You are not a registered vendor.'));
                return $resultRedirect->setPath('marketplace/seller/profile');
            }

            // Load the document
            $document = $this->vendorDocumentFactory->create();
            $this->vendorDocumentResource->load($document, $documentId);

            if (!$document->getId()) {
                $this->messageManager->addErrorMessage(__('The document no longer exists.'));
                return $resultRedirect->setPath('marketplace/seller/profile');
            }

            // Security: confirm this document belongs to the current vendor
            if ((int) $document->getVendorId() !== (int) $vendor->getId()) {
                $this->messageManager->addErrorMessage(__('You do not have permission to delete this document.'));
                return $resultRedirect->setPath('marketplace/seller/profile');
            }

            // Perform the delete
            $this->vendorDocumentResource->delete($document);
            $this->messageManager->addSuccessMessage(__('Document has been deleted successfully.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error deleting document: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('marketplace/seller/profile');
    }
}
