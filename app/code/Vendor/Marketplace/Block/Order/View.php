<?php
namespace Vendor\Marketplace\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Vendor\Marketplace\Model\ResourceModel\ShippingRate\CollectionFactory as ShippingRateCollectionFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Address\Renderer as AddressRenderer;
use Vendor\Marketplace\Helper\Data as VendorHelper;

class View extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $vendorProfileFactory;
    protected $shippingRateCollectionFactory;
    protected $orderFactory;
    protected $coreRegistry;
    protected $paymentHelper;
    protected $addressRenderer;
    protected $vendorHelper;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        VendorProfileFactory $vendorProfileFactory,
        ShippingRateCollectionFactory $shippingRateCollectionFactory,
        OrderFactory $orderFactory,
        Registry $coreRegistry,
        \Magento\Payment\Helper\Data $paymentHelper,
        AddressRenderer $addressRenderer,
        VendorHelper $vendorHelper,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->shippingRateCollectionFactory = $shippingRateCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->coreRegistry = $coreRegistry;
        $this->paymentHelper = $paymentHelper;
        $this->addressRenderer = $addressRenderer;
        $this->vendorHelper = $vendorHelper;
        parent::__construct($context, $data);
    }

    /**
     * Check if subtotal should be displayed including tax
     * @return bool
     */
    public function displaySubtotalInclTax()
    {
        $value = $this->getConfigValue('tax/sales_display/subtotal', $this->getOrder()->getStoreId());
        return $value == \Magento\Tax\Model\Config::DISPLAY_TYPE_INCLUDING_TAX ||
            $value == \Magento\Tax\Model\Config::DISPLAY_TYPE_BOTH;
    }

    /**
     * Get system configuration value
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigValue($path, $storeId = null)
    {
        return $this->_scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function _construct()
    {
        parent::_construct();
        $this->pageConfig->getTitle()->set(__('Order Details'));
    }

    /**
     * Get the current Vendor Order object
     * @return \Vendor\Marketplace\Model\VendorOrder|false
     */
    public function getVendorOrder()
    {
        // Check registry first
        $vendorOrder = $this->coreRegistry->registry('current_vendor_order');
        if ($vendorOrder) {
            return $vendorOrder;
        }

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/vendor_order_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        if (!$this->customerSession->isLoggedIn()) {
            $logger->info('BLOCK: getVendorOrder - User not logged in');
            return false;
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        if (!$vendor->getId()) {
            $logger->info('BLOCK: getVendorOrder - Vendor not found for customer ' . $customerId);
            return false;
        }

        $requestId = $this->getRequest()->getParam('id');
        if (!$requestId) {
            $logger->info('BLOCK: getVendorOrder - No ID param');
            return false;
        }

        $logger->info("BLOCK: getVendorOrder - Request ID: $requestId, Vendor ID: " . $vendor->getId());

        // 1. Try loading as Vendor Order ID
        $vendorOrder = $this->vendorOrderFactory->create()->load($requestId);

        if ($vendorOrder->getId() && $vendorOrder->getVendorId() == $vendor->getId()) {
            $logger->info('BLOCK: getVendorOrder - Found by Vendor Order ID');
            $this->coreRegistry->register('current_vendor_order', $vendorOrder);
            return $vendorOrder;
        }

        // 2. Fallback: Try loading as Sales Order ID (linked to this vendor)
        $logger->info('BLOCK: getVendorOrder - ID match failed, checking as Sales Order ID');
        $collection = $this->vendorOrderFactory->create()->getCollection();
        $collection->addFieldToFilter('order_id', $requestId);
        $collection->addFieldToFilter('vendor_id', $vendor->getId());

        $fallbackOrder = $collection->getFirstItem();

        if ($fallbackOrder && $fallbackOrder->getId()) {
            $logger->info('BLOCK: getVendorOrder - Found by Sales Order ID linkage. Vendor Order ID: ' . $fallbackOrder->getId());
            $this->coreRegistry->register('current_vendor_order', $fallbackOrder);
            return $fallbackOrder;
        }

        $logger->info('BLOCK: getVendorOrder - FAILED to find order or permission denied. VO ID: ' . $vendorOrder->getId() . ', VO Vendor: ' . $vendorOrder->getVendorId());
        return false;
    }

    /**
     * Get the main Sales Order object
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        // Check registry first (used by PrintInvoice controller)
        $order = $this->coreRegistry->registry('current_order');
        if ($order) {
            return $order;
        }

        // Fallback to loading from vendor order
        $vendorOrder = $this->getVendorOrder();
        if ($vendorOrder) {
            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
            return $order;
        }
        return null;
    }

    public function getFormattedAddress($address)
    {
        if (!$address) {
            return '';
        }
        return $this->addressRenderer->format($address, 'html');
    }

    public function getPaymentInfoHtml()
    {
        return $this->getChildHtml('payment_info');
    }

    public function getInvoiceUrl()
    {
        return $this->getUrl('marketplace/order/invoice', ['id' => $this->getVendorOrder()->getId()]);
    }

    public function getShipUrl()
    {
        return $this->getUrl('marketplace/order/ship', ['id' => $this->getVendorOrder()->getId()]);
    }

    public function getCancelUrl()
    {
        return $this->getUrl('marketplace/order/cancel', ['id' => $this->getVendorOrder()->getId()]);
    }

    public function getPrintUrl()
    {
        return $this->getUrl('marketplace/order/print', ['id' => $this->getVendorOrder()->getId()]);
    }

    public function getPrintInvoiceUrl()
    {
        return $this->getUrl('marketplace/order/printinvoice', ['id' => $this->getVendorOrder()->getId()]);
    }

    public function canPrintInvoice()
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        $invoiceCount = (int) $order->getInvoiceCollection()->getSize();
        if ($invoiceCount > 0) {
            return true;
        }

        $vendorOrder = $this->getVendorOrder();
        if (!$vendorOrder) {
            return false;
        }

        $vendorItems = $this->getVendorOrderItems();
        foreach ($vendorItems as $item) {
            $qtyInvoiced = (float) $item->getQtyInvoiced();
            if ($qtyInvoiced > 0) {
                return true;
            }
        }

        return false;
    }

    public function getBackUrl()
    {
        return $this->getUrl('marketplace/order/history');
    }

    /**
     * Get items belonging to this vendor
     */
    public function getVendorOrderItems()
    {
        $order = $this->getOrder();
        $vendorOrder = $this->getVendorOrder();
        $items = [];

        if ($order && $vendorOrder) {
            foreach ($order->getAllItems() as $item) {
                if ($item->getParentItem())
                    continue; // Skip child items, keep parent which has price/tax

                // Check if item belongs to vendor
                // We need to load product if vendor_id isn't on item
                $product = $item->getProduct();
                $vendorId = $product ? $product->getData('vendor_id') : null;

                if ($vendorId == $vendorOrder->getVendorId()) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    public function getVendorShippingAmount()
    {
        $order = $this->getOrder();
        $vendorOrder = $this->getVendorOrder();
        if (!$order || !$vendorOrder) {
            return 0.0;
        }

        $storedShipping = $vendorOrder->getData('shipping_amount');
        if ($storedShipping !== null && $storedShipping !== '') {
            return (float) $storedShipping;
        }

        $vendorId = (int) $vendorOrder->getVendorId();
        $vendorItems = $this->getVendorOrderItems();
        if (!$vendorItems) {
            return 0.0;
        }

        $weight = 0.0;
        $subtotal = 0.0;
        foreach ($vendorItems as $item) {
            $qty = (float) $item->getQtyOrdered();
            $weight += ((float) $item->getWeight()) * $qty;
            $subtotal += (float) $item->getRowTotal();
        }

        $profile = $this->vendorProfileFactory->create()->load($vendorId, 'vendor_id');
        if (!$profile->getId()) {
            return 0.0;
        }

        $shippingSource = $profile->getShippingSource() ?: 'self_ship';
        if ($shippingSource !== 'self_ship') {
            return 0.0;
        }

        $freeShippingThreshold = (float) $profile->getFreeShippingAmount();
        if ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) {
            return 0.0;
        }

        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            return 0.0;
        }

        $rate = $this->getVendorShippingRate(
            $vendorId,
            (string) $shippingAddress->getCountryId(),
            (int) $shippingAddress->getRegionId(),
            (string) $shippingAddress->getPostcode(),
            $weight
        );

        if (!$rate || $rate->getPrice() === null) {
            return 0.0;
        }

        return (float) $rate->getPrice();
    }

    protected function getVendorShippingRate($vendorId, $countryId, $regionId, $zip, $weight)
    {
        $collection = $this->shippingRateCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('country_id', $countryId);

        $collection->addFieldToFilter('weight_from', ['lteq' => $weight]);
        $collection->addFieldToFilter('weight_to', ['gteq' => $weight]);

        $collection->getSelect()->where(
            '(region_id = ? OR region_id = 0 OR region_id IS NULL)',
            $regionId
        );

        $collection->getSelect()->where(
            '(zip_code = ? OR zip_code = "*" OR zip_code IS NULL OR zip_code = "")',
            $zip
        );

        $rates = $collection->getItems();
        $bestRate = null;
        $bestScore = -1;

        foreach ($rates as $rate) {
            $score = 0;
            if ((int) $rate->getRegionId() == $regionId && $regionId != 0) {
                $score += 10;
            }
            if ($rate->getZipCode() == $zip && $zip != '*' && $zip != '') {
                $score += 5;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRate = $rate;
            }
        }

        if (!$bestRate) {
            return false;
        }

        return $bestRate;
    }

    public function getItemHtml(\Magento\Framework\DataObject $item)
    {
        $type = $item->getOrderItem() ? $item->getOrderItem()->getProductType() : $item->getProductType();
        $renderer = $this->getRenderer($type);
        $renderer->setItem($item);
        return $renderer->toHtml();
    }

    protected function getRenderer($type)
    {
        $rendererList = $this->getChildBlock('renderer.list');
        if ($rendererList) {
            try {
                // Try specific type, fallback to 'default' alias if not found
                // Pass null for template to use the renderer's configured template
                return $rendererList->getRenderer($type, 'default');
            } catch (\Exception $e) {
                // If RendererList itself fails or even 'default' is missing
            }
        }

        // Final fallback: Create a default renderer block manually
        $block = $this->getLayout()->createBlock(\Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer::class);
        $block->setTemplate('Vendor_Marketplace::order/print/items/renderer/default.phtml');
        return $block;
    }

    public function getInvoice()
    {
        // Check registry first (used by PrintInvoice controller)
        $invoice = $this->coreRegistry->registry('current_invoice');
        if ($invoice) {
            return $invoice;
        }

        // Fallback to getting first invoice from order
        $order = $this->getOrder();
        if ($order && $order->hasInvoices()) {
            return $order->getInvoiceCollection()->getFirstItem();
        }
        return null;
    }

    public function formatAddress($address, $type = 'html')
    {
        return $this->addressRenderer->format($address, $type);
    }

    public function formatDate($date = null, $format = \IntlDateFormatter::SHORT, $showTime = false, $timezone = null)
    {
        if (!$date) {
            return '';
        }
        return $this->_localeDate->formatDateTime(
            new \DateTime($date),
            $format,
            $showTime ? $format : \IntlDateFormatter::NONE,
            $timezone
        );
    }

    /**
     * Get invoices containing items belonging to this vendor
     * @return array
     */
    public function getVendorInvoices()
    {
        $order = $this->getOrder();
        $vendorOrder = $this->getVendorOrder();
        if (!$order || !$vendorOrder) {
            return [];
        }

        $vendorInvoices = [];
        $vendorId = $vendorOrder->getVendorId();

        foreach ($order->getInvoiceCollection() as $invoice) {
            $hasVendorItem = false;
            foreach ($invoice->getAllItems() as $item) {
                $orderItem = $item->getOrderItem();
                if (!$orderItem) {
                    continue;
                }

                if ($orderItem->getParentItem()) {
                    continue;
                }

                $product = $orderItem->getProduct();
                $itemVendorId = $product ? $product->getData('vendor_id') : null;

                // Safeguard reload if missing
                if (!$itemVendorId) {
                    try {
                        $p = $this->vendorHelper->getProductById($orderItem->getProductId());
                        $itemVendorId = $p ? $p->getData('vendor_id') : null;
                    } catch (\Exception $e) {
                    }
                }

                if ($itemVendorId && $itemVendorId == $vendorId) {
                    $hasVendorItem = true;
                    break;
                }
            }
            if ($hasVendorItem) {
                $vendorInvoices[] = $invoice;
            }
        }
        return $vendorInvoices;
    }

    /**
     * Check if vendor can ship items
     * @return bool
     */
    public function canVendorShip()
    {
        $vendorOrder = $this->getVendorOrder();
        if (!$vendorOrder || $vendorOrder->getStatus() == 'complete') {
            return false;
        }

        $items = $this->getVendorOrderItems();
        foreach ($items as $item) {
            $qtyToShip = $item->getQtyOrdered() - $item->getQtyShipped();
            if ($qtyToShip > 0) {
                return true;
            }
        }
        return false;
    }

    public function getInvoices()
    {
        return $this->coreRegistry->registry('current_invoices');
    }

    /**
     * Get shipments containing items belonging to this vendor
     * @return array
     */
    public function getVendorShipments()
    {
        $order = $this->getOrder();
        $vendorOrder = $this->getVendorOrder();
        if (!$order || !$vendorOrder) {
            return [];
        }

        $vendorShipments = [];
        $vendorId = $vendorOrder->getVendorId();

        foreach ($order->getShipmentsCollection() as $shipment) {
            $hasVendorItem = false;
            foreach ($shipment->getAllItems() as $item) {
                $orderItem = $item->getOrderItem();
                if (!$orderItem) {
                    continue;
                }

                if ($orderItem->getParentItem()) {
                    continue;
                }

                $product = $orderItem->getProduct();
                $itemVendorId = $product ? $product->getData('vendor_id') : null;

                // Safeguard reload if missing
                if (!$itemVendorId) {
                    try {
                        $p = $this->vendorHelper->getProductById($orderItem->getProductId());
                        $itemVendorId = $p ? $p->getData('vendor_id') : null;
                    } catch (\Exception $e) {
                    }
                }

                if ($itemVendorId && $itemVendorId == $vendorId) {
                    $hasVendorItem = true;
                    break;
                }
            }
            if ($hasVendorItem) {
                $vendorShipments[] = $shipment;
            }
        }
        return $vendorShipments;
    }
}
