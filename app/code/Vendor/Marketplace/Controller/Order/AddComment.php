<?php
namespace Vendor\Marketplace\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;
use Magento\Framework\Controller\ResultFactory;

class AddComment extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorOrderFactory;
    protected $orderRepository;
    protected $orderCommentSender;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorOrderFactory $vendorOrderFactory,
        OrderRepositoryInterface $orderRepository,
        OrderCommentSender $orderCommentSender
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->orderRepository = $orderRepository;
        $this->orderCommentSender = $orderCommentSender;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$this->customerSession->isLoggedIn()) {
            return $resultRedirect->setPath('customer/account/login');
        }

        $orderId = $this->getRequest()->getParam('id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Invalid order ID.'));
            return $resultRedirect->setPath('marketplace/order/history');
        }

        try {
            // 1. Load Vendor and verify
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if (!$vendor->getId()) {
                $this->messageManager->addErrorMessage(__('You are not authorized to view this order.'));
                return $resultRedirect->setPath('marketplace/dashboard');
            }

            // 2. Load Vendor Order
            $vendorOrder = $this->vendorOrderFactory->create()->load($orderId);

            if (!$vendorOrder->getId() || $vendorOrder->getVendorId() != $vendor->getId()) {
                $this->messageManager->addErrorMessage(__('Order not found or access denied.'));
                return $resultRedirect->setPath('marketplace/order/history');
            }

            // 3. Get Post Data
            $data = $this->getRequest()->getPostValue();
            $comment = isset($data['comment']) ? trim($data['comment']) : '';
            $status = isset($data['status']) ? $data['status'] : null;
            $notify = isset($data['notify_customer']) ? (bool) $data['notify_customer'] : false;
            $visible = isset($data['visible_on_storefront']) ? (bool) $data['visible_on_storefront'] : false;

            // 4. Update Vendor Order Status
            if ($status) {
                $vendorOrder->setStatus($status);
                $vendorOrder->save();
            }

            // 5. Add Comment to Main Order
            // Use full comment which includes status info prefixing
            $fullComment = $comment;
            if ($status) {
                $statusLabel = ucfirst($status);
                $statusPrefix = "[Status Tracking: {$statusLabel}]";
                // If comment exists, append it. Otherwise just show status update.
                $fullComment = $comment ? $statusPrefix . "\n" . $comment : $statusPrefix;
            }

            if ($comment || $status) {
                $order = $this->orderRepository->get($vendorOrder->getOrderId());

                // Create the history comment
                $mainedOrderStatus = $status ?: false;
                $history = $order->addStatusHistoryComment($fullComment, $mainedOrderStatus);
                $history->setIsVisibleOnFront($visible);
                $history->setIsCustomerNotified($notify);
                $history->save();

                $this->orderRepository->save($order);

                // 6. Send Email
                if ($notify) {
                    try {
                        // CRITICAL: We pass the $fullComment explicitly to ensure it reaches the email sender
                        // Standard Magento 2 behavior uses the 3rd param as the comment variable in templates
                        $this->orderCommentSender->send($order, $notify, $fullComment);
                    } catch (\Exception $e) {
                        $this->messageManager->addWarningMessage(__('Order updated, but notification email failed (MailHog might be down).'));
                    }
                }

                $this->messageManager->addSuccessMessage(__('You updated the order.'));
            } else {
                $this->messageManager->addWarningMessage(__('Please provide a comment or status.'));
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('We cannot add the order history.'));
        }

        return $resultRedirect->setPath('marketplace/order/view', ['id' => $orderId]);
    }
}
