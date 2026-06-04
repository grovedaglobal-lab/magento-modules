<?php
namespace Vendor\Marketplace\Controller\Account;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Vendor\Marketplace\Model\ResourceModel\Vendor as VendorResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CreatePost extends Action
{
    protected $customerSession;
    protected $accountManagement;
    protected $customerFactory;
    protected $vendorFactory;
    protected $vendorProfileFactory;
    protected $vendorResource;
    protected $scopeConfig;
    protected $messageManager; // derived from context

    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $accountManagement,
        CustomerInterfaceFactory $customerFactory,
        VendorFactory $vendorFactory,
        VendorProfileFactory $vendorProfileFactory,
        VendorResource $vendorResource,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->customerSession = $customerSession;
        $this->accountManagement = $accountManagement;
        $this->customerFactory = $customerFactory;
        $this->vendorFactory = $vendorFactory;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->vendorResource = $vendorResource;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('vendor_marketplace/seller/dashboard');
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $post = $this->getRequest()->getPostValue();

        if (!$post) {
            return $resultRedirect->setPath('*/*/create');
        }

        $shopUrl = isset($post['shop_url']) ? trim($post['shop_url']) : '';
        $shopName = isset($post['shop_name']) ? trim($post['shop_name']) : '';
        $phone = isset($post['phone']) ? trim($post['phone']) : '';

        try {
            // Validate Shop URL
            if (empty($shopUrl)) {
                throw new InputException(__('Shop URL is required.'));
            }
            if ($this->isShopUrlExists($shopUrl)) {
                throw new InputException(__('Shop URL "%1" is already taken.', $shopUrl));
            }

            // Validate Phone Number
            if (!preg_match('/^\d{10}$/', $phone)) {
                throw new InputException(__('Please enter a valid 10-digit phone number.'));
            }

            // Create Customer
            $customer = $this->customerFactory->create();
            $customer->setFirstname($post['firstname'] ?? '');
            $customer->setLastname($post['lastname'] ?? '');
            $customer->setEmail($post['email'] ?? '');

            // Dynamically find Vendor/Retailer Group ID (avoid hardcoding 4)
            $groupId = 1; // Default to General
            try {
                // Using ObjectManager here to avoid constructor changes in this quick fix
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $groupRepo = $objectManager->get(\Magento\Customer\Api\GroupRepositoryInterface::class);
                $searchCriteriaBuilder = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
                $searchCriteria = $searchCriteriaBuilder->addFilter('customer_group_code', 'Retailer')->create();
                $groups = $groupRepo->getList($searchCriteria)->getItems();
                if ($groups) {
                    $groupId = reset($groups)->getId();
                }
            } catch (\Exception $e) {
                // Fallback to General
                $groupId = 1;
            }
            $customer->setGroupId($groupId);

            $password = $post['password'] ?? '';
            $confirmation = $post['password_confirmation'] ?? '';

            if ($password != $confirmation) {
                throw new InputException(__('Make sure your passwords match.'));
            }

            // This creates the customer in DB
            $customer = $this->accountManagement->createAccount($customer, $password);

            // Create Vendor Entity
            $vendor = $this->vendorFactory->create();
            $vendor->setCustomerId($customer->getId());
            $vendor->setShopUrl($shopUrl);

            $autoApprove = $this->scopeConfig->isSetFlag(
                'vendor_marketplace/general/auto_approve_vendor',
                ScopeInterface::SCOPE_STORE
            );
            $vendor->setStatus($autoApprove ? 1 : 0);
            $this->vendorResource->save($vendor);

            // Create Vendor Profile
            $vendorProfile = $this->vendorProfileFactory->create();
            $vendorProfile->setVendorId($vendor->getId());
            $vendorProfile->setShopName($shopName);
            $vendorProfile->setPhone($phone);
            $vendorProfile->save();

            // Log In
            $this->customerSession->setCustomerDataAsLoggedIn($customer);

            if ($autoApprove) {
                $this->messageManager->addSuccessMessage(__('Thank you for registering! Your vendor account has been approved.'));
            } else {
                $this->messageManager->addSuccessMessage(__('Thank you for registering! Your vendor account is pending approval.'));
            }
            return $resultRedirect->setPath('marketplace/seller/dashboard');

        } catch (InputException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->customerSession->setCustomerFormData($post); // Save form data
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->customerSession->setCustomerFormData($post);
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t save the vendor account right now.'));
            $this->customerSession->setCustomerFormData($post);
        }

        return $resultRedirect->setPath('*/*/create');
    }

    protected function isShopUrlExists($shopUrl)
    {
        $collection = $this->vendorFactory->create()->getCollection()
            ->addFieldToFilter('shop_url', $shopUrl);
        return $collection->getSize() > 0;
    }
}
