<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Shipping\Model\ShipmentNotifier;

class AddTrack extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $shipmentRepository;
    protected $trackFactory;
    protected $shipmentNotifier;
    protected $vendorHelper;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        TrackFactory $trackFactory,
        ShipmentNotifier $shipmentNotifier,
        ?\Vendor\Marketplace\Helper\Data $vendorHelper = null
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->vendorHelper = $vendorHelper ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Vendor\Marketplace\Helper\Data::class);
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $vendorOrderId = $this->getRequest()->getParam('id');
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $carrier = $this->getRequest()->getParam('carrier');
        $trackingNumber = $this->getRequest()->getParam('tracking_number');

        if (!$vendorOrderId || !$shipmentId || !$carrier || !$trackingNumber) {
            $this->messageManager->addErrorMessage(__('Invalid parameters.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/order/history');
        }

        try {
            // Load Vendor Order
            $vendorOrder = $this->vendorOrderFactory->create()->load($vendorOrderId);
            if (!$vendorOrder->getId()) {
                throw new \Exception(__('Vendor Order not found.'));
            }

            // Verify Permission
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
            if ($vendorOrder->getVendorId() != $vendor->getId()) {
                throw new \Exception(__('Access Denied.'));
            }

            // Load and update shipment
            $shipment = $this->shipmentRepository->get($shipmentId);

            // Basic validation to ensure shipment belongs to the order
            if ($shipment->getOrderId() != $vendorOrder->getOrderId()) {
                throw new \Exception(__('Access Denied (Shipment mismatch).'));
            }

            // Verify shipment belongs to this vendor's items (prevent multi-vendor cross-shipment IDOR)
            $order = $shipment->getOrder();
            $vendorItems = $this->vendorHelper->getVendorOrderItems($order, $vendor->getId());
            $vendorOrderItemIds = [];
            foreach ($vendorItems as $vItem) {
                $vendorOrderItemIds[] = (int)$vItem->getId();
            }

            $shipmentBelongsToVendor = false;
            foreach ($shipment->getAllItems() as $sItem) {
                if (in_array((int)$sItem->getOrderItemId(), $vendorOrderItemIds, true)) {
                    $shipmentBelongsToVendor = true;
                    break;
                }
            }

            if (!$shipmentBelongsToVendor) {
                throw new \Exception(__('Access Denied: This shipment does not contain items from your vendor account.'));
            }

            $track = $this->trackFactory->create();
            $track->setNumber($trackingNumber);
            $track->setCarrierCode('custom');
            $track->setTitle($carrier);

            $shipment->addTrack($track);
            $this->shipmentRepository->save($shipment);

            // Notify Customer
            try {
                $this->shipmentNotifier->notify($shipment);
                $this->messageManager->addSuccessMessage(__('Tracking information has been added and customer has been notified.'));
            } catch (\Exception $e) {
                $this->messageManager->addWarningMessage(__('Tracking saved, but notification email failed to send.'));
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('marketplace/order/view', ['id' => $vendorOrderId]);
    }
}