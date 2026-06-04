<?php
namespace Vendor\Marketplace\Controller\Adminhtml\Commission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;

class Edit extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @var \Vendor\Marketplace\Model\VendorCommissionFactory
     */
    protected $vendorCommissionFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Registry $coreRegistry
     * @param \Vendor\Marketplace\Model\VendorCommissionFactory $vendorCommissionFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        \Vendor\Marketplace\Model\VendorCommissionFactory $vendorCommissionFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->vendorCommissionFactory = $vendorCommissionFactory;
        parent::__construct($context);
    }

    /**
     * Edit action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id');
        $model = $this->vendorCommissionFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This commission rule no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->coreRegistry->register('vendor_marketplace_commission', $model);

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Marketplace::manage_commission');
        $resultPage->getConfig()->getTitle()->prepend(__('Commission'));
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? __('Edit Commission Rule') : __('New Commission Rule'));

        return $resultPage;
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
