<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Document;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\VendorDocumentFactory;

class Hide extends Action
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
                    $document->setIsVisible(0); // 0 = Hidden
                    $document->save();
                    $this->messageManager->addSuccessMessage(__('Document is now hidden on the frontend.'));
                } else {
                    $this->messageManager->addErrorMessage(__('Document not found.'));
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error hiding document: %1', $e->getMessage()));
            }
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
