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

class MassPrintInvoice extends Action
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
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/account/login');
        }

        $orderIds = $this->getRequest()->getParam('order_ids');
        if (empty($orderIds) || !is_array($orderIds)) {
            $this->messageManager->addErrorMessage(__('Please select at least one order.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        $vendorId = $vendor->getId();

        $invoicesToPrint = [];

        foreach ($orderIds as $orderId) {
            // Load Vendor Order first to verify ownership
            $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
            if (!$vendorOrder->getId() || $vendorOrder->getVendorId() != $vendorId) {
                continue; // Skip invalid or unauthorized orders
            }

            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
            if (!$order->getId()) {
                continue;
            }

            // Find invoice belonging to this vendor for this order
            foreach ($order->getInvoiceCollection() as $inv) {
                foreach ($inv->getAllItems() as $item) {
                    $orderItem = $item->getOrderItem();
                    if (!$orderItem || $orderItem->getParentItem()) {
                        continue;
                    }

                    $product = $orderItem->getProduct();
                    $itemVendorId = $product ? $product->getData('vendor_id') : null;

                    if ($itemVendorId == $vendorId) {
                        $invoicesToPrint[] = $inv;
                        break 1; // Found an invoice for this vendor in this order, move to next invoice? No, actually one order might have multiple invoices. But let's assume one relevant invoice per order for now or collect all.
                        // Actually, if we break, we might miss split invoices. But typically 1 invoice per shipment/group.
                        // The loop is over invoices. "break 1" breaks the ITEM loop, continuing to next invoice.
                        // Oh wait, I want to capture the invoice and move to next INVOICE.
                        // But if I want to avoid duplicates?
                        // Let's rely on $invoicesToPrint keys? No, array is indexed.
                        // Let's just collect valid invoices.
                    }
                }
            }
        }

        if (empty($invoicesToPrint)) {
            $this->messageManager->addErrorMessage(__('No valid invoices found for the selected orders.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        // Register invoices
        $this->coreRegistry->register('current_invoices', $invoicesToPrint);

        $resultPage = $this->resultPageFactory->create();
        // Reuse the existing print layout
        $resultPage->addHandle('vendor_marketplace_order_printinvoice');
        $resultPage->getConfig()->getTitle()->set(__('Print Invoices'));

        return $resultPage;
    }
}
