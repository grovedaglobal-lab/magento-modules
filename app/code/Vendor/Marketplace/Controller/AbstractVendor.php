<?php
namespace Vendor\Marketplace\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;

abstract class AbstractVendor extends Action
{
    /**
     * @var VendorSession
     */
    protected $_vendorSession;

    /**
     * @param Context $context
     * @param VendorSession $vendorSession
     */
    public function __construct(
        Context $context,
        VendorSession $vendorSession
    ) {
        $this->_vendorSession = $vendorSession;
        parent::__construct($context);
    }

    /**
     * Check vendor session
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {
        if (!$this->_vendorSession->isLoggedIn()) {
            $this->messageManager->addErrorMessage(__('Please log in to your vendor account.'));
            return $this->_redirect('marketplace/account/login');
        }
        return parent::dispatch($request);
    }
}
