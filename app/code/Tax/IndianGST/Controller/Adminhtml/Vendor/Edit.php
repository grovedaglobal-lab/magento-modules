<?php
declare(strict_types=1);

namespace Tax\IndianGST\Controller\Adminhtml\Vendor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Tax\IndianGST\Model\VendorProfileFactory;

class Edit extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var VendorProfileFactory
     */
    protected $vendorProfileFactory;

    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param VendorProfileFactory $vendorProfileFactory
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        VendorProfileFactory $vendorProfileFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->vendorProfileFactory = $vendorProfileFactory;
        parent::__construct($context);
    }

    /**
     * Edit Vendor
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('entity_id');
        $model = $this->vendorProfileFactory->create();

        // 2. Initial checking
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This vendor no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->_coreRegistry->register('indiangst_vendor_profile', $model);

        // 3. Build Edit Page
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Tax_IndianGST::vendors')
            ->addBreadcrumb(__('Manage Vendors'), __('Manage Vendors'))
            ->addBreadcrumb($id ? __('Edit Vendor') : __('New Vendor'), $id ? __('Edit Vendor') : __('New Vendor'));
        $resultPage->getConfig()->getTitle()->prepend(__('Vendors'));
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? $model->getBusinessName() : __('New Vendor'));

        return $resultPage;
    }
}
