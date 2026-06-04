<?php
namespace Vendor\Marketplace\Plugin\Sales\Model\ResourceModel\Order\Grid;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\UserContextInterface;
use Psr\Log\LoggerInterface;

class CollectionPlugin
{
    protected $adminSession;
    protected $vendorFactory;
    protected $filterApplied = false;
    protected $logger;

    public function __construct(
        AdminSession $adminSession,
        VendorFactory $vendorFactory,
        LoggerInterface $logger
    ) {
        $this->adminSession = $adminSession;
        $this->vendorFactory = $vendorFactory;
        $this->logger = $logger;
    }

    /**
     * Filter order grid collection for vendor users before loading
     *
     * @param Collection $subject
     * @return null
     */
    public function beforeLoad(Collection $subject)
    {
        if ($this->filterApplied) {
            return null;
        }

        // Check if the logged-in admin user is a vendor
        $user = $this->adminSession->getUser();
        if (!$user || !$user->getId()) {
            $this->logger->info('OrderGrid Plugin: No admin user logged in');
            return null;
        }

        $this->logger->info('OrderGrid Plugin: User ID=' . $user->getId() . ', Email=' . $user->getEmail());

        // Get the vendor by checking if the admin user's email matches a customer's email
        // This assumes vendor users are created with the same email as their customer account
        $userEmail = $user->getEmail();
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerFactory = $objectManager->create(\Magento\Customer\Model\CustomerFactory::class);
        $customer = $customerFactory->create()->setWebsiteId(1)->loadByEmail($userEmail);
        
        if (!$customer || !$customer->getId()) {
            // No matching customer found - not a vendor, show all orders
            $this->logger->info('OrderGrid Plugin: No customer found with email=' . $userEmail . ' - showing all orders');
            return null;
        }

        $this->logger->info('OrderGrid Plugin: Found Customer ID=' . $customer->getId());

        $vendor = $this->vendorFactory->create()->load($customer->getId(), 'customer_id');
        
        if (!$vendor->getId()) {
            // Not a vendor, show all orders (regular admin)
            $this->logger->info('OrderGrid Plugin: No vendor entity for customer_id=' . $customer->getId() . ' - showing all orders');
            return null;
        }

        // This is a vendor user - filter collection to only show orders from this vendor
        $vendorId = $vendor->getId();
        
        $this->logger->info('OrderGrid Plugin: *** FILTERING FOR VENDOR_ID=' . $vendorId . ' ***');
        
        $subject->getSelect()->joinInner(
            ['vo' => $subject->getTable('vendor_order')],
            'main_table.entity_id = vo.order_id',
            []
        )->where('vo.vendor_id = ?', $vendorId)
        ->group('main_table.entity_id');

        $this->filterApplied = true;

        return null;
    }
}
