<?php
declare(strict_types=1);

namespace Vendor\AccessControl\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'vendor_access_control/general/enabled';
    private const XML_PATH_ALLOWED_URLS = 'vendor_access_control/general/allowed_urls';
    private const XML_PATH_BLOCKED_URLS = 'vendor_access_control/general/blocked_urls';
    private const XML_PATH_HIDE_HEADER_FOOTER_URLS = 'vendor_access_control/general/hide_header_footer_urls';
    private const XML_PATH_APPLY_DOMAIN_ONLY = 'vendor_access_control/general/apply_domain_only';
    private const XML_PATH_DOMAIN = 'vendor_access_control/general/domain';

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getAdminFrontName(): string
    {
        $useCustomPath = $this->scopeConfig->isSetFlag('admin/url/use_custom_path');
        $customPath = (string)$this->scopeConfig->getValue('admin/url/custom_path');

        if ($useCustomPath && !empty($customPath)) {
            return $customPath;
        }

        return (string)$this->scopeConfig->getValue('backend/frontName') ?: 'admin';
    }

    public function getAllowedUrls(): array
    {
        return $this->parseLines((string)$this->scopeConfig->getValue(self::XML_PATH_ALLOWED_URLS, ScopeInterface::SCOPE_STORE));
    }

    public function getBlockedUrls(): array
    {
        return $this->parseLines((string)$this->scopeConfig->getValue(self::XML_PATH_BLOCKED_URLS, ScopeInterface::SCOPE_STORE));
    }

    public function getHideHeaderFooterUrls(): array
    {
        return $this->parseLines((string)$this->scopeConfig->getValue(self::XML_PATH_HIDE_HEADER_FOOTER_URLS, ScopeInterface::SCOPE_STORE));
    }

    public function isDomainRestrictionEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_APPLY_DOMAIN_ONLY, ScopeInterface::SCOPE_STORE);
    }

    public function getConfiguredDomain(): string
    {
        return strtolower(trim((string)$this->scopeConfig->getValue(self::XML_PATH_DOMAIN, ScopeInterface::SCOPE_STORE)));
    }

    public function isCurrentDomainAllowed(string $host): bool
    {
        if (!$this->isDomainRestrictionEnabled()) {
            return true;
        }

        $configuredDomain = $this->getConfiguredDomain();
        if ($configuredDomain === '') {
            return true;
        }

        $normalizedHost = strtolower(trim(preg_replace('/:\d+$/', '', $host) ?? ''));

        return $normalizedHost === $configuredDomain;
    }

    public function matchesPath(string $path, array $patterns): bool
    {
        $normalizedPath = $this->normalizePath($path);

        foreach ($patterns as $pattern) {
            $normalizedPattern = $this->normalizePath($pattern);
            if ($normalizedPattern === '/') {
                continue;
            }

            if (
                $normalizedPath === $normalizedPattern
                || str_starts_with($normalizedPath, $normalizedPattern . '/')
                || str_contains($normalizedPath, $normalizedPattern)
            ) {
                return true;
            }
        }

        return false;
    }

    private function parseLines(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim((string)$line);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }

        return array_values(array_unique($result));
    }

    private function normalizePath(string $path): string
    {
        $path = strtolower(trim($path));
        $path = strtok($path, '?') ?: $path;
        $path = '/' . trim($path, '/');

        if ($path === '/index.php') {
            return '/';
        }

        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php'));
        }

        return $path === '' ? '/' : $path;
    }
}
