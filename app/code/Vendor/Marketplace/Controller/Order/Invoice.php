<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Vendor\Marketplace\Helper\Data as VendorHelper;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class Invoice extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $invoiceService;
    protected $transaction;
    protected $vendorHelper;
    protected $invoiceSender;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        InvoiceService $invoiceService,
        Transaction $transaction,
        VendorHelper $vendorHelper,
        InvoiceSender $invoiceSender
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->vendorHelper = $vendorHelper;
        $this->invoiceSender = $invoiceSender;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $orderId = $this->getRequest()->getParam('id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Invalid Order ID.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        try {
            // Load Vendor Order
            $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);
            if (!$vendorOrder->getId()) {
                throw new \Exception(__('Vendor Order not found.'));
            }

            // Verify Vendor Permission
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if ($vendorOrder->getVendorId() != $vendor->getId()) {
                throw new \Exception(__('Access Denied.'));
            }

            // Load Sales Order
            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
            if (!$order->canInvoice()) {
                throw new \Exception(__('Order cannot be invoiced.'));
            }

            // Calculate Quantities to Invoice (Only for this vendor)
            $vendorItems = $this->vendorHelper->getVendorOrderItems($order, $vendor->getId());
            $qtys = [];
            $hasItemsToInvoice = false;

            foreach ($vendorItems as $item) {
                // Determine qty available to invoice
                $qtyToInvoice = $item->getQtyOrdered() - $item->getQtyInvoiced();
                if ($qtyToInvoice > 0) {
                    $qtys[$item->getId()] = $qtyToInvoice;
                    $hasItemsToInvoice = true;
                }
            }

            if (!$hasItemsToInvoice) {
                throw new \Exception(__('No items available to invoice for this vendor.'));
            }

            // Create Invoice
            $invoice = $this->invoiceService->prepareInvoice($order, $qtys);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $invoice->getOrder()->setIsInProcess(true);

            $saveTransaction = $this->transaction->addObject($invoice)
                ->addObject($invoice->getOrder());

            $saveTransaction->save();

            // Update Vendor Order Status if needed
            // If all items invoiced/shipped, we might change status. For now, keep as 'processing' if pending.
            if ($vendorOrder->getStatus() == 'pending') {
                $vendorOrder->setStatus('processing');
                $vendorOrder->save();
            }

            // Send Invoice Email
            try {
                $this->invoiceSender->send($invoice);
                $this->messageManager->addSuccessMessage(__('Invoice has been created and customer notified successfully.'));
            } catch (\Exception $e) {
                $this->messageManager->addWarningMessage(__('Invoice created locally, but failed to send notification email: ' . $e->getMessage()));
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $orderId]);
    }
}
