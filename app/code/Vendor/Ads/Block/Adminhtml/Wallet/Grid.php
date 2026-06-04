<?php
namespace Vendor\Ads\Block\Adminhtml\Wallet;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Service\WalletRechargeHistoryService;

class Grid extends Template
{
    protected $resource;
    protected $vendorResolver;
    protected $walletRechargeHistoryService;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        VendorResolverInterface $vendorResolver,
        array $data = [],
        ?WalletRechargeHistoryService $walletRechargeHistoryService = null
    ) {
        parent::__construct($context, $data);
        $this->resource       = $resource;
        $this->vendorResolver = $vendorResolver;
        $this->walletRechargeHistoryService = $walletRechargeHistoryService
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(WalletRechargeHistoryService::class);
    }

    /**
     * Get wallets. Vendor name is resolved dynamically via the configured vendor module.
     */
    public function getWalletData()
    {
        $connection  = $this->resource->getConnection();
        $walletTable = $this->resource->getTableName('vendor_ads_wallet');

        $select = $connection->select()
            ->from(['w' => $walletTable], ['vendor_id', 'balance', 'currency', 'updated_at'])
            ->order('w.vendor_id ASC');

        $rows = $connection->fetchAll($select);

        foreach ($rows as &$row) {
            $row['vendor_name'] = $this->vendorResolver->getVendorName((int)$row['vendor_id']) ?? 'N/A';
        }

        return $rows;
    }

    public function getRechargeHistory(int $limit = 100): array
    {
        $rows = $this->walletRechargeHistoryService->getAllHistory($limit);

        foreach ($rows as &$row) {
            $row['vendor_name'] = $this->vendorResolver->getVendorName((int)$row['vendor_id']) ?? 'N/A';
        }

        return $rows;
    }
}
