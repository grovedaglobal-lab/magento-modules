<?php
namespace Vendor\Marketplace\Controller\Adminhtml\News;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Marketplace\Model\NewsFactory;

class Delete extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Vendor_Marketplace::manage_news';

    /**
     * @var NewsFactory
     */
    protected $newsFactory;

    /**
     * @param Context $context
     * @param NewsFactory $newsFactory
     */
    public function __construct(
        Context $context,
        NewsFactory $newsFactory
    ) {
        parent::__construct($context);
        $this->newsFactory = $newsFactory;
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
        $id = $this->getRequest()->getParam('id');

        if ($id) {
            try {
                $model = $this->newsFactory->create();
                $model->load($id);
                $model->delete();
                $this->messageManager->addSuccessMessage(__('You deleted the news.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a news to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
