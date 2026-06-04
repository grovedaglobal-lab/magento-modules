<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\NotificationManagement;
use Vendor\Marketplace\Model\Notification;
use Magento\Catalog\Api\ProductRepositoryInterface;

class NotifyNewReview implements ObserverInterface
{
    /**
     * @var NotificationManagement
     */
    protected $notificationManagement;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @param NotificationManagement $notificationManagement
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        NotificationManagement $notificationManagement,
        ProductRepositoryInterface $productRepository
    ) {
        $this->notificationManagement = $notificationManagement;
        $this->productRepository = $productRepository;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $review = $observer->getEvent()->getObject();
        $productId = $review->getEntityPkValue();

        try {
            $product = $this->productRepository->getById($productId);
            $vendorId = $product->getData('vendor_id');

            if ($vendorId) {
                $title = __('New Product Review');
                $message = __('A customer has submitted a new review for your product: %1', $product->getName());
                $link = 'vendor_marketplace/review/index'; // Or specific review link if available

                $this->notificationManagement->addNotification(
                    $vendorId,
                    Notification::TYPE_NEW_REVIEW,
                    $title,
                    $message,
                    $link
                );
            }
        } catch (\Exception $e) {
            // Silently fail if product doesn't exist or other error
        }
    }
}
