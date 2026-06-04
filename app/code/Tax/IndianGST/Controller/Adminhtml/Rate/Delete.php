<?php
declare(strict_types=1);

namespace Tax\IndianGST\Controller\Adminhtml\Rate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tax\IndianGST\Model\RateFactory;
use Tax\IndianGST\Model\ResourceModel\Rate as RateResource;

class Delete extends Action
{
    /**
     * @var RateFactory
     */
    protected $rateFactory;

    /**
     * @var RateResource
     */
    protected $rateResource;

    /**
     * @param Context $context
     * @param RateFactory $rateFactory
     * @param RateResource $rateResource
     */
    public function __construct(
        Context $context,
        RateFactory $rateFactory,
        RateResource $rateResource
    ) {
        parent::__construct($context);
        $this->rateFactory = $rateFactory;
        $this->rateResource = $rateResource;
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
        $id = $this->getRequest()->getParam('rate_id');

        if ($id) {
            try {
                $model = $this->rateFactory->create();
                $this->rateResource->load($model, $id);
                $this->rateResource->delete($model);
                $this->messageManager->addSuccessMessage(__('You deleted the GST rate.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['rate_id' => $id]);
            }
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a rate to delete.'));
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Check permission
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Tax_IndianGST::rates');
    }
}
