<?php
namespace Vendor\Marketplace\Controller\Coupon;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorSalesRule\CollectionFactory as VendorRuleCollectionFactory;
use Magento\SalesRule\Model\RuleRepository;

class Delete extends Action
{
    protected $customerSession;
    protected $vendorFactory;
    protected $vendorRuleCollectionFactory;
    protected $ruleRepository;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        VendorRuleCollectionFactory $vendorRuleCollectionFactory,
        RuleRepository $ruleRepository
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->vendorRuleCollectionFactory = $vendorRuleCollectionFactory;
        $this->ruleRepository = $ruleRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $customerId = $this->customerSession->getCustomerId();
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

                if (!$vendor->getId()) {
                    $this->messageManager->addErrorMessage(__('You are not a registered vendor.'));
                    return $this->resultRedirectFactory->create()->setPath('*/*/index');
                }

                // Verify ownership
                $collection = $this->vendorRuleCollectionFactory->create();
                $collection->addFieldToFilter('rule_id', $id);
                $collection->addFieldToFilter('vendor_id', $vendor->getId());

                $vendorRule = $collection->getFirstItem();

                if (!$vendorRule->getId()) {
                    $this->messageManager->addErrorMessage(__('You do not have permission to delete this coupon.'));
                    return $this->resultRedirectFactory->create()->setPath('*/*/index');
                }

                // Delete the Sales Rule (Cascade should handle vendor_sales_rule deletion if defined, check db_schema)
                // In db_schema, we have onDelete="CASCADE" for foreign keys, so deleting vendor_sales_rule row is automatic if referenced row deleted?
                // No, the vendor_sales_rule references salesrule. If we delete salesrule, vendor_sales_rule row is deleted.

                $this->ruleRepository->deleteById($id);

                $this->messageManager->addSuccessMessage(__('The coupon has been deleted.'));

            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('An error occurred while deleting the coupon: %1', $e->getMessage()));
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
