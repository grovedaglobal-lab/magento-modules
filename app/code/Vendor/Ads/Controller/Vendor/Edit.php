<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Api\BidRepositoryInterface;

class Edit extends Action
{
    protected $resultPageFactory;
    protected $customerSession;
    protected $vendorResolver;
    protected $bidRepository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        BidRepositoryInterface $bidRepository
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession   = $customerSession;
        $this->vendorResolver    = $vendorResolver;
        $this->bidRepository     = $bidRepository;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $vendorId = $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
        if (!$vendorId) {
            $this->messageManager->addErrorMessage(__('You must be a registered vendor to access this page.'));
            return $this->_redirect('marketplace/seller/dashboard');
        }

        $bidId = $this->getRequest()->getParam('id');
        if (!$bidId) {
            return $this->_redirect('vendor_ads/vendor/index');
        }

        try {
            $bid = $this->bidRepository->getById($bidId);
            if ($bid->getVendorId() != $vendorId) {
                $this->messageManager->addErrorMessage(__('You do not have permission to edit this advertisement.'));
                return $this->_redirect('vendor_ads/vendor/index');
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Advertisement not found.'));
            return $this->_redirect('vendor_ads/vendor/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Edit Advertisement #%1', $bidId));
        return $resultPage;
    }
}
