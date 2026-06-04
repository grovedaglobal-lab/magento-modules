<?php
namespace Vendor\Marketplace\Block\Report;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class Coupon extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $orderCollectionFactory;
    protected $currentVendor = null;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        OrderCollectionFactory $orderCollectionFactory,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getVendor()
    {
        if (!$this->currentVendor) {
            $customerId = $this->customerSession->getCustomerId();
            if ($customerId) {
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
                if ($vendor->getId()) {
                    $this->currentVendor = $vendor;
                }
            }
        }
        return $this->currentVendor;
    }

    public function getCouponUsage()
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            return [];
        }

        $collection = $this->orderCollectionFactory->create();

        // Join salesrule_coupon to link order coupon_code to rule_id
        $collection->getSelect()->join(
            ['coupon' => $collection->getTable('salesrule_coupon')],
            'main_table.coupon_code = coupon.code',
            ['rule_id']
        );

        // Join vendor_sales_rule to filter by vendor
        $collection->getSelect()->join(
            ['vsr' => $collection->getTable('vendor_sales_rule')],
            'coupon.rule_id = vsr.rule_id',
            []
        );

        $collection->addFieldToFilter('vsr.vendor_id', $vendor->getId());

        // Add rule name for display
        $collection->getSelect()->join(
            ['rule' => $collection->getTable('salesrule')],
            'coupon.rule_id = rule.rule_id',
            ['rule_name' => 'name'] // Alias to avoid conflict
        );

        $collection->setOrder('main_table.created_at', 'DESC');

        return $collection;
    }
}
