<?php
namespace Vendor\Ads\Controller\Adminhtml\Bid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class VersionHistory extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_bids';

    public function __construct(
        Context $context,
        private PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $parentAdId = (int)$this->getRequest()->getParam('parent_ad_id');
        if (!$parentAdId) {
            $this->messageManager->addErrorMessage(__('Invalid ad ID.'));
            return $this->resultRedirectFactory->create()->setPath('vendor_ads/bid/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Ad Version History'));
        return $resultPage;
    }
}
