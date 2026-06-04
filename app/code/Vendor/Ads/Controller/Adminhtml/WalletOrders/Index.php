<?php
declare(strict_types=1);

namespace Vendor\Ads\Controller\Adminhtml\WalletOrders;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Vendor_Ads::wallet_orders';

    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Ads::wallet_orders');
        $resultPage->getConfig()->getTitle()->prepend(__('Wallet Orders'));

        return $resultPage;
    }
}
