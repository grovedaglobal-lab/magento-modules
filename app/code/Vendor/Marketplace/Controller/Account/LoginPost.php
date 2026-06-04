<?php
namespace Vendor\Marketplace\Controller\Account;

use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\Result\Redirect;

class LoginPost extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    protected $session;
    protected $customerAccountManagement;
    protected $formKeyValidator;

    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        Validator $formKeyValidator
    ) {
        $this->session = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->formKeyValidator = $formKeyValidator;
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($this->session->isLoggedIn() || !$this->formKeyValidator->validate($this->getRequest())) {
            $resultRedirect->setPath('marketplace/seller/dashboard');
            return $resultRedirect;
        }

        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {
                try {
                    $customer = $this->customerAccountManagement->authenticate($login['username'], $login['password']);
                    
                    // Verify the authenticated customer is actually a vendor
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $vendorFactory = $objectManager->create(\Vendor\Marketplace\Model\VendorFactory::class);
                    $vendor = $vendorFactory->create()->load($customer->getId(), 'customer_id');

                    if (!$vendor->getId()) {
                        $this->messageManager->addErrorMessage(__('You do not have a vendor account.'));
                        $resultRedirect->setPath('marketplace/account/login');
                        return $resultRedirect;
                    }

                    if ($vendor->getStatus() == 0) {
                        $this->session->setCustomerDataAsLoggedIn($customer);
                        $this->session->regenerateId();
                        $this->messageManager->addNoticeMessage(__('Your vendor account is pending approval. You can access the dashboard in read-only mode in the meantime.'));
                        $resultRedirect->setPath('marketplace/seller/dashboard');
                        return $resultRedirect;
                    }

                    if ($vendor->getStatus() != 1) {
                        $this->messageManager->addErrorMessage(__('Your account is inactive.'));
                        $resultRedirect->setPath('marketplace/account/login');
                        return $resultRedirect;
                    }

                    $this->session->setCustomerDataAsLoggedIn($customer);
                    $this->session->regenerateId();

                    // FORCE REDIRECT TO VENDOR DASHBOARD
                    $resultRedirect->setPath('marketplace/seller/dashboard');
                    return $resultRedirect;

                } catch (EmailNotConfirmedException $e) {
                    $this->messageManager->addErrorMessage(__('This account is not confirmed.'));
                } catch (AuthenticationException $e) {
                    $this->messageManager->addErrorMessage(__('Invalid login or password.'));
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(__('An unexpected error occurred.'));
                }
            } else {
                $this->messageManager->addErrorMessage(__('A login and a password are required.'));
            }
        }

        // If login failed, go back to vendor login page
        $resultRedirect->setPath('marketplace/account/login');
        return $resultRedirect;
    }
}
