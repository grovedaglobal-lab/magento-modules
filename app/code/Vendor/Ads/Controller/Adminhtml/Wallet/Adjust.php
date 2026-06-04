<?php
namespace Vendor\Ads\Controller\Adminhtml\Wallet;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Ads\Api\WalletRepositoryInterface;
use Vendor\Ads\Service\WalletRechargeHistoryService;

class Adjust extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_wallet';

    protected $walletRepository;
    protected $walletRechargeHistoryService;

    public function __construct(
        Context $context,
        WalletRepositoryInterface $walletRepository,
        WalletRechargeHistoryService $walletRechargeHistoryService
    ) {
        parent::__construct($context);
        $this->walletRepository = $walletRepository;
        $this->walletRechargeHistoryService = $walletRechargeHistoryService;
    }

    public function execute()
    {
        $vendorId = $this->getRequest()->getParam('vendor_id');
        $type = $this->getRequest()->getParam('type');
        $amount = (float)$this->getRequest()->getParam('amount', 100); // Default or show form
        
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $wallet = $this->walletRepository->getOrCreate($vendorId);
            $oldBalance = $wallet->getBalance();
            
            if ($type == 'add') {
                $wallet->setBalance($oldBalance + $amount);
            } else {
                $wallet->setBalance(max(0, $oldBalance - $amount));
            }

            $newBalance = (float)$wallet->getBalance();
            
            $this->walletRepository->save($wallet);

            if ($type == 'add' && $amount > 0) {
                $this->walletRechargeHistoryService->logRecharge(
                    (int)$vendorId,
                    (float)$amount,
                    (float)$oldBalance,
                    $newBalance,
                    'admin',
                    'Admin adjusted wallet (add balance)'
                );
            }

            $this->messageManager->addSuccessMessage(__('Wallet balance adjusted successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
