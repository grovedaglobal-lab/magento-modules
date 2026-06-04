<?php
namespace Vendor\Ads\Controller\Adminhtml\Keyword;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Details extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_keywords';

    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $queryId = $this->getRequest()->getParam('query_id');
        if (!$queryId) {
            $this->messageManager->addErrorMessage(__('Query ID is required.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Keyword Details'));
        return $resultPage;
    }
}
