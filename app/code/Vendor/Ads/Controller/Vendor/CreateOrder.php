<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Helper\Config as AdsConfig;
use Vendor\Ads\Service\WalletRechargeProcessor;

class CreateOrder extends Action
{
    protected $resultJsonFactory;
    protected $customerSession;
    protected $vendorResolver;
    protected $walletRechargeProcessor;
    protected $adsConfig;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        WalletRechargeProcessor $walletRechargeProcessor,
        AdsConfig $adsConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->vendorResolver = $vendorResolver;
        $this->walletRechargeProcessor = $walletRechargeProcessor;
        $this->adsConfig = $adsConfig;
    }

    public function execute()
    {
        \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->info('Create Razorpay Order request started');
        $result = $this->resultJsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Please login first.')]);
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $vendorId = (int)$this->vendorResolver->getVendorIdByCustomer($customerId);
        if ($vendorId <= 0) {
            return $result->setData(['success' => false, 'message' => __('Only vendors can recharge wallet.')]);
        }

        $amount = (float)$this->getRequest()->getParam('amount');

        try {
            $payment = $this->walletRechargeProcessor->createPendingPayment($vendorId, $customerId, $amount);

            return $result->setData([
                'success' => true,
                'message' => __('Order created successfully.'),
                'payment_ref' => $payment['id'],
                'razorpay_order_id' => $payment['razorpay_order_id'],
                'key_id' => $this->adsConfig->getRazorpayKeyId(),
                'merchant_name' => $this->adsConfig->getRazorpayMerchantName(),
                'mode' => $this->adsConfig->getRazorpayMode(),
                'currency' => 'INR',
                'amount_paise' => $payment['total_amount_paise'],
                'base_amount' => $payment['base_amount'],
                'gst_amount' => $payment['gst_amount'],
                'total_amount' => $payment['total_amount'],
                'tax_mode' => $payment['tax_mode'],
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
