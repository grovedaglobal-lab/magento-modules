<?php
namespace Vendor\Ads\Controller\Adminhtml\Wallet;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class History extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_wallet_history';

    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Vendor Ads - Wallet History'));
        return $resultPage;
    }
}
