<?php
namespace Vendor\Marketplace\Block\Report;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder\CollectionFactory as VendorOrderCollectionFactory;
use Magento\Sales\Model\OrderFactory;

class Earnings extends Template
{
    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @var VendorOrderCollectionFactory
     */
    protected $vendorOrderCollectionFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @param Context $context
     * @param VendorSession $vendorSession
     * @param VendorOrderCollectionFactory $vendorOrderCollectionFactory
     * @param OrderFactory $orderFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        VendorOrderCollectionFactory $vendorOrderCollectionFactory,
        OrderFactory $orderFactory,
        array $data = []
    ) {
        $this->vendorSession = $vendorSession;
        $this->vendorOrderCollectionFactory = $vendorOrderCollectionFactory;
        $this->orderFactory = $orderFactory;
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getEarningsCollection()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'vendor.marketplace.report.earnings.pager'
            )->setCollection(
                    $this->getEarningsCollection()
                );
            $this->setChild('pager', $pager);
            $this->getEarningsCollection()->load();
        }
        return $this;
    }

    /**
     * Get vendor order collection
     *
     * @return \Vendor\Marketplace\Model\ResourceModel\VendorOrder\Collection
     */
    public function getEarningsCollection()
    {
        if (!$this->_collection) {
            $vendorId = $this->vendorSession->getVendorId();
            $this->_collection = $this->vendorOrderCollectionFactory->create()
                ->addFieldToFilter('vendor_id', $vendorId)
                ->addFieldToFilter('status', ['nin' => ['pending', 'processing', 'canceled']])
                ->setOrder('entity_id', 'DESC');
        }
        return $this->_collection;
    }

    /**
     * Get pager html
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * Get order object
     *
     * @param int $orderId
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder($orderId)
    {
        return $this->orderFactory->create()->load($orderId);
    }

    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    public function formatPrice($price)
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->format($price, [], false);
    }

    /** @var \Vendor\Marketplace\Model\ResourceModel\VendorOrder\Collection */
    protected $_collection;
}
