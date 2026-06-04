<?php
namespace Vendor\Marketplace\Plugin\Adminhtml\Order\Invoice;

use Magento\Sales\Controller\Adminhtml\Order\Invoice\Save as SaveController;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class SavePlugin
{
    protected $request;
    protected $invoiceService;
    protected $transactionFactory;
    protected $invoiceSender;
    protected $messageManager;
    protected $orderFactory;
    protected $logger;

    public function __construct(
        RequestInterface $request,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        InvoiceSender $invoiceSender,
        ManagerInterface $messageManager,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->messageManager = $messageManager;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    /**
     * Around execute to split invoice by vendor
     * 
     * @param SaveController $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function aroundExecute(SaveController $subject, \Closure $proceed)
    {
        $orderId = $this->request->getParam('order_id');
        $invoiceData = $this->request->getParam('invoice', []);
        $items = isset($invoiceData['items']) ? $invoiceData['items'] : [];

        if (empty($items)) {
            return $proceed();
        }

        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            return $proceed();
        }

        // Group items by vendor
        $vendorItems = [];
        foreach ($items as $itemId => $qty) {
            if ($qty <= 0)
                continue;

            $orderItem = null;
            foreach ($order->getAllItems() as $oi) {
                if ($oi->getId() == $itemId) {
                    $orderItem = $oi;
                    break;
                }
            }

            if ($orderItem) {
                $vendorId = $orderItem->getProduct() ? $orderItem->getProduct()->getData('vendor_id') : 'admin';
                $vendorItems[$vendorId ?: 'admin'][$itemId] = $qty;
            }
        }

        // If only one vendor or no vendors found, proceed normally
        if (count($vendorItems) <= 1) {
            return $proceed();
        }

        // MULTI-VENDOR SPLIT LOGIC
        try {
            $invoiceCount = 0;
            foreach ($vendorItems as $vId => $vQtys) {
                $this->logger->info("Splitting Invoice for Vendor: " . $vId);

                // Re-load order to have fresh state? Or just use same
                $invoice = $this->invoiceService->prepareInvoice($order, $vQtys);
                if (!$invoice->getTotalQty())
                    continue;

                if (!empty($invoiceData['capture_case'])) {
                    $invoice->setRequestedCaptureCase($invoiceData['capture_case']);
                }

                $invoice->register();
                $invoice->getOrder()->setIsInProcess(true);

                $transaction = $this->transactionFactory->create()
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                $transaction->save();

                // Send Email
                try {
                    if (!empty($invoiceData['send_email'])) {
                        $this->invoiceSender->send($invoice);
                    }
                } catch (\Exception $e) {
                    $this->logger->error("Email error during split invoice: " . $e->getMessage());
                }

                $invoiceCount++;
            }

            $this->messageManager->addSuccessMessage(
                __('Split Invoice Successful: %1 separate invoices were created for each vendor.', $invoiceCount)
            );

            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            return $subject->getResponse()->setRedirect($subject->getUrl('sales/order/view', ['order_id' => $orderId]));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error during split invoice: %1', $e->getMessage()));
            $this->logger->critical($e);
            return $proceed(); // Fallback to original logic if we fail early
        }
    }
}
