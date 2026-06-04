<?php
namespace Vendor\Ads\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Ads\Service\BillingService;
use Magento\Checkout\Model\Session as CheckoutSession;

class SalesOrderPlaceAfter implements ObserverInterface
{
    /** @var BillingService */
    protected $billingService;

    /** @var CheckoutSession */
    protected $checkoutSession;

    public function __construct(
        BillingService $billingService,
        CheckoutSession $checkoutSession
    ) {
        $this->billingService = $billingService;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $sponsoredBids = $this->checkoutSession->getSponsoredBids();

        if (empty($sponsoredBids)) {
            return;
        }

        foreach ($order->getAllVisibleItems() as $item) {
            $productId = $item->getProductId();
            if (isset($sponsoredBids[$productId])) {
                $bidId = $sponsoredBids[$productId];
                $this->billingService->trackConversion($bidId);
            }
        }

        // Clear session data after successful tracking (or keep it for the session?)
        // Usually, one click leads to one set of potential conversions in that session.
    }
}
