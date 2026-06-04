<?php
namespace Vendor\Ads\Service;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Vendor\Ads\Helper\Config as AdsConfig;

class RazorpayGateway
{
    protected $adsConfig;

    public function __construct(AdsConfig $adsConfig)
    {
        $this->adsConfig = $adsConfig;
    }

    protected function getApi(): Api
    {
        return new Api($this->adsConfig->getRazorpayKeyId(), $this->adsConfig->getRazorpayKeySecret());
    }

    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        $order = $this->getApi()->order->create([
            'receipt' => $receipt,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'payment_capture' => 1,
            'notes' => $notes,
        ]);

        return $order->toArray();
    }

    public function fetchPayment(string $paymentId): array
    {
        $payment = $this->getApi()->payment->fetch($paymentId);
        return $payment->toArray();
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->getApi()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = $this->adsConfig->getRazorpayWebhookSecret();
        if (empty($secret) || empty($signature)) {
            return false;
        }

        try {
            $this->getApi()->utility->verifyWebhookSignature($payload, $signature, $secret);
            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }
}
