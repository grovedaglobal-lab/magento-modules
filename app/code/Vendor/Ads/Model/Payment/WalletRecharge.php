<?php
namespace Vendor\Ads\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;
use Vendor\Ads\Helper\Config as AdsConfig;

class WalletRecharge extends AbstractMethod
{
    public const METHOD_CODE = 'vendor_ads_wallet_recharge';

    protected $_code = self::METHOD_CODE;
    protected $_isOffline = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;

    private $adsConfig;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        AdsConfig $adsConfig,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->adsConfig = $adsConfig;
    }

    public function isAvailable(?CartInterface $quote = null)
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }

        if ($quote === null) {
            return true;
        }

        $targetSku = $this->adsConfig->getWalletRechargeProductSku();
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((string)$item->getSku() === $targetSku) {
                return true;
            }
        }

        return false;
    }
}
