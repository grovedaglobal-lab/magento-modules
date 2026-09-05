<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;

class PrintInvoice extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $resultPageFactory;
    protected $coreRegistry;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        PageFactory $resultPageFactory,
        Registry $coreRegistry
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        parent::__construct($context);
    }

    public function execute()
    {
        // 1. Must be logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $orderId = $this->getRequest()->getParam('id') ?: $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

        $order = null;
        $invoice = null;

        // 2. Validate Marketplace Product Orders (Must belong to this vendor)
        if ($vendor && $vendor->getId()) {
            $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
            if ($vendorOrder->getId()) {
                if ((int)$vendorOrder->getVendorId() !== (int)$vendor->getId()) {
                    // Unauthorized vendor attempting to access another vendor's order
                    $this->messageManager->addErrorMessage(__('You do not have permission to view this invoice.'));
                    return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
                }

                $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
                
                // Find invoice belonging to this vendor
                foreach ($order->getInvoiceCollection() as $inv) {
                    foreach ($inv->getAllItems() as $item) {
                        $orderItem = $item->getOrderItem();
                        if (!$orderItem || $orderItem->getParentItem())
                            continue;

                        $product = $orderItem->getProduct();
                        $itemVendorId = $product ? $product->getData('vendor_id') : null;

                        if ($itemVendorId == $vendor->getId()) {
                            $invoice = $inv;
                            break 2;
                        }
                    }
                }
            }
        }

        // 3. Validate Wallet Recharge Orders / Direct Sales Orders (Must belong to this logged-in customer)
        if (!$order) {
            $directOrder = $this->orderFactory->create()->load($orderId);
            if (!$directOrder->getId()) {
                // Try fallback by increment_id (e.g. #000000001 or 000000001)
                $cleanIncrementId = ltrim($orderId, '#');
                $directOrder = $this->orderFactory->create()->loadByIncrementId($cleanIncrementId);
            }

            if ($directOrder->getId()) {
                $orderCustomerId = (int)$directOrder->getCustomerId();

                // STRICT AUTHENTICATION: Only the customer who created/owns this recharge order can view it
                if ($orderCustomerId !== $customerId) {
                    $this->messageManager->addErrorMessage(__('You do not have permission to view this invoice.'));
                    return $this->resultRedirectFactory->create()->setPath('vendor_ads/vendor/walletHistory');
                }

                $order = $directOrder;
                if ($order->hasInvoices()) {
                    $invoice = $order->getInvoiceCollection()->getFirstItem();
                }
            }
        }

        // 4. If order exists and belongs to user, but invoice object is pending generation
        if ($order && !$invoice) {
            try {
                $invoiceService = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Sales\Model\Service\InvoiceService::class);
                $dbTransaction = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\DB\Transaction::class);
                
                $invoice = $invoiceService->prepareInvoice($order);
                if ($invoice && $invoice->getTotalQty()) {
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    $invoice->register();
                    $dbTransaction->addObject($invoice)->addObject($order)->save();
                }
            } catch (\Exception $e) {
                // Proceed with fallback if any
            }
        }

        // 5. Final Permission & Existence Validation
        if (!$order) {
            $this->messageManager->addErrorMessage(__('Invoice not found or access denied.'));
            return $this->resultRedirectFactory->create()->setPath('vendor_ads/vendor/walletHistory');
        }

        if (!$invoice && $order->hasInvoices()) {
            $invoice = $order->getInvoiceCollection()->getFirstItem();
        }

        // Register order and invoice for template access
        if ($this->coreRegistry->registry('current_order')) {
            $this->coreRegistry->unregister('current_order');
        }
        $this->coreRegistry->register('current_order', $order);

        if ($invoice) {
            if ($this->coreRegistry->registry('current_invoice')) {
                $this->coreRegistry->unregister('current_invoice');
            }
            $this->coreRegistry->register('current_invoice', $invoice);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('vendor_marketplace_order_printinvoice');
        $title = $invoice ? __('Print Invoice #%1', $invoice->getIncrementId()) : __('Print Order #%1', $order->getIncrementId());
        $resultPage->getConfig()->getTitle()->set($title);

        return $resultPage;
    }
}
