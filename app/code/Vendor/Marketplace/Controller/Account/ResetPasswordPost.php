<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\InputException;

class ResetPasswordPost extends \Magento\Customer\Controller\AbstractAccount implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resetPasswordToken = (string)$this->getRequest()->getQuery('token');
        $customerId = (string)$this->getRequest()->getQuery('id');
        $password = (string)$this->getRequest()->getPost('password');
        $passwordConfirmation = (string)$this->getRequest()->getPost('password_confirmation');
        $email = null;

        if ($password !== $passwordConfirmation) {
            $this->messageManager->addErrorMessage(__("New Password and Confirm New Password values didn't match."));
            return $resultRedirect->setPath('customer/account/createpassword', ['token' => $resetPasswordToken]);
        }

        if (iconv_strlen($password) <= 0) {
            $this->messageManager->addErrorMessage(__('Please enter a new password.'));
            return $resultRedirect->setPath('customer/account/createpassword', ['token' => $resetPasswordToken]);
        }

        if ($customerId && $this->customerRepository->getById($customerId)) {
            $email = $this->customerRepository->getById($customerId)->getEmail();
        }

        try {
            $this->accountManagement->resetPassword($email, $resetPasswordToken, $password);

            if ($this->customerSession->isLoggedIn()) {
                $this->customerSession->logout();
                $this->customerSession->start();
            }

            $this->customerSession->unsRpToken();
            $this->customerSession->unsRpCustomerId();
            $this->messageManager->addSuccessMessage(__('You updated your password.'));

            return $resultRedirect->setPath('marketplace/account/login');
        } catch (InputException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the new password.'));
        }

        return $resultRedirect->setPath('customer/account/createpassword', ['token' => $resetPasswordToken]);
    }
}