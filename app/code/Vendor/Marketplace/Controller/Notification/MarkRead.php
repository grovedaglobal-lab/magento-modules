<?php
namespace Vendor\Marketplace\Controller\Notification;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\Marketplace\Model\NotificationManagement;
use Vendor\Marketplace\Model\Session\VendorSession;

class MarkRead extends Action
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
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param NotificationManagement $notificationManagement
     * @param VendorSession $vendorSession
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        NotificationManagement $notificationManagement,
        VendorSession $vendorSession
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->notificationManagement = $notificationManagement;
        $this->vendorSession = $vendorSession;
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

        $this->notificationManagement->markAllAsRead($vendorId);

        return $resultJson->setData([
            'success' => true
        ]);
    }
}
