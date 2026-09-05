<?php
namespace Vendor\Ads\Model\Email;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Area;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Consumer
{
    protected $orderRepository;
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $urlModel;
    protected $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        UrlInterface $urlModel,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->urlModel = $urlModel;
        $this->logger = $logger;
    }

    public function processMessage(string $orderIdStr)
    {
        $orderId = (int)$orderIdStr;
        try {
            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getId()) {
                return;
            }

            $templateId = 'vendor_ads_wallet_recharge';
            $currency = $order->getOrderCurrency();
            $cgst = (float)$order->getData('indiangst_cgst_amount');
            $sgst = (float)$order->getData('indiangst_sgst_amount');
            $igst = (float)$order->getData('indiangst_igst_amount');

            $walletUrl = $this->urlModel->getUrl('vendor_ads/vendor/wallet');

            $transport = $this->transportBuilder->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => $order->getStoreId()
                ])
                ->setTemplateVars([
                    'order' => $order,
                    'customer_name' => $order->getCustomerName(),
                    'status_label' => $order->getStatusLabel(),
                    'formatted_base_amount' => $currency->formatTxt($order->getBaseSubtotal() ?: $order->getSubtotal()),
                    'formatted_grand_total' => $currency->formatTxt($order->getGrandTotal()),
                    'has_cgst' => $cgst > 0,
                    'formatted_cgst' => $currency->formatTxt($cgst),
                    'has_sgst' => $sgst > 0,
                    'formatted_sgst' => $currency->formatTxt($sgst),
                    'has_igst' => $igst > 0,
                    'formatted_igst' => $currency->formatTxt($igst),
                    'wallet_url' => $walletUrl,
                ])
                ->setFrom('sales')
                ->addTo($order->getCustomerEmail(), $order->getCustomerName())
                ->getTransport();

            $this->inlineTranslation->suspend();
            $transport->sendMessage();
            $this->inlineTranslation->resume();

            $order->setEmailSent(true);
            $this->orderRepository->save($order);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to send async wallet recharge custom email: ' . $e->getMessage(), ['order_id' => $orderId]);
            // Do NOT fall back to standard sales order email for wallet recharge
            $this->logger->critical("Failed to send wallet recharge email via transport. Skipping standard order email fallback to prevent incorrect New Order Placed template.", ["order_id" => $orderId]);
        }
    }
}
