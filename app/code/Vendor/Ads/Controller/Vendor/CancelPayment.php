<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Service\WalletRechargeProcessor;

class CancelPayment extends Action implements HttpPostActionInterface
{
    protected $resultJsonFactory;
    protected $customerSession;
    protected $vendorResolver;
    protected $walletRechargeProcessor;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        WalletRechargeProcessor $walletRechargeProcessor
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->vendorResolver = $vendorResolver;
        $this->walletRechargeProcessor = $walletRechargeProcessor;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Please login first.')]);
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $vendorId = (int)$this->vendorResolver->getVendorIdByCustomer($customerId);
        if ($vendorId <= 0) {
            return $result->setData(['success' => false, 'message' => __('Only vendors can cancel payments.')]);
        }

        $orderId = (string)$this->getRequest()->getParam('razorpay_order_id');
        if ($orderId === '') {
            return $result->setData(['success' => false, 'message' => __('Missing Razorpay order ID.')]);
        }

        $cancelled = $this->walletRechargeProcessor->cancelPendingPayment($orderId, $vendorId, $customerId);

        return $result->setData([
            'success' => true,
            'cancelled' => $cancelled,
            'message' => __('Payment cancelled.'),
        ]);
    }
}
