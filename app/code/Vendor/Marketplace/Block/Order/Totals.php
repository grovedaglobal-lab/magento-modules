<?php
namespace Vendor\Marketplace\Block\Order;

use Magento\Sales\Block\Order\Totals as MagentoOrderTotals;

class Totals extends MagentoOrderTotals
{
    /**
     * Get the order from vendor context
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrder()
    {
        // First check if order is already in standard registry
        $order = $this->_coreRegistry->registry('current_order');
        if ($order && $order->getId()) {
            return $order;
        }

        // Try to get from vendor context
        $vendorOrder = $this->_coreRegistry->registry('current_vendor_order');
        if ($vendorOrder && $vendorOrder->getOrderId()) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderFactory = $objectManager->get(\Magento\Sales\Model\OrderFactory::class);
            $order = $orderFactory->create()->load($vendorOrder->getOrderId());
            if ($order->getId()) {
                return $order;
            }
        }

        return null;
    }
}
