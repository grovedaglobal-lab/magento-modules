<?php
declare(strict_types=1);

namespace Vendor\AccessControl\Plugin;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\FrontController;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ResponseInterface;
use Vendor\AccessControl\Helper\Data;

class FrontControllerPlugin
{
    /**
     * Always bypass access control for these technical routes.
     */
    private const SAFETY_BYPASS_PREFIXES = [
        '/admin',
        '/rest',
        '/graphql',
        '/static',
        '/media',
        '/customer/section/load',
        '/pub/static',
        '/pub/media'
    ];

    private const SAFETY_BYPASS_SEGMENTS = [
        '/static/',
        '/media/',
        '/graphql',
        '/rest/'
    ];

    /**
     * Public customer routes that should remain accessible when logged out.
     */
    private const DEFAULT_ALLOWED_PATHS = [
        '/customer/account/login',
        '/customer/account/create',
        '/customer/account/forgotpassword',
        '/customer/account/forgotpasswordpost',
        '/customer/account/createpassword',
        '/customer/account/resetpasswordpost',
        '/marketplace/account/login',
        '/marketplace/account/create',
        '/marketplace/account/forgotpassword',
        '/marketplace/account/forgotpasswordpost',
        '/marketplace/account/createpassword',
        '/marketplace/account/resetpasswordpost'
    ];

    public function __construct(
        private readonly Data $helper,
        private readonly CustomerSession $customerSession,
        private readonly ResponseFactory $responseFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @param callable $proceed
     * @return ResponseInterface|ResultInterface
     */
    public function aroundDispatch(FrontController $subject, callable $proceed, RequestInterface $request)
    {
        if ($this->isAssetRequest($request)) {
            return $proceed($request);
        }

        if (!$this->helper->isEnabled()) {
            return $proceed($request);
        }

        $pathInfo = ltrim((string)$request->getPathInfo(), '/');
        $pathSegments = explode('/', $pathInfo);
        $firstSegment = strtolower($pathSegments[0]);

        $adminFrontName = strtolower(trim($this->helper->getAdminFrontName(), '/'));
        if ($firstSegment === $adminFrontName || $firstSegment === 'admin') {
            return $proceed($request);
        }

        if (!$this->helper->isCurrentDomainAllowed((string)$request->getHttpHost())) {
            return $proceed($request);
        }

        $path = $this->normalizePath((string)$request->getPathInfo());

        if ($this->isSafetyBypassPath($path)) {
            return $proceed($request);
        }

        $allowedUrls = array_merge(self::DEFAULT_ALLOWED_PATHS, $this->helper->getAllowedUrls());
        $blockedUrls = $this->helper->getBlockedUrls();

        $isBlockedPath = $this->helper->matchesPath($path, $blockedUrls);
        $isAllowedPath = $this->helper->matchesPath($path, $allowedUrls);

        $isLoggedOut = !$this->customerSession->isLoggedIn();
        $hasBlockedRules = !empty($blockedUrls);

        // Explicitly allowed paths (e.g. login, create account, forgot password) must never redirect.
        $shouldRedirect = $isLoggedOut && (
            ($hasBlockedRules && $isBlockedPath && !$isAllowedPath)
            || (!$hasBlockedRules && !$isAllowedPath)
        );

        if ($shouldRedirect) {
            $loginUrl = $this->urlBuilder->getUrl('customer/account/login');
            $response = $this->responseFactory->create();
            $response->setRedirect($loginUrl);
            return $response;
        }

        return $proceed($request);
    }

    private function isSafetyBypassPath(string $path): bool
    {
        // Explicitly allow all /static/ paths (including versioned ones like /static/version{hash}/...)
        if (str_starts_with($path, '/static/') || $path === '/static.php' || str_starts_with($path, '/static.php?')) {
            return true;
        }

        foreach (self::SAFETY_BYPASS_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        $adminFrontName = $this->helper->getAdminFrontName();
        $adminPrefix = '/' . trim($adminFrontName, '/');
        if ($path === $adminPrefix || str_starts_with($path, $adminPrefix . '/')) {
            return true;
        }

        // Handle prefixed paths like /index.php/pub/static or /storecode/static.
        foreach (self::SAFETY_BYPASS_SEGMENTS as $segment) {
            if (str_contains($path, $segment)) {
                return true;
            }
        }

        // Never gate common static asset file requests.
        if (preg_match('/\.(css|js|map|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf|eot|ico|otf|avif)$/', $path) === 1) {
            return true;
        }

        return false;
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

    private function isAssetRequest(RequestInterface $request): bool
    {
        $path = strtolower((string)$request->getPathInfo());
        $path = strtok($path, '?') ?: $path;

        return preg_match('/\.(css|js|map|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|eot|otf|avif)(?:$|[?#])/', $path) === 1;
    }
}
