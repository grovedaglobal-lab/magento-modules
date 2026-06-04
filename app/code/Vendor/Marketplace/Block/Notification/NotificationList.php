<?php
namespace Vendor\Marketplace\Block\Notification;

use Magento\Framework\View\Element\Template;
use Vendor\Marketplace\Model\ResourceModel\Notification\CollectionFactory;
use Vendor\Marketplace\Model\Session\VendorSession;

class NotificationList extends Template
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @var \Vendor\Marketplace\Model\ResourceModel\Notification\Collection
     */
    protected $notifications;

    /**
     * @param Template\Context $context
     * @param CollectionFactory $collectionFactory
     * @param VendorSession $vendorSession
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        CollectionFactory $collectionFactory,
        VendorSession $vendorSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
        $this->vendorSession = $vendorSession;
    }

    /**
     * Get notifications collection
     *
     * @return \Vendor\Marketplace\Model\ResourceModel\Notification\Collection
     */
    public function getNotifications()
    {
        if (!$this->notifications) {
            $vendorId = $this->vendorSession->getVendorId();
            $this->notifications = $this->collectionFactory->create();
            $this->notifications->addFieldToFilter('vendor_id', $vendorId)
                ->setOrder('created_at', 'DESC');

            // Set pagination
            $page = ($this->getRequest()->getParam('p')) ? $this->getRequest()->getParam('p') : 1;
            $pageSize = ($this->getRequest()->getParam('limit')) ? $this->getRequest()->getParam('limit') : 10;
            $this->notifications->setPageSize($pageSize);
            $this->notifications->setCurPage($page);
        }
        return $this->notifications;
    }

    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getNotifications()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'vendor.notification.list.pager'
            )->setAvailableLimit([10 => 10, 20 => 20, 50 => 50])
                ->setCollection($this->getNotifications());
            $this->setChild('pager', $pager);
            $this->getNotifications()->load();
        }
        return $this;
    }

    /**
     * Get pager HTML
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }
}
