<?php
declare(strict_types=1);

namespace Tax\IndianGST\Controller\Adminhtml\Vendor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tax\IndianGST\Model\VendorProfileFactory;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action
{
    /**
     * @var VendorProfileFactory
     */
    protected $vendorProfileFactory;

    /**
     * @param Context $context
     * @param VendorProfileFactory $vendorProfileFactory
     */
    public function __construct(
        Context $context,
        VendorProfileFactory $vendorProfileFactory
    ) {
        $this->vendorProfileFactory = $vendorProfileFactory;
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
                $model = $this->vendorProfileFactory->create();
                $model->load($id);
                $model->delete();
                $this->messageManager->addSuccessMessage(__('You deleted the vendor.'));
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the vendor.'));
            }
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a vendor to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
