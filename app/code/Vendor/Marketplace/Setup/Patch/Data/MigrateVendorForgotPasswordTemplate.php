<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateVendorForgotPasswordTemplate implements DataPatchInterface
{
    private const OLD_TEMPLATE_ID = 'vendor_marketplace_email_vendor_forgot_password_template';
    private const NEW_TEMPLATE_ID = 'vendor_marketplace_vendor_forgot_password_template';
    private const CONFIG_PATH = 'vendor_marketplace/email/vendor_forgot_password_template';

    public function __construct(
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply()
    {
        $this->configWriter->save(self::CONFIG_PATH, self::NEW_TEMPLATE_ID);
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
