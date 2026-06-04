<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Vendor\Ads\Api\WalletRepositoryInterface;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Service\WalletRechargeHistoryService;
use Magento\Customer\Model\Session as CustomerSession;

class AddFunds extends Action
{
    protected $walletRepository;
    protected $vendorResolver;
    protected $customerSession;
    protected $walletRechargeHistoryService;

    public function __construct(
        Context $context,
        WalletRepositoryInterface $walletRepository,
        VendorResolverInterface $vendorResolver,
        CustomerSession $customerSession,
        WalletRechargeHistoryService $walletRechargeHistoryService
    ) {
        parent::__construct($context);
        $this->walletRepository = $walletRepository;
        $this->vendorResolver = $vendorResolver;
        $this->customerSession = $customerSession;
        $this->walletRechargeHistoryService = $walletRechargeHistoryService;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $this->messageManager->addErrorMessage(__('Direct wallet credit is disabled. Please use Razorpay recharge.'));

        return $this->_redirect('vendor_ads/vendor/wallet');
    }
}
