<?php
namespace Vendor\Ads\Model;

use Vendor\Ads\Api\WalletRepositoryInterface;
use Vendor\Ads\Api\Data\WalletInterface;
use Vendor\Ads\Model\ResourceModel\Wallet as ResourceWallet;
use Vendor\Ads\Model\WalletFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;

class WalletRepository implements WalletRepositoryInterface
{
    protected $resource;
    protected $walletFactory;

    public function __construct(
        ResourceWallet $resource,
        WalletFactory $walletFactory
    ) {
        $this->resource = $resource;
        $this->walletFactory = $walletFactory;
    }

    public function save(WalletInterface $wallet)
    {
        try {
            $this->resource->save($wallet);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $wallet;
    }

    public function getByVendorId(int $vendorId)
    {
        $wallet = $this->walletFactory->create();
        $this->resource->load($wallet, $vendorId, 'vendor_id');
        if (!$wallet->getId()) {
            throw new NoSuchEntityException(__('Wallet for vendor %1 does not exist.', $vendorId));
        }
        return $wallet;
    }

    public function getOrCreate(int $vendorId)
    {
        try {
            return $this->getByVendorId($vendorId);
        } catch (NoSuchEntityException $e) {
            $wallet = $this->walletFactory->create();
            $wallet->setVendorId($vendorId);
            $wallet->setBalance(0.00);
            $wallet->setCurrency('INR'); // Default to INR since user is in India
            $this->save($wallet);
            return $wallet;
        }
    }
}
