<?php
namespace Vendor\Marketplace\Block\Order;

use Magento\Sales\Block\Order\Info as MagentoOrderInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Address\Renderer as AddressRenderer;
use Magento\Sales\Model\OrderFactory;

class Info extends MagentoOrderInfo
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param PaymentHelper $paymentHelper
     * @param AddressRenderer $addressRenderer
     * @param OrderFactory $orderFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        PaymentHelper $paymentHelper,
        AddressRenderer $addressRenderer,
        OrderFactory $orderFactory,
        array $data = []
    ) {
        $this->orderFactory = $orderFactory;
        parent::__construct($context, $registry, $paymentHelper, $addressRenderer, $data);
    }

    /**
     * Get the order from vendor context
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrder()
    {
        // First check if order is already in standard registry
        $order = $this->coreRegistry->registry('current_order');
        if ($order && $order->getId()) {
            return $order;
        }

        // Try to get from vendor context
        $vendorOrder = $this->coreRegistry->registry('current_vendor_order');
        if ($vendorOrder && $vendorOrder->getOrderId()) {
            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
            if ($order->getId()) {
                // Register it for other blocks
                if (!$this->coreRegistry->registry('current_order')) {
                    $this->coreRegistry->register('current_order', $order);
                }
                return $order;
            }
        }

        return null;
    }

    /**
     * Prepare layout - override to handle null order gracefully
     */
    protected function _prepareLayout()
    {
        $order = $this->getOrder();
        if ($order && $order->getId()) {
            $this->pageConfig->getTitle()->set(__('Order # %1', $order->getRealOrderId()));
            if ($order->getPayment()) {
                $infoBlock = $this->paymentHelper->getInfoBlock($order->getPayment(), $this->getLayout());
                $this->setChild('payment_info', $infoBlock);
            }
        }

        return $this;
    }

    /**
     * Resolve payment method title with safe fallback for COD and other methods.
     */
    public function getResolvedPaymentMethodTitle(): string
    {
        $order = $this->getOrder();
        if (!$order || !$order->getPayment()) {
            return (string)__('Payment information is not available.');
        }

        $payment = $order->getPayment();
        $methodCode = (string)$payment->getMethod();

        // In this marketplace flow, checkmo is used as COD in some storefront setups.
        if ($methodCode === 'cashondelivery' || $methodCode === 'checkmo') {
            return (string)__('Cash on Delivery');
        }

        try {
            $instance = $payment->getMethodInstance();
            $title = $instance ? (string)$instance->getTitle() : '';
            if ($title !== '') {
                return $title;
            }
        } catch (\Throwable $e) {
            // Fall through to code-based fallback.
        }

        if ($methodCode === '') {
            return (string)__('Payment method unavailable');
        }

        return ucwords(str_replace(['_', '-'], ' ', $methodCode));
    }
}
