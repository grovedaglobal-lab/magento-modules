<?php
namespace Vendor\Marketplace\Controller\Coupon;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorSalesRuleFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorSalesRule\CollectionFactory as VendorRuleCollectionFactory;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Rule\Condition\ProductFactory as ProductConditionFactory;
use Magento\SalesRule\Model\Rule\Condition\Product\FoundFactory as ProductFoundConditionFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class Save extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorSalesRuleFactory;
    protected $vendorRuleCollectionFactory;
    protected $ruleFactory;
    protected $ruleRepository;
    protected $productConditionFactory;
    protected $productFoundConditionFactory;
    protected $formKeyValidator;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorSalesRuleFactory $vendorSalesRuleFactory,
        VendorRuleCollectionFactory $vendorRuleCollectionFactory,
        RuleFactory $ruleFactory,
        RuleRepository $ruleRepository,
        ProductConditionFactory $productConditionFactory,
        ProductFoundConditionFactory $productFoundConditionFactory,
        FormKeyValidator $formKeyValidator
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorSalesRuleFactory = $vendorSalesRuleFactory;
        $this->vendorRuleCollectionFactory = $vendorRuleCollectionFactory;
        $this->ruleFactory = $ruleFactory;
        $this->ruleRepository = $ruleRepository;
        $this->productConditionFactory = $productConditionFactory;
        $this->productFoundConditionFactory = $productFoundConditionFactory;
        $this->formKeyValidator = $formKeyValidator;
        parent::__construct($context);
    }


    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if (!$vendor->getId()) {
                $this->messageManager->addErrorMessage(__('You are not a registered vendor.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }

            $ruleId = isset($data['rule_id']) ? $data['rule_id'] : null;
            $rule = $this->ruleFactory->create();

            if ($ruleId) {
                // Verify ownership
                $collection = $this->vendorRuleCollectionFactory->create();
                $collection->addFieldToFilter('rule_id', $ruleId);
                $collection->addFieldToFilter('vendor_id', $vendor->getId());

                if (!$collection->getSize()) {
                    $this->messageManager->addErrorMessage(__('You do not have permission to edit this coupon.'));
                    return $this->resultRedirectFactory->create()->setPath('*/*/index');
                }
                $rule->load($ruleId);
            }

            // Strip existing prefix before loading post to avoid duplication in case it's already there
            $data['name'] = preg_replace('/^\[VENDOR_\d+\]\s*/', '', $data['name']);

            // Load all data from request (handles serialized conditions too)
            $rule->loadPost($data);

            // Set Rule Data (Custom/Override)
            $vendorName = $vendor->getShopUrl() ? ucwords(str_replace('-', ' ', $vendor->getShopUrl())) : 'Vendor';
            $prefix = '[VENDOR_' . $vendor->getId() . '] ';
            $rule->setName($prefix . $data['name']);
            $rule->setDescription('Created by Vendor: ' . $vendorName);
            $rule->setCouponCode($data['code']);
            $rule->setIsActive($data['is_active']);

            // Hardcoded Settings for Vendor Logic
            $rule->setWebsiteIds([1]); // Default Website
            $rule->setCustomerGroupIds([0, 1, 2, 3]); // All Groups
            $rule->setCouponType(\Magento\SalesRule\Model\Rule::COUPON_TYPE_SPECIFIC);
            $rule->setUseAutoGeneration(0);
            $rule->setStopRulesProcessing(0);
            $rule->setIsAdvanced(1);

            // Mandatory Logic: Actions - Restrict to Vendor Items
            // We ensure the root aggregator is ALWAYS 'all' so that our vendor_id check is mandatory
            $rule->getActions()->setConditions([]); // Reset existing conditions
            $rule->getActions()->setAggregator('all');
            $rule->getActions()->setValue(1); // True

            // 1. Vendor ID Restriction (Always apply)
            $condition = $this->productConditionFactory->create();
            $condition->setAttribute('vendor_id');
            $condition->setOperator('==');
            $condition->setValue($vendor->getId());
            $rule->getActions()->addCondition($condition);

            // 2. Selected Products Restriction (from simplified UI)
            if (isset($data['product_skus']) && is_array($data['product_skus']) && !empty($data['product_skus'])) {
                $skus = implode(',', $data['product_skus']);

                $productCondition = $this->productConditionFactory->create();
                $productCondition->setType('Magento\SalesRule\Model\Rule\Condition\Product');
                $productCondition->setAttribute('sku');
                $productCondition->setOperator('()'); // "is one of"
                $productCondition->setValue($skus);

                $rule->getActions()->addCondition($productCondition);
            }

            // Force Empty/True for Cart Conditions (remove any "logic" from cart level)
            // But actually, to be safe for cart_fixed, we SHOULD enforce that at least one valid item exists.
            $conditions = $this->ruleFactory->create()->getConditions();
            $conditions->setAggregator('all');
            $conditions->setValue(1); // True

            // Add "If an item is found" condition
            $foundCondition = $this->productFoundConditionFactory->create();
            $foundCondition->setType('Magento\SalesRule\Model\Rule\Condition\Product\Found');
            $foundCondition->setValue(1); // Found
            $foundCondition->setAggregator('all'); // matching ALL of below

            // Vendor ID in Found Condition
            $vCond = $this->productConditionFactory->create();
            $vCond->setAttribute('vendor_id');
            $vCond->setOperator('==');
            $vCond->setValue($vendor->getId());
            $foundCondition->addCondition($vCond);

            // SKU in Found Condition (if selected)
            if (isset($data['product_skus']) && is_array($data['product_skus']) && !empty($data['product_skus'])) {
                $skus = implode(',', $data['product_skus']);
                $sCond = $this->productConditionFactory->create();
                $sCond->setAttribute('sku');
                $sCond->setOperator('()');
                $sCond->setValue($skus);
                $foundCondition->addCondition($sCond);
            }

            $conditions->addCondition($foundCondition);
            $rule->setConditions($conditions);

            // Save Rule
            $rule->save();

            // Link in vendor_sales_rule if new
            if (!$ruleId) {
                $vendorRule = $this->vendorSalesRuleFactory->create();
                $vendorRule->setVendorId($vendor->getId());
                $vendorRule->setRuleId($rule->getId());
                $vendorRule->save();
            }

            $this->messageManager->addSuccessMessage(__('Coupon saved successfully.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error saving coupon: %1', $e->getMessage()));
            return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $ruleId]);
        }
    }
}
