<?php
namespace Vendor\Ads\Block\Vendor;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Magento\Framework\App\ResourceConnection;

class Notification extends Template
{
    protected $customerSession;
    protected $vendorResolver;
    protected $resource;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->vendorResolver  = $vendorResolver;
        $this->resource        = $resource;
    }

    public function getVendorId(): ?int
    {
        return $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
    }

    public function getWalletInfo(): ?array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) {
            return null;
        }

        $connection  = $this->resource->getConnection();
        $walletTable = $this->resource->getTableName('vendor_ads_wallet');
        $bidTable    = $this->resource->getTableName('vendor_ads_bid');

        // Check if vendor has any active bids that are suspended due to balance
        $activeBidsCount = (int)$connection->fetchOne(
            $connection->select()
                ->from($bidTable, ['COUNT(*)'])
                ->where('vendor_id = ?', $vendorId)
                ->where('is_active = 1')
        );

        if ($activeBidsCount === 0) {
            return null;
        }

        $wallet = $connection->fetchRow(
            $connection->select()
                ->from($walletTable)
                ->where('vendor_id = ?', $vendorId)
        );

        if (!$wallet || (float)$wallet['balance'] <= 0) {
            return [
                'type' => 'error',
                'message' => __('Your Ad Wallet balance is empty! Your active advertisements are currently suspended.'),
                'action_label' => __('Add Funds'),
                'action_url' => $this->getUrl('vendor_ads/vendor/wallet')
            ];
        }

        if ((float)$wallet['balance'] < 10) { // Low balance threshold
            return [
                'type' => 'warning',
                'message' => __('Your Ad Wallet balance is low (%1 %2). Add funds soon to avoid ad suspension.', $wallet['balance'], $wallet['currency']),
                'action_label' => __('Top up'),
                'action_url' => $this->getUrl('vendor_ads/vendor/wallet')
            ];
        }

        return null;
    }
}
