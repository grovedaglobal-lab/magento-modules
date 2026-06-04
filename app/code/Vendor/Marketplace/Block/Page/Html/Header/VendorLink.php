<?php
namespace Vendor\Marketplace\Block\Page\Html\Header;

use Magento\Framework\View\Element\Html\Link;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Http\Context;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Vendor\Marketplace\Model\VendorFactory;

class VendorLink extends Link
{
    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var Context
     */
    protected $httpContext;

    /**
     * @var VendorFactory
     */
    protected $vendorFactory;

    /**
     * @param TemplateContext $context
     * @param Session $customerSession
     * @param Context $httpContext
     * @param VendorFactory $vendorFactory
     * @param array $data
     */
    public function __construct(
        TemplateContext $context,
        Session $customerSession,
        Context $httpContext,
        VendorFactory $vendorFactory,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->httpContext = $httpContext;
        $this->vendorFactory = $vendorFactory;
        parent::__construct($context, $data);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        // Check if customer is logged in
        $isLoggedIn = $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);

        if ($isLoggedIn) {
            // If logged in, we should check if they are a vendor or not.
            // However, usually we HIDE the 'Vendor Login' link if they are already logged in
            // because they should see 'My Account' or 'Vendor Dashboard' instead.
            return '';
        }

        // If not logged in, show the link
        return parent::_toHtml();
    }

    /**
     * @return string
     */
    public function getHref()
    {
        return $this->getUrl('marketplace/account/login');
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Vendor Login');
    }
}
