<?php
namespace Vendor\Ads\Plugin\Sales\Block\Order;

class InvoicePrintPlugin
{
    public function beforeToHtml($subject)
    {
        if (method_exists($subject, 'getOrder')) {
            $order = $subject->getOrder();
            if ($order && $order->getId() && current(explode('_', $order->getIncrementId() ?? '')) !== 'inv') {
                if ($order->getData('is_wallet_recharge')) {
                    // Check if current template is an invoice template
                    $currentTemplate = $subject->getTemplate();
                    if (strpos($currentTemplate, 'invoice.phtml') !== false) {
                        $subject->setTemplate('Vendor_Ads::order/print/wallet_invoice.phtml');
                    }
                    if (strpos($currentTemplate, 'order/view.phtml') !== false) {
                        $subject->setTemplate('Vendor_Ads::order/wallet_view.phtml');
                    }
                }
            }
        }
        return [];
    }
}
