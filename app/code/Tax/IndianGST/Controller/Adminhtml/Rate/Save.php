<?php
declare(strict_types=1);

namespace Tax\IndianGST\Controller\Adminhtml\Rate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tax\IndianGST\Model\RateFactory;
use Tax\IndianGST\Model\ResourceModel\Rate as RateResource;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
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
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('rate_id');
            $model = $this->rateFactory->create();

            if ($id) {
                $this->rateResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This rate no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            // Calculate Total Rate automatically if not set or user wants auto-calc
            if (isset($data['cgst_rate']) && isset($data['sgst_rate']) && isset($data['igst_rate'])) {
                $cess = isset($data['cess_rate']) ? (float) $data['cess_rate'] : 0;
                $data['total_rate'] = (float) $data['igst_rate'] + $cess; // Usually IGST is the full tax rate (CGST+SGST = IGST)
            }

            $model->setData($data);

            try {
                $this->rateResource->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the GST rate.'));
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['rate_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Something went wrong while saving the rate.'));
            }

            return $resultRedirect->setPath('*/*/edit', ['rate_id' => $id]);
        }
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
