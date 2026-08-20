<?php
/**
 * @category Multiv
 * @package Multiv_FrontendEmail
 * @copyright Copyright (c) 2026 Multiv
 */
namespace Multiv\FrontendEmail\Plugin\Mail\Template;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Vendor\Marketplace\Model\VendorFactory;

class TransportBuilderPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var VendorFactory
     */
    protected $vendorFactory;

    /**
     * @var string|null
     */
    protected $templateIdentifier;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param VendorFactory $vendorFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        VendorFactory $vendorFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->vendorFactory = $vendorFactory;
    }

    /**
     * Track the template identifier so URL selection can follow the actual template in use.
     *
     * @param TransportBuilder $subject
     * @param string $templateIdentifier
     * @return array
     */
    public function beforeSetTemplateIdentifier(TransportBuilder $subject, $templateIdentifier)
    {
        $this->templateIdentifier = (string)$templateIdentifier;

        return [$templateIdentifier];
    }

    /**
     * Add frontend_reset_url variable to email templates based on customer type (Vendor or Customer)
     *
     * @param TransportBuilder $subject
     * @param array $vars
     * @return array
     */
    public function beforeSetTemplateVars(TransportBuilder $subject, array $vars)
    {
        if (!empty($vars['frontend_reset_url'])) {
            return [$vars];
        }

        $isVendorTemplate = $this->isVendorTemplate();

        if (isset($vars['customer'])) {
            $customer = $vars['customer'];
            
            // Check if customer is a DataObject (standard for EmailNotification variables)
            if (is_object($customer) && method_exists($customer, 'getData')) {
                $customerId = $customer->getData('entity_id') ?: $customer->getData('id');
                $rpToken = $customer->getData('rp_token');
                $email = $customer->getData('email');

                if ($rpToken && $email) {
                    $configPath = $isVendorTemplate
                        ? 'multiv_frontend/general/vendor_frontend_url'
                        : 'multiv_frontend/general/frontend_url';

                    if (!$isVendorTemplate) {
                        $isVendorTemplate = $this->isVendor((int)$customerId);
                        $configPath = $isVendorTemplate
                            ? 'multiv_frontend/general/vendor_frontend_url'
                            : 'multiv_frontend/general/frontend_url';
                    }

                    $storeId = 0;
                    if (isset($vars['store']) && is_object($vars['store']) && method_exists($vars['store'], 'getId')) {
                        $storeId = (int)$vars['store']->getId();
                    }

                    $frontendBaseUrl = $this->scopeConfig->getValue(
                        $configPath,
                        ScopeInterface::SCOPE_STORE,
                        $storeId
                    );

                    if ($frontendBaseUrl) {
                        $frontendBaseUrl = rtrim($frontendBaseUrl, '/');
                        $path = $isVendorTemplate
                            ? sprintf('/marketplace/account/createpassword/?id=%s&token=%s', rawurlencode((string)$customerId), rawurlencode($rpToken))
                            : sprintf('/reset-password?token=%s&email=%s', rawurlencode($rpToken), rawurlencode($email));
                        $vars['frontend_reset_url'] = sprintf(
                            '%s%s',
                            $frontendBaseUrl,
                            $path
                        );
                    }
                }
            }
        }

        return [$vars];
    }

    /**
     * Check if a customer ID belongs to a vendor
     * 
     * @param int $customerId
     * @return bool
     */
    protected function isVendor(int $customerId): bool
    {
        if (!$customerId) {
            return false;
        }
        $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');
        return (bool)$vendor->getId();
    }

    /**
     * Determine whether the current email template is the vendor forgot-password template.
     */
    protected function isVendorTemplate(): bool
    {
        if ($this->templateIdentifier === null || $this->templateIdentifier === '') {
            return false;
        }

        return in_array($this->templateIdentifier, [
            'vendor_marketplace_vendor_forgot_password_template',
            'vendor_marketplace_email_vendor_forgot_password_template'
        ], true);
    }
}
