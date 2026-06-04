<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Plugin\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\EmailNotification;

/**
 * Customer password reset email should use the template selected in admin.
 * The Multiv transport plugin injects the correct frontend_reset_url variable.
 */
class PasswordResetEmailUrlPlugin
{
    public function aroundPasswordResetConfirmation(
        EmailNotification $subject,
        callable $proceed,
        CustomerInterface $customer
    ): void {
        $proceed($customer);
    }
}