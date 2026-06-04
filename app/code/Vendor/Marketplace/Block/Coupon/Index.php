<?php
namespace Vendor\Marketplace\Block\Coupon;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorSalesRule\CollectionFactory as VendorRuleCollectionFactory;
use Magento\SalesRule\Model\RuleFactory;

class Index extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorRuleCollectionFactory;
    protected $ruleFactory;
    protected $currentVendor = null;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorRuleCollectionFactory $vendorRuleCollectionFactory,
        RuleFactory $ruleFactory,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorRuleCollectionFactory = $vendorRuleCollectionFactory;
        $this->ruleFactory = $ruleFactory;
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

    public function getCoupons()
    {
        $vendor = $this->getVendor();
        if (!$vendor) {
            return [];
        }

        $collection = $this->vendorRuleCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendor->getId());

        // Join with salesrule table to get rule details
        $collection->getSelect()->join(
            ['rule' => $collection->getTable('salesrule')],
            'main_table.rule_id = rule.rule_id',
            ['name', 'description', 'from_date', 'to_date', 'is_active', 'simple_action', 'discount_amount']
        );

        // Join with salesrule_coupon to get the coupon code
        $collection->getSelect()->joinLeft(
            ['coupon' => $collection->getTable('salesrule_coupon')],
            'main_table.rule_id = coupon.rule_id AND coupon.is_primary = 1',
            ['code']
        );

        $collection->setOrder('main_table.created_at', 'DESC');

        return $collection;
    }

    public function getAddUrl()
    {
        return $this->getUrl('vendor_marketplace/coupon/new');
    }

    public function getEditUrl($id)
    {
        return $this->getUrl('vendor_marketplace/coupon/edit', ['id' => $id]);
    }

    public function getDeleteUrl($id)
    {
        return $this->getUrl('vendor_marketplace/coupon/delete', ['id' => $id]);
    }
}
