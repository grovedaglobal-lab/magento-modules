<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;

class Report extends Action
{
    protected $resultPageFactory;
    protected $customerSession;
    protected $vendorResolver;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->vendorResolver = $vendorResolver;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $vendorId = $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
        if (!$vendorId) {
            return $this->_redirect('marketplace/vendor/register');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Advertising Performance Report'));
        return $resultPage;
    }
}
