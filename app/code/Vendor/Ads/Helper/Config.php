<?php
namespace Vendor\Ads\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    const XML_PATH_ENABLED            = 'vendor_ads/general/enabled';
    const XML_PATH_MAX_SPONSORED      = 'vendor_ads/general/max_sponsored';
    const XML_PATH_MAX_BIDS           = 'vendor_ads/general/max_bids_per_vendor';

    const XML_PATH_VENDOR_MODULE      = 'vendor_ads/vendor_integration/vendor_module';
    const XML_PATH_VENDOR_TABLE       = 'vendor_ads/vendor_integration/vendor_table';
    const XML_PATH_VENDOR_ID_COL      = 'vendor_ads/vendor_integration/vendor_id_column';
    const XML_PATH_VENDOR_NAME_COL    = 'vendor_ads/vendor_integration/vendor_name_column';
    const XML_PATH_CUSTOMER_ID_COL    = 'vendor_ads/vendor_integration/customer_id_column';

    const XML_PATH_MIN_BID            = 'vendor_ads/cpc/min_bid';
    const XML_PATH_DEFAULT_CURRENCY   = 'vendor_ads/cpc/default_currency';
    const XML_PATH_GST_TAX_CLASS_ID   = 'vendor_ads/wallet_recharge/gst_tax_class_id';
    const XML_PATH_TAX_MODE           = 'vendor_ads/wallet_recharge/tax_mode';
    const XML_PATH_RECHARGE_SKU       = 'vendor_ads/wallet_recharge/recharge_product_sku';

    const XML_PATH_RZP_KEY_ID         = 'payment/razorpay/key_id';
    const XML_PATH_RZP_KEY_SECRET     = 'payment/razorpay/key_secret';
    const XML_PATH_RZP_MODE           = 'payment/razorpay/mode';
    const XML_PATH_RZP_MERCHANT_NAME  = 'payment/razorpay/merchant_name';
    const XML_PATH_RZP_WEBHOOK_SECRET = 'payment/razorpay/webhook_secret';

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getMaxSponsored(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_SPONSORED, ScopeInterface::SCOPE_STORE) ?: 3;
    }

    public function getMaxBidsPerVendor(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_BIDS, ScopeInterface::SCOPE_STORE) ?: 50;
    }

    public function getVendorModule(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_VENDOR_MODULE) ?: 'custom_marketplace';
    }

    public function getVendorTable(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_VENDOR_TABLE) ?: 'vendor_entity';
    }

    public function getVendorIdColumn(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_VENDOR_ID_COL) ?: 'entity_id';
    }

    public function getVendorNameColumn(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_VENDOR_NAME_COL) ?: 'name';
    }

    public function getCustomerIdColumn(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_CUSTOMER_ID_COL) ?: 'customer_id';
    }

    public function getMinBid(): float
    {
        return (float)$this->scopeConfig->getValue(self::XML_PATH_MIN_BID) ?: 0.01;
    }

    public function getDefaultCurrency(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_CURRENCY) ?: 'USD';
    }

    public function getWalletRechargeTaxClassId(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_GST_TAX_CLASS_ID, ScopeInterface::SCOPE_STORE);
    }

    public function getWalletRechargeTaxMode(): string
    {
        $mode = (string)$this->scopeConfig->getValue(self::XML_PATH_TAX_MODE, ScopeInterface::SCOPE_STORE);
        return in_array($mode, ['exclusive', 'inclusive'], true) ? $mode : 'exclusive';
    }

    public function getWalletRechargeProductSku(): string
    {
        $sku = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RECHARGE_SKU, ScopeInterface::SCOPE_STORE));
        return $sku !== '' ? $sku : 'wallet-recharge';
    }

    public function getRazorpayKeyId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_RZP_KEY_ID, ScopeInterface::SCOPE_STORE);
    }

    public function getRazorpayKeySecret(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_RZP_KEY_SECRET, ScopeInterface::SCOPE_STORE);
    }

    public function getRazorpayMode(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_RZP_MODE, ScopeInterface::SCOPE_STORE) ?: 'test';
    }

    public function getRazorpayMerchantName(): string
    {
        $name = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RZP_MERCHANT_NAME, ScopeInterface::SCOPE_STORE));
        return $name !== '' ? $name : 'Magento';
    }

    public function getRazorpayWebhookSecret(): string
    {
        $secret = (string)$this->scopeConfig->getValue(self::XML_PATH_RZP_WEBHOOK_SECRET, ScopeInterface::SCOPE_STORE);
        if (!empty($secret)) {
            return $secret;
        }
        return $this->getRazorpayKeySecret();
    }

    /**
     * Check if Razorpay is configured with keys
     *
     * @return bool
     */
    public function isRazorpayConfigured(): bool
    {
        return !empty($this->getRazorpayKeyId()) && !empty($this->getRazorpayKeySecret());
    }
}
