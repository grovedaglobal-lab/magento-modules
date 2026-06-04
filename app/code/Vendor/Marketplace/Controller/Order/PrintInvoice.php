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
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $orderId = $this->getRequest()->getParam('id');
        if (!$orderId) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
        if (!$vendorOrder->getId()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        $customerId = $this->customerSession->getCustomerId();
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        if ($vendorOrder->getVendorId() != $vendor->getId()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());

        // Find invoice belonging to this vendor
        $invoice = null;
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

        if (!$invoice) {
            $this->messageManager->addErrorMessage(__('No invoice found for your items in this order.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $orderId]);
        }

        // Register order and invoice for template access
        if ($this->coreRegistry->registry('current_order')) {
            $this->coreRegistry->unregister('current_order');
        }
        $this->coreRegistry->register('current_order', $order);

        if ($this->coreRegistry->registry('current_invoice')) {
            $this->coreRegistry->unregister('current_invoice');
        }
        $this->coreRegistry->register('current_invoice', $invoice);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('vendor_marketplace_order_printinvoice');
        $resultPage->getConfig()->getTitle()->set(__('Print Invoice #%1', $invoice->getIncrementId()));

        return $resultPage;
    }
}
