<?php
namespace Vendor\Ads\Service;

use Vendor\Ads\Model\ResourceModel\Wallet as WalletResource;
use Vendor\Ads\Model\WalletFactory;

class WalletService
{
    protected $walletResource;
    protected $walletFactory;

    public function __construct(
        WalletResource $walletResource,
        WalletFactory $walletFactory
    ) {
        $this->walletResource = $walletResource;
        $this->walletFactory = $walletFactory;
    }

    /**
     * Get vendor wallet balance
     */
    public function getBalance($vendorId)
    {
        $wallet = $this->walletFactory->create();
        $this->walletResource->load($wallet, $vendorId);
        return (float)$wallet->getBalance();
    }

    /**
     * Add funds to wallet (simulation)
     */
    public function addFunds($vendorId, $amount)
    {
        $wallet = $this->walletFactory->create();
        $this->walletResource->load($wallet, $vendorId);
        
        if (!$wallet->getVendorId()) {
            $wallet->setVendorId($vendorId);
            $wallet->setBalance(0);
            $wallet->setCurrency('USD');
        }

        $wallet->setBalance($wallet->getBalance() + $amount);
        $this->walletResource->save($wallet);
        return $wallet->getBalance();
    }
}
