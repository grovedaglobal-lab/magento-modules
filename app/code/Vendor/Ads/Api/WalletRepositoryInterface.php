<?php
namespace Vendor\Ads\Api;

use Vendor\Ads\Api\Data\WalletInterface;

interface WalletRepositoryInterface
{
    /**
     * @param WalletInterface $wallet
     * @return WalletInterface
     */
    public function save(WalletInterface $wallet);

    /**
     * @param int $vendorId
     * @return WalletInterface
     */
    public function getByVendorId(int $vendorId);

    /**
     * @param int $vendorId
     * @return WalletInterface
     */
    public function getOrCreate(int $vendorId);
}
