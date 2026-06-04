<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Document;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Vendor\Marketplace\Model\ResourceModel\VendorDocument\CollectionFactory;

class MassReject extends Action
{
    protected $filter;
    protected $collectionFactory;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::manage_documents');
    }

    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            foreach ($collection as $document) {
                $document->setStatus(2); // 2 = Rejected
                $document->save();
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been rejected.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error rejecting documents: %1', $e->getMessage()));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index');
    }
}
