<?php
namespace Vendor\Marketplace\Controller\Shipping;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;

class Index extends AbstractVendor implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param VendorSession $vendorSession
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context, $vendorSession);
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Shipping Settings'));
        return $resultPage;
    }
}
