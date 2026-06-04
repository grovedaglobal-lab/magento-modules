<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Framework\DB\Transaction;
use Vendor\Marketplace\Helper\Data as VendorHelper;
use Magento\Shipping\Model\ShipmentNotifier;

class Ship extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderFactory;
    protected $shipmentFactory;
    protected $shipmentRepository;
    protected $shipmentExtensionFactory;
    protected $transaction;
    protected $vendorHelper;
    protected $shipmentNotifier;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderFactory $orderFactory,
        ShipmentFactory $shipmentFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentExtensionFactory $shipmentExtensionFactory,
        Transaction $transaction,
        VendorHelper $vendorHelper,
        ShipmentNotifier $shipmentNotifier
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderFactory = $orderFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->shipmentExtensionFactory = $shipmentExtensionFactory;
        $this->transaction = $transaction;
        $this->vendorHelper = $vendorHelper;
        $this->shipmentNotifier = $shipmentNotifier;
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

            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
            if ($vendorOrder->getVendorId() != $vendor->getId()) {
                throw new \Exception(__('Access Denied.'));
            }

            $order = $this->orderFactory->create()->load($vendorOrder->getOrderId());
            if (!$order->canShip()) {
                throw new \Exception(__('Order cannot be shipped.'));
            }

            // Calc quantities
            $vendorItems = $this->vendorHelper->getVendorOrderItems($order, $vendor->getId());
            $qtys = [];
            $hasItemsToShip = false;

            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/vendor_ship_debug.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info("SHIP: Order " . $order->getIncrementId() . " for Vendor " . $vendor->getId());

            foreach ($vendorItems as $item) {
                // Determine qty available to ship
                $qtyToShip = $item->getQtyOrdered() - $item->getQtyShipped();
                $logger->info("ITEM: ID " . $item->getId() . ", SKU " . $item->getSku() . ", QtyToShip: " . $qtyToShip);
                if ($qtyToShip > 0) {
                    $qtys[$item->getId()] = $qtyToShip;
                    $hasItemsToShip = true;
                }
            }

            if (!$hasItemsToShip) {
                $logger->info("FAIL: No items to ship");
                throw new \Exception(__('No items available to ship for this vendor.'));
            }

            $logger->info("QTYS Map: " . json_encode($qtys));

            // Create Shipment
            $shipment = $this->shipmentFactory->create($order, $qtys);

            // MSI Support: Set the vendor's source code
            $vendorSourceCode = 'vendor_' . $vendor->getId();

            // Ensure extension attributes exist and set the source code
            $extensionAttributes = $shipment->getExtensionAttributes();
            if (!$extensionAttributes) {
                $extensionAttributes = $this->shipmentExtensionFactory->create();
            }
            $extensionAttributes->setSourceCode($vendorSourceCode);
            $shipment->setExtensionAttributes($extensionAttributes);

            $shipment->register();
            $shipment->getOrder()->setIsInProcess(true);

            // Save via repository to trigger MSI plugins (inventory deduction)
            $this->shipmentRepository->save($shipment);

            // Explicitly save the order to ensure status updates are persisted
            $order->save();

            // Notify Customer (Shipment without tracking)
            try {
                $this->shipmentNotifier->notify($shipment);
                $this->messageManager->addSuccessMessage(__('Shipment has been created and customer notified successfully.'));
            } catch (\Exception $e) {
                // Ignore email failures
                $this->messageManager->addWarningMessage(__('Shipment created locally, but failed to send notification email: ' . $e->getMessage()));
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $orderId]);
    }
}
