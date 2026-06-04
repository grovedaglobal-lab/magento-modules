<?php
namespace Vendor\Marketplace\Model\Order\Email\Sender;

use Magento\Sales\Model\Order;
use Vendor\Marketplace\Model\VendorOrder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Helper\Data as VendorHelper;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;

class VendorOrderSender
{
    const XML_PATH_VENDOR_ORDER_NEW_TEMPLATE = 'vendor_marketplace/email/vendor_order_new_template';
    const XML_PATH_EMAIL_IDENTITY = 'trans_email/ident_sales/email';

    protected $vendorFactory;
    protected $transportBuilder;
    protected $scopeConfig;
    protected $storeManager;
    protected $vendorHelper;
    protected $vendorProfileFactory;
    protected $customerRepository;
    protected $eventManager;
    protected $logger;

    public function __construct(
        \Vendor\Marketplace\Model\VendorFactory $vendorFactory,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        VendorHelper $vendorHelper,
        VendorProfileFactory $vendorProfileFactory,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $eventManager,
        LoggerInterface $logger
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->vendorHelper = $vendorHelper;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->customerRepository = $customerRepository;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    public function send(Order $order, VendorOrder $vendorOrder)
    {
        try {
            $vendorId = $vendorOrder->getVendorId();
            $vendor = $this->vendorFactory->create()->load($vendorId);

            if (!$vendor->getId()) {
                $this->logger->info("VendorOrderSender: Vendor not found for ID: " . $vendorId);
                return false;
            }

            $profile = $this->vendorProfileFactory->create()->load($vendorId, 'vendor_id');

            // Get Vendor Customer context to find the email
            $vendorCustomer = $this->customerRepository->getById($vendor->getCustomerId());
            $vendorEmail = $vendorCustomer->getEmail();

            if (!$vendorEmail) {
                $this->logger->info("VendorOrderSender: No email found for vendor ID: " . $vendorId);
                return false;
            }

            $storeId = $order->getStoreId();
            $templateId = $this->scopeConfig->getValue(
                'vendor_marketplace/email/vendor_order_new_template',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?: 'vendor_marketplace_email_vendor_order_new_template';

            $items = $this->vendorHelper->getVendorOrderItems($order, $vendorId);
            $itemsHtml = $this->getFormattedItemsHtml($items, $order);

            // Calculate Ship By (Current Date + 2 days as example)
            $shipByDate = date('d/m/Y', strtotime($order->getCreatedAt() . ' + 2 days'));

            $templateVars = [
                'order' => $order,
                'vendor_order' => $vendorOrder,
                'vendor_name' => $profile->getShopName() ?: $vendorCustomer->getFirstname(),
                'order_id' => $order->getIncrementId(),
                'order_date' => $order->getCreatedAt(),
                'ship_by' => $shipByDate,
                'subject' => "[Action Required] Sold, ship now: Order #{$order->getIncrementId()}",
                'view_order_url' => $this->storeManager->getStore($storeId)->getUrl('marketplace/order/view', ['id' => $vendorOrder->getEntityId()]),
                'items_html' => $itemsHtml,
                'vendor_subtotal' => $order->formatPrice($vendorOrder->getSubtotal()),
                'vendor_discount' => $order->formatPrice($vendorOrder->getDiscountAmount()),
                'vendor_shipping' => $order->formatPrice($vendorOrder->getData('shipping_amount')),
                'commission_amount' => $order->formatPrice($vendorOrder->getCommissionAmount()),
                'total_vendor_amount' => $order->formatPrice($vendorOrder->getVendorEarnings()),
                'shipping_method' => $order->getShippingDescription(),
                'payment_method' => $order->getPayment()->getMethodInstance()->getTitle(),
                'store_url' => $this->storeManager->getStore($storeId)->getBaseUrl()
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars($templateVars)
                ->setFrom($this->scopeConfig->getValue('trans_email/ident_sales', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId))
                ->addTo($vendorEmail, $vendorCustomer->getFirstname())
                ->getTransport();

            $transport->sendMessage();
            return true;
        } catch (\Exception $e) {
            $this->logger->error("VendorOrderSender Error: " . $e->getMessage());
            return false;
        }
    }

    protected function getFormattedItemsHtml($items, $order)
    {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px; border-collapse: collapse;">';
        foreach ($items as $item) {
            $html .= sprintf(
                '<tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #eee;">
                        <div style="font-weight: bold; color: #232f3e; margin-bottom: 5px;">%s</div>
                        <div style="font-size: 12px; color: #555;">
                            <strong>SKU:</strong> %s | 
                            <strong>Qty:</strong> %d | 
                            <strong>Price:</strong> %s |
                            <strong>Tax:</strong> %s
                        </div>
                    </td>
                </tr>',
                $item->getName(),
                $item->getSku(),
                (int) $item->getQtyOrdered(),
                $order->formatPrice($item->getPrice()),
                $order->formatPrice($item->getTaxAmount())
            );
        }
        $html .= '</table>';
        return $html;
    }
}
