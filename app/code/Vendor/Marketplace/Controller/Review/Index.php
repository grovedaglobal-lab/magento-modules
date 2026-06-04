<?php
namespace Vendor\Marketplace\Controller\Review;

use Vendor\Marketplace\Controller\AbstractVendor;

use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractVendor
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Vendor\Marketplace\Model\Session\VendorSession $vendorSession
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Vendor\Marketplace\Model\Session\VendorSession $vendorSession,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context, $vendorSession);

        // Debug
        file_put_contents(BP . '/var/log/debug_custom.log', "Review List Controller (Index.php) Initialized\n", FILE_APPEND);
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        // Debug
        file_put_contents(BP . '/var/log/debug_custom.log', "Review List action executed\n", FILE_APPEND);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Product Reviews'));
        return $resultPage;
    }
}