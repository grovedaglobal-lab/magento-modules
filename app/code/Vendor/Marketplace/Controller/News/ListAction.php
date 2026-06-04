<?php
namespace Vendor\Marketplace\Controller\News;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Model\Session\VendorSession;

class ListAction extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param VendorSession $vendorSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        VendorSession $vendorSession
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->vendorSession = $vendorSession;
    }

    /**
     * News list action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->vendorSession->isLoggedIn()) {
            return $this->_redirect('marketplace/account/login');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Admin News'));

        return $resultPage;
    }
}
