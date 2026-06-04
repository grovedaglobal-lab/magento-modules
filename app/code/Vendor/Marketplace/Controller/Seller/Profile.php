<?php
namespace Vendor\Marketplace\Controller\Seller;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Marketplace\Model\VendorFactory;

class Profile extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $customerSession;
    protected $vendorRepository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CustomerSession $customerSession,
        \Vendor\Marketplace\Api\VendorRepositoryInterface $vendorRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->vendorRepository = $vendorRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        // Check if customer is logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        // Check if customer is a vendor
        $customerId = $this->customerSession->getCustomerId();
        try {
            $vendor = $this->vendorRepository->getByCustomerId($customerId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->resultRedirectFactory->create()->setPath('vendor_marketplace/account/create');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Edit Seller Profile'));
        return $resultPage;
    }
}
