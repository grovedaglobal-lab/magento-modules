<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\Ads\Service\RazorpayGateway;
use Vendor\Ads\Service\WalletRechargeProcessor;

class Webhook extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $razorpayGateway;
    protected $walletRechargeProcessor;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RazorpayGateway $razorpayGateway,
        WalletRechargeProcessor $walletRechargeProcessor
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->razorpayGateway = $razorpayGateway;
        $this->walletRechargeProcessor = $walletRechargeProcessor;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $payload = (string)$this->getRequest()->getContent();
        $signature = (string)$this->getRequest()->getHeader('X-Razorpay-Signature');

        if (!$this->razorpayGateway->verifyWebhookSignature($payload, $signature)) {
            return $result->setData(['success' => false, 'message' => __('Invalid webhook signature.')]);
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['event'])) {
            return $result->setData(['success' => false, 'message' => __('Invalid webhook payload.')]);
        }

        $event = (string)$data['event'];
        if (!in_array($event, ['payment.captured', 'order.paid'], true)) {
            return $result->setData(['success' => true, 'message' => __('Event ignored.')]);
        }

        $paymentEntity = $data['payload']['payment']['entity'] ?? null;
        if (!is_array($paymentEntity) && isset($data['payload']['order']['entity']['payments'][0]['entity'])) {
            $paymentEntity = $data['payload']['order']['entity']['payments'][0]['entity'];
        }

        $razorpayOrderId = (string)($paymentEntity['order_id'] ?? '');
        $razorpayPaymentId = (string)($paymentEntity['id'] ?? '');

        if (empty($razorpayOrderId) || empty($razorpayPaymentId)) {
            return $result->setData(['success' => false, 'message' => __('Payment details missing in webhook payload.')]);
        }

        try {
            $processed = $this->walletRechargeProcessor->processCapturedPayment($razorpayOrderId, $razorpayPaymentId);
            return $result->setData(['success' => true, 'message' => __('Webhook processed.'), 'data' => $processed]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
