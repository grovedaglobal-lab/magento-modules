<?php
namespace Vendor\Marketplace\Controller\Adminhtml\News;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\NewsFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Vendor_Marketplace::manage_news';

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var NewsFactory
     */
    protected $newsFactory;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param NewsFactory $newsFactory
     */
    public function __construct(
        Context $context,
        DataPersistorInterface $dataPersistor,
        NewsFactory $newsFactory
    ) {
        parent::__construct($context);
        $this->dataPersistor = $dataPersistor;
        $this->newsFactory = $newsFactory;
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
            $id = $this->getRequest()->getParam('entity_id');
            $model = $this->newsFactory->create();

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This news no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $model->save();
                $this->messageManager->addSuccessMessage(__('You saved the news.'));
                $this->dataPersistor->clear('vendor_marketplace_news');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the news.'));
            }

            $this->dataPersistor->set('vendor_marketplace_news', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
