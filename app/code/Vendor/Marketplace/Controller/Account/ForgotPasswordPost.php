<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SecurityViolationException;
use Magento\Framework\Validator\EmailAddress;
use Magento\Framework\Validator\ValidatorChain;

class ForgotPasswordPost extends \Magento\Customer\Controller\AbstractAccount implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly AccountManagementInterface $customerAccountManagement,
        private readonly Escaper $escaper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $email = (string)$this->getRequest()->getPost('email');

        if ($email === '') {
            $this->messageManager->addErrorMessage(__('Please enter your email.'));
            return $resultRedirect->setPath('marketplace/account/forgotpassword');
        }

        if (!ValidatorChain::is($email, EmailAddress::class)) {
            $this->customerSession->setForgottenEmail($email);
            $this->messageManager->addErrorMessage(
                __('The email address is incorrect. Verify the email address and try again.')
            );
            return $resultRedirect->setPath('marketplace/account/forgotpassword');
        }

        try {
            $this->customerAccountManagement->initiatePasswordReset($email, AccountManagement::EMAIL_RESET);
        } catch (NoSuchEntityException $exception) {
            // Intentionally silent to avoid user enumeration.
        } catch (SecurityViolationException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $resultRedirect->setPath('marketplace/account/forgotpassword');
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                __('We\'re unable to send the password reset email.')
            );
            return $resultRedirect->setPath('marketplace/account/forgotpassword');
        }

        $this->customerSession->destroy(['send_expire_cookie']);
        $this->messageManager->addSuccessMessage(
            __(
                'If there is an account associated with %1 you will receive an email with a link to reset your password.',
                $this->escaper->escapeHtml($email)
            )
        );

        return $resultRedirect->setPath('marketplace/account/login');
    }
}