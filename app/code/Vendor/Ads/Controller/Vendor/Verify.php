<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Service\RazorpayGateway;
use Vendor\Ads\Service\WalletRechargeProcessor;

class Verify extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $customerSession;
    protected $vendorResolver;
    protected $razorpayGateway;
    protected $walletRechargeProcessor;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        RazorpayGateway $razorpayGateway,
        WalletRechargeProcessor $walletRechargeProcessor
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->vendorResolver = $vendorResolver;
        $this->razorpayGateway = $razorpayGateway;
        $this->walletRechargeProcessor = $walletRechargeProcessor;
        $this->logger = ObjectManager::getInstance()->get(LoggerInterface::class);
    }

    public function createCsrfValidationException(RequestInterface $request): ?\Magento\Framework\App\Request\InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $hasRazorpayParams = (bool)$request->getParam('razorpay_order_id')
            && (bool)$request->getParam('razorpay_payment_id')
            && (bool)$request->getParam('razorpay_signature');

        if ($hasRazorpayParams) {
            return true;
        }

        return null;
    }

    public function execute()
    {
        file_put_contents('/tmp/ads_debug.log', "DEBUG: Verify controller started\n", FILE_APPEND);
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->customerSession->isLoggedIn()) {
                return $result->setData(['success' => false, 'message' => __('Please login first.')]);
            }

            $customerId = (int)$this->customerSession->getCustomerId();
            $vendorId = (int)$this->vendorResolver->getVendorIdByCustomer($customerId);
            if ($vendorId <= 0) {
                return $result->setData(['success' => false, 'message' => __('Only vendors can verify payments.')]);
            }

            $orderId = (string)$this->getRequest()->getParam('razorpay_order_id');
            $paymentId = (string)$this->getRequest()->getParam('razorpay_payment_id');
            $signature = (string)$this->getRequest()->getParam('razorpay_signature');

            $this->logger->info('Vendor wallet verify request received', [
                'customer_id' => $customerId,
                'vendor_id' => $vendorId,
                'has_order_id' => $orderId !== '',
                'has_payment_id' => $paymentId !== '',
                'has_signature' => $signature !== '',
            ]);

            if (empty($orderId) || empty($paymentId) || empty($signature)) {
                return $result->setData(['success' => false, 'message' => __('Missing Razorpay verification parameters.')]);
            }

            if (!$this->razorpayGateway->verifyPaymentSignature($orderId, $paymentId, $signature)) {
                return $result->setData(['success' => false, 'message' => __('Invalid payment signature.')]);
            }

            $processed = $this->walletRechargeProcessor->processCapturedPayment($orderId, $paymentId, $vendorId, $customerId);
            return $result->setData(['success' => true, 'message' => __('Payment verified successfully.'), 'data' => $processed]);
        } catch (\Throwable $e) {
            $this->logger->error('Vendor wallet payment verify failed', [
                'exception' => $e,
                'message' => $e->getMessage(),
            ]);
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
