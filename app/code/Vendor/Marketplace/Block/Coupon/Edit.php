<?php
namespace Vendor\Marketplace\Block\Coupon;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorSalesRule\CollectionFactory as VendorRuleCollectionFactory;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Framework\Registry;

class Edit extends Template
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorRuleCollectionFactory;
    protected $ruleFactory;
    protected $registry;
    protected $currentVendor = null;
    protected $rule = null;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorRuleCollectionFactory $vendorRuleCollectionFactory,
        RuleFactory $ruleFactory,
        Registry $registry,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorRuleCollectionFactory = $vendorRuleCollectionFactory;
        $this->ruleFactory = $ruleFactory;
        $this->registry = $registry;
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

    public function getRule()
    {
        if ($this->rule === null) {
            $id = $this->getRequest()->getParam('id');
            $vendor = $this->getVendor();

            if ($id && $vendor) {
                // Verify ownership
                $collection = $this->vendorRuleCollectionFactory->create();
                $collection->addFieldToFilter('rule_id', $id);
                $collection->addFieldToFilter('vendor_id', $vendor->getId());

                if ($collection->getSize()) {
                    $this->rule = $this->ruleFactory->create()->load($id);
                } else {
                    // Rule not found or not owned by vendor
                    $this->rule = false;
                }
            } else {
                // New Rule
                $this->rule = $this->ruleFactory->create();
            }
        }
        return $this->rule;
    }

    public function getSaveUrl()
    {
        return $this->getUrl('vendor_marketplace/coupon/save');
    }

    public function getBackUrl()
    {
        return $this->getUrl('vendor_marketplace/coupon/index');
    }
}
