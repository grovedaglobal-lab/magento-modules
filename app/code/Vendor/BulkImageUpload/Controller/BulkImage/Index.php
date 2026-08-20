<?php
namespace Vendor\BulkImageUpload\Controller\BulkImage;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractVendor
{
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context, $vendorSession);
    }

    public function execute()
    {
        // Session validation is handled by AbstractVendor dispatch
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Bulk Image Upload'));
        return $resultPage;
    }
}
