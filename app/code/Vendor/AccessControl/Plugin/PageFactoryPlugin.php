<?php
declare(strict_types=1);

namespace Vendor\AccessControl\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Vendor\AccessControl\Helper\Data;

class PageFactoryPlugin
{
    private const HIDE_LAYOUT_HANDLE = 'accesscontrol_hide_header_footer';

    public function __construct(
        private readonly Data $helper,
        private readonly RequestInterface $request
    ) {
    }

    public function afterCreate(PageFactory $subject, Page $resultPage): Page
    {
        if (!$this->helper->isEnabled()) {
            return $resultPage;
        }

        if (!$this->helper->isCurrentDomainAllowed((string)$this->request->getHttpHost())) {
            return $resultPage;
        }

        $path = (string)$this->request->getPathInfo();
        if ($this->helper->matchesPath($path, $this->helper->getHideHeaderFooterUrls())) {
            $resultPage->addHandle(self::HIDE_LAYOUT_HANDLE);
        }

        return $resultPage;
    }
}
