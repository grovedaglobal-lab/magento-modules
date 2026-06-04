<?php
namespace Vendor\Marketplace\Controller\Notification;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\Marketplace\Model\NotificationManagement;
use Vendor\Marketplace\Model\Session\VendorSession;
use Vendor\Marketplace\Model\ResourceModel\Notification\CollectionFactory;

class Fetch extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var NotificationManagement
     */
    protected $notificationManagement;

    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param NotificationManagement $notificationManagement
     * @param VendorSession $vendorSession
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        NotificationManagement $notificationManagement,
        VendorSession $vendorSession,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->notificationManagement = $notificationManagement;
        $this->vendorSession = $vendorSession;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $vendorId = $this->vendorSession->getVendorId();

        if (!$vendorId) {
            return $resultJson->setData(['success' => false, 'message' => __('Vendor session not found')]);
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId)
            ->addFieldToFilter('is_read', 0)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(5);

        $notifications = [];
        foreach ($collection as $item) {
            $notifications[] = [
                'id' => $item->getId(),
                'title' => $item->getTitle(),
                'message' => $item->getMessage(),
                'link' => $this->_url->getUrl($item->getLink()),
                'created_at' => $item->getCreatedAt()
            ];
        }

        return $resultJson->setData([
            'success' => true,
            'count' => $this->notificationManagement->getUnreadCount($vendorId),
            'notifications' => $notifications
        ]);
    }
}
