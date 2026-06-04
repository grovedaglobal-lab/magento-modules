<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Model\NotificationFactory;
use Vendor\Marketplace\Model\ResourceModel\Notification as NotificationResource;
use Vendor\Marketplace\Model\ResourceModel\Notification\CollectionFactory as NotificationCollectionFactory;

class NotificationManagement
{
    /**
     * @var NotificationFactory
     */
    protected $notificationFactory;

    /**
     * @var NotificationResource
     */
    protected $notificationResource;

    /**
     * @var NotificationCollectionFactory
     */
    protected $notificationCollectionFactory;

    /**
     * @param NotificationFactory $notificationFactory
     * @param NotificationResource $notificationResource
     * @param NotificationCollectionFactory $notificationCollectionFactory
     */
    public function __construct(
        NotificationFactory $notificationFactory,
        NotificationResource $notificationResource,
        NotificationCollectionFactory $notificationCollectionFactory
    ) {
        $this->notificationFactory = $notificationFactory;
        $this->notificationResource = $notificationResource;
        $this->notificationCollectionFactory = $notificationCollectionFactory;
    }

    /**
     * Create a new notification
     *
     * @param int $vendorId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string|null $link
     * @return \Vendor\Marketplace\Model\Notification
     */
    public function addNotification($vendorId, $type, $title, $message, $link = null)
    {
        $notification = $this->notificationFactory->create();
        $notification->setData([
            'vendor_id' => $vendorId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'is_read' => 0
        ]);
        $this->notificationResource->save($notification);
        return $notification;
    }

    /**
     * Get unread notifications count for a vendor
     *
     * @param int $vendorId
     * @return int
     */
    public function getUnreadCount($vendorId)
    {
        $collection = $this->notificationCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('is_read', 0);
        return $collection->getSize();
    }

    /**
     * Mark all notifications as read for a vendor
     *
     * @param int $vendorId
     * @return void
     */
    public function markAllAsRead($vendorId)
    {
        $connection = $this->notificationResource->getConnection();
        $table = $this->notificationResource->getMainTable();
        $connection->update(
            $table,
            ['is_read' => 1],
            ['vendor_id = ?' => $vendorId, 'is_read = ?' => 0]
        );
    }
}
