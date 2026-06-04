<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Plugin\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\EmailNotification;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Model\VendorFactory;

class VendorForgotPasswordEmailPlugin
{
    private const XML_PATH_VENDOR_FORGOT_TEMPLATE = 'vendor_marketplace/email/vendor_forgot_password_template';
    private const DEFAULT_VENDOR_FORGOT_TEMPLATE = 'vendor_marketplace_vendor_forgot_password_template';
    private const XML_PATH_VENDOR_FRONTEND_URL = 'multiv_frontend/general/vendor_frontend_url';

    public function __construct(
        private readonly VendorFactory $vendorFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SenderResolverInterface $senderResolver,
        private readonly TransportBuilder $transportBuilder,
        private readonly CustomerViewHelper $customerViewHelper,
        private readonly CustomerRegistry $customerRegistry,
        private readonly DataObjectProcessor $dataObjectProcessor,
        private readonly Emulation $emulation
    ) {
    }

    /**
     * Use a separate forgot-password template for vendor accounts.
     *
     * @throws LocalizedException
     */
    public function aroundPasswordResetConfirmation(
        EmailNotification $subject,
        callable $proceed,
        CustomerInterface $customer
    ): void {
        $customerId = (int)$customer->getId();
        if ($customerId <= 0 || !$this->isVendorCustomer($customerId)) {
            $proceed($customer);
            return;
        }

        $storeId = $this->resolveStoreId($customer);
        $customerEmailData = $this->buildCustomerEmailData($customer);
        $resetToken = (string)$customerEmailData->getRpToken();
        if ($resetToken === '') {
            $proceed($customer);
            return;
        }

        $templateId = (string)$this->scopeConfig->getValue(
            self::XML_PATH_VENDOR_FORGOT_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($templateId === '') {
            $templateId = self::DEFAULT_VENDOR_FORGOT_TEMPLATE;
        }

        // Build the frontend reset password URL for vendor
        $frontendUrl = $this->scopeConfig->getValue(
            self::XML_PATH_VENDOR_FRONTEND_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'http://localhost:3001';
        
        if (!str_ends_with($frontendUrl, '/')) {
            $frontendUrl .= '/';
        }

        $resetPasswordUrl = $frontendUrl . 'marketplace/account/createpassword/?id=' .
            urlencode((string)$customer->getId()) .
            '&token=' . urlencode($resetToken);

        $from = $this->senderResolver->resolve(
            (string)$this->scopeConfig->getValue(
                EmailNotification::XML_PATH_FORGOT_EMAIL_IDENTITY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            $storeId
        );

        $transport = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars([
                'customer' => $customerEmailData,
                'store' => $this->storeManager->getStore($storeId),
                'resetPasswordUrl' => $resetPasswordUrl,
                'frontend_reset_url' => $resetPasswordUrl
            ])
            ->setFrom($from)
            ->addTo((string)$customer->getEmail(), $this->customerViewHelper->getCustomerName($customer))
            ->getTransport();

        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND);
        try {
            $transport->sendMessage();
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function isVendorCustomer(int $customerId): bool
    {
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        return (bool)$vendor->getId();
    }

    private function resolveStoreId(CustomerInterface $customer): int
    {
        $storeId = $customer->getStoreId();
        if ($storeId !== null) {
            return (int)$storeId;
        }

        if ((int)$customer->getWebsiteId() > 0) {
            $storeIds = $this->storeManager->getWebsite((int)$customer->getWebsiteId())->getStoreIds();
            $firstStoreId = reset($storeIds);
            if ($firstStoreId !== false) {
                return (int)$firstStoreId;
            }
        }

        return (int)$this->storeManager->getStore()->getId();
    }

    private function buildCustomerEmailData(CustomerInterface $customer): \Magento\Customer\Model\Data\CustomerSecure
    {
        $mergedCustomerData = $this->customerRegistry->retrieveSecureData((int)$customer->getId());
        $customerData = $this->dataObjectProcessor->buildOutputDataArray($customer, CustomerInterface::class);
        $mergedCustomerData->addData($customerData);
        $mergedCustomerData->setData('name', $this->customerViewHelper->getCustomerName($customer));

        return $mergedCustomerData;
    }
}