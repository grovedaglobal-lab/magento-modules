<?php
declare(strict_types=1);

namespace Tax\IndianGST\Controller\Adminhtml\Vendor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Tax\IndianGST\Model\VendorProfileFactory;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var VendorProfileFactory
     */
    protected $vendorProfileFactory;

    /**
     * @var \Tax\IndianGST\Model\ResourceModel\VendorProfile
     */
    protected $vendorProfileResource;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param VendorProfileFactory $vendorProfileFactory
     * @param \Tax\IndianGST\Model\ResourceModel\VendorProfile $vendorProfileResource
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        DataPersistorInterface $dataPersistor,
        VendorProfileFactory $vendorProfileFactory,
        \Tax\IndianGST\Model\ResourceModel\VendorProfile $vendorProfileResource,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->vendorProfileResource = $vendorProfileResource;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Save Vendor
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $postData = $this->getRequest()->getPostValue();

        // Extract data from nested 'data' key (from form dataScope)
        $data = isset($postData['data']) ? $postData['data'] : $postData;

        $this->logger->info('Raw POST data received', [
            'post_data' => $postData,
            'extracted_data' => $data
        ]);

        if ($data) {
            $id = $this->getRequest()->getParam('entity_id');
            $model = $this->vendorProfileFactory->create();

            if ($id) {
                $this->vendorProfileResource->load($model, $id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This vendor no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            // Handle signature image data from uploader
            if (isset($data['signature']) && is_array($data['signature'])) {
                if (isset($data['signature'][0]['name'])) {
                    $data['signature'] = $data['signature'][0]['name'];
                } else {
                    $data['signature'] = null;
                }
            }

            $model->setData($data);

            // Remove empty entity_id to allow auto-increment
            if ($model->getData('entity_id') === '' || $model->getData('entity_id') === null) {
                $model->unsetData('entity_id');
            }

            // Log the data being saved
            $this->logger->info('Attempting to save vendor profile', [
                'data' => $data,
                'model_id_before' => $model->getId(),
                'model_data_before' => $model->getData(),
                'has_data_changes' => $model->hasDataChanges()
            ]);

            try {
                // Verify model has required data
                if (empty($model->getVendorCode())) {
                    throw new LocalizedException(__('Vendor Code is required'));
                }

                $this->logger->info('About to call resource save', [
                    'vendor_code' => $model->getVendorCode(),
                    'business_name' => $model->getBusinessName()
                ]);

                $this->vendorProfileResource->save($model);

                // Log successful save with actual ID
                $savedId = $model->getId();
                $this->logger->info('Vendor profile saved successfully', [
                    'entity_id' => $savedId,
                    'vendor_code' => $model->getVendorCode(),
                    'model_data_after' => $model->getData()
                ]);

                if (!$savedId) {
                    $this->logger->error('Save succeeded but entity_id is still empty!', [
                        'model_debug' => $model->debug()
                    ]);
                    throw new LocalizedException(__('Failed to save vendor - no ID returned'));
                }

                $this->messageManager->addSuccessMessage(__('You saved the vendor.'));
                $this->dataPersistor->clear('indiangst_vendor_profile');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->logger->error('LocalizedException saving vendor: ' . $e->getMessage(), [
                    'exception' => $e,
                    'data' => $data,
                    'trace' => $e->getTraceAsString()
                ]);
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error('Exception saving vendor: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                    'data' => $data
                ]);
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the vendor.'));
            }

            $this->dataPersistor->set('indiangst_vendor_profile', $data);
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
