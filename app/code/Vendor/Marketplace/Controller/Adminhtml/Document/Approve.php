<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Document;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\VendorDocumentFactory;

class Approve extends Action
{
    protected $documentFactory;

    public function __construct(
        Context $context,
        VendorDocumentFactory $documentFactory
    ) {
        parent::__construct($context);
        $this->documentFactory = $documentFactory;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::manage_documents');
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id');
        if ($id) {
            try {
                $document = $this->documentFactory->create()->load($id);
                if ($document->getId()) {
                    $document->setStatus(1); // 1 = Approved
                    $document->save();
                    $this->messageManager->addSuccessMessage(__('Document has been approved.'));
                } else {
                    $this->messageManager->addErrorMessage(__('Document not found.'));
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error approving document: %1', $e->getMessage()));
            }
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index');
    }
}
