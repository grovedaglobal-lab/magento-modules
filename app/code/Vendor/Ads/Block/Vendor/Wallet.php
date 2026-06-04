<?php
namespace Vendor\Ads\Block\Vendor;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Helper\Config as AdsConfig;
use Vendor\Ads\Service\WalletRechargeHistoryService;
use Magento\Framework\App\ResourceConnection;

class Wallet extends Template
{
    protected $customerSession;
    protected $vendorResolver;
    protected $walletRepository;
    protected $walletRechargeHistoryService;
    protected $adsConfig;
    protected $formKey;
    protected $resource;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        \Vendor\Ads\Api\WalletRepositoryInterface $walletRepository,
        AdsConfig $adsConfig,
        FormKey $formKey,
        ResourceConnection $resource,
        array $data = [],
        ?WalletRechargeHistoryService $walletRechargeHistoryService = null
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->vendorResolver  = $vendorResolver;
        $this->walletRepository = $walletRepository;
        $this->adsConfig = $adsConfig;
        $this->formKey = $formKey;
        $this->resource = $resource;
        $this->walletRechargeHistoryService = $walletRechargeHistoryService
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(WalletRechargeHistoryService::class);
    }

    public function getVendorId(): ?int
    {
        return $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
    }

    public function getWallet()
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) {
            return null;
        }

        try {
            return $this->walletRepository->getByVendorId($vendorId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    public function getAddFundsUrl($amount = 100): string
    {
        return $this->getUrl('vendor_ads/vendor/addFunds', ['amount' => $amount]);
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getCreateOrderUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/createOrder');
    }

    public function getVerifyUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/verify');
    }

    public function getCancelPaymentUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/cancelPayment');
    }

    public function getWebhookUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/webhook');
    }

    public function getRazorpayKeyId(): string
    {
        return $this->adsConfig->getRazorpayKeyId();
    }

    public function getTaxMode(): string
    {
        return $this->adsConfig->getWalletRechargeTaxMode();
    }

    public function getRechargeEstimate(float $amount): array
    {
        $amount = max(0, (float)$amount);
        $taxMode = $this->getTaxMode();

        if ($taxMode === 'inclusive') {
            $total = round($amount, 2);
            $base = round($total / 1.18, 2);
            $gst = round($total - $base, 2);
        } else {
            $base = round($amount, 2);
            $gst = round($base * 0.18, 2);
            $total = round($base + $gst, 2);
        }

        return [
            'base_amount' => $base,
            'gst_amount' => $gst,
            'total_amount' => $total,
            'tax_mode' => $taxMode,
        ];
    }

    public function getRechargeHistory(int $limit = 20): array
    {
        $vendorId = (int)$this->getVendorId();
        if ($vendorId <= 0) {
            return [];
        }

        return $this->walletRechargeHistoryService->getVendorHistory($vendorId, $limit);
    }

    /**
     * Check if Razorpay is configured and active
     *
     * @return bool
     */
    public function isRazorpayActive(): bool
    {
        return $this->adsConfig->isRazorpayConfigured();
    }

    /**
     * Check if there is a pending payment in the last 15 minutes
     *
     * @return bool
     */
    public function hasPendingPayment(): bool
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) {
            return false;
        }

        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('vendor_ads_payment');
        $customerId = (int)$this->customerSession->getCustomerId();

        // If the vendor is back on the wallet page with no Razorpay payment id,
        // the checkout popup was abandoned and should not block another attempt.
        $connection->update(
            $tableName,
            ['status' => 'cancelled'],
            [
                'vendor_id = ?' => (int)$vendorId,
                'customer_id = ?' => $customerId,
                'status = ?' => 'pending',
                'razorpay_order_id IS NOT NULL',
                'razorpay_payment_id IS NULL',
            ]
        );
        
        // Find any 'pending' payment for this vendor created in the last 15 minutes
        // We only count it as "in progress" if it has a Razorpay Order ID
        $select = $connection->select()
            ->from($tableName, ['id'])
            ->where('vendor_id = ?', (int)$vendorId)
            ->where('customer_id = ?', $customerId)
            ->where('status = ?', 'pending')
            ->where('razorpay_order_id IS NOT NULL')
            ->where('razorpay_payment_id IS NOT NULL')
            ->where('created_at > ?', date('Y-m-d H:i:s', time() - 900)) // 15 mins
            ->limit(1);
            
        return (bool)$connection->fetchOne($select);
    }
}
