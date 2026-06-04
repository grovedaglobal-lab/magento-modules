<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\Vendor as VendorResource;

class Delete extends Action
{
    /**
     * @var VendorFactory
     */
    protected $vendorFactory;

    /**
     * @var VendorResource
     */
    protected $vendorResource;

    /**
     * @param Context $context
     * @param VendorFactory $vendorFactory
     * @param VendorResource $vendorResource
     */
    public function __construct(
        Context $context,
        VendorFactory $vendorFactory,
        VendorResource $vendorResource
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->vendorResource = $vendorResource;
        parent::__construct($context);
    }

    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('entity_id');
        if ($id) {
            try {
                // init model and delete
                $model = $this->vendorFactory->create();
                $this->vendorResource->load($model, $id);
                $this->vendorResource->delete($model);
                
                // display success message
                $this->messageManager->addSuccessMessage(__('The vendor has been deleted.'));
                
                // go to grid
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                // display error message
                $this->messageManager->addErrorMessage($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }
        
        // display error message
        $this->messageManager->addErrorMessage(__('We can\'t find a vendor to delete.'));
        
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Check permission for action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::dashboard');
    }
}
