<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Controller\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class ForgotPassword extends \Magento\Customer\Controller\AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('marketplace/seller/dashboard');
        }

        $resultPage = $this->resultPageFactory->create();
        $forgotPasswordBlock = $resultPage->getLayout()->getBlock('forgotPassword');
        if ($forgotPasswordBlock) {
            $forgotPasswordBlock->setEmailValue((string)$this->customerSession->getForgottenEmail());
        }

        $this->customerSession->unsForgottenEmail();

        return $resultPage;
    }
}