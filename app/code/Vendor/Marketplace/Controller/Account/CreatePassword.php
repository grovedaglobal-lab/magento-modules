<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ForgotPasswordToken\ConfirmCustomerByToken;
use Magento\Customer\Model\ForgotPasswordToken\GetCustomerByToken;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class CreatePassword extends \Magento\Customer\Controller\AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly PageFactory $resultPageFactory,
        private readonly AccountManagementInterface $accountManagement,
        private readonly ?ConfirmCustomerByToken $confirmByToken = null,
        private readonly ?GetCustomerByToken $getByToken = null,
        private readonly ?CustomerRepositoryInterface $customerRepository = null
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $confirmByToken = $this->confirmByToken ?? ObjectManager::getInstance()->get(ConfirmCustomerByToken::class);
        $customerRepository = $this->customerRepository ?? ObjectManager::getInstance()->get(CustomerRepositoryInterface::class);

        $resetPasswordToken = (string)$this->getRequest()->getParam('token');
        $customerId = (int)$this->getRequest()->getParam('id');
        $isDirectLink = $resetPasswordToken !== '';

        if (!$isDirectLink) {
            $resetPasswordToken = (string)$this->customerSession->getRpToken();
            $customerId = (int)$this->customerSession->getRpCustomerId();
        }

        try {
            $this->accountManagement->validateResetPasswordLinkToken($customerId, $resetPasswordToken);
            $confirmByToken->resetCustomerConfirmation($customerId);

            $customer = $customerRepository->getById($customerId);
            $this->accountManagement->changeResetPasswordLinkToken($customer, $resetPasswordToken);

            if ($isDirectLink) {
                $this->customerSession->setRpToken($resetPasswordToken);
                $this->customerSession->setRpCustomerId($customerId);

                return $this->resultRedirectFactory->create()->setPath('marketplace/account/createpassword');
            }

            /** @var Page $resultPage */
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getLayout()
                ->getBlock('resetPassword')
                ->setResetPasswordLinkToken($resetPasswordToken)
                ->setRpCustomerId($customerId);

            return $resultPage;
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Your password reset link has expired.'));
            return $this->resultRedirectFactory->create()->setPath('marketplace/account/forgotpassword');
        }
    }
}