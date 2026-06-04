<?php
declare(strict_types=1);

namespace Vendor\AccessControl\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\Page;
use Vendor\AccessControl\Helper\Data;

class PageResultPlugin
{
    private const HIDE_LAYOUT_HANDLE = 'accesscontrol_hide_header_footer';

    public function __construct(
        private readonly Data $helper,
        private readonly RequestInterface $request
    ) {
    }

    public function beforeRenderResult(Page $subject, ResponseInterface $response): array
    {
        if (!$this->helper->isEnabled()) {
            return [$response];
        }

        if (!$this->helper->isCurrentDomainAllowed((string)$this->request->getHttpHost())) {
            return [$response];
        }

        $path = (string)$this->request->getPathInfo();
        if ($this->helper->matchesPath($path, $this->helper->getHideHeaderFooterUrls())) {
            $subject->addHandle(self::HIDE_LAYOUT_HANDLE);
        }

        return [$response];
    }
}
