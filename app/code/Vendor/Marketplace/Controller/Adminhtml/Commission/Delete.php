<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Commission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Delete extends Action
{
    /**
     * @var \Vendor\Marketplace\Model\VendorCommissionFactory
     */
    protected $vendorCommissionFactory;

    /**
     * @param Context $context
     * @param \Vendor\Marketplace\Model\VendorCommissionFactory $vendorCommissionFactory
     */
    public function __construct(
        Context $context,
        \Vendor\Marketplace\Model\VendorCommissionFactory $vendorCommissionFactory
    ) {
        $this->vendorCommissionFactory = $vendorCommissionFactory;
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
                $model = $this->vendorCommissionFactory->create();
                $model->load($id);
                $model->delete();
                $this->messageManager->addSuccessMessage(__('You deleted the commission rule.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a commission rule to delete.'));
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Check permission for action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_Marketplace::manage_commission');
    }
}
