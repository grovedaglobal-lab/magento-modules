<?php
namespace Vendor\Ads\Block\Adminhtml\Wallet;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Service\WalletRechargeHistoryService;

class History extends Template
{
    protected $vendorResolver;
    protected $walletRechargeHistoryService;

    public function __construct(
        Context $context,
        VendorResolverInterface $vendorResolver,
        array $data = [],
        ?WalletRechargeHistoryService $walletRechargeHistoryService = null
    ) {
        parent::__construct($context, $data);
        $this->vendorResolver = $vendorResolver;
        $this->walletRechargeHistoryService = $walletRechargeHistoryService
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(WalletRechargeHistoryService::class);
    }

    public function getRechargeHistory(int $limit = 300): array
    {
        $rows = $this->walletRechargeHistoryService->getAllHistory($limit);

        foreach ($rows as &$row) {
            $row['vendor_name'] = $this->vendorResolver->getVendorName((int)$row['vendor_id']) ?? 'N/A';
        }

        return $rows;
    }
}
