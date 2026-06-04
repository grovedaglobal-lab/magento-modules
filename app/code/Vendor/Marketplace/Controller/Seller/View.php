<?php
namespace Vendor\Marketplace\Controller\Seller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Model\VendorFactory;

class View extends Action
{
    protected $resultPageFactory;
    protected $vendorFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        VendorFactory $vendorFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->vendorFactory = $vendorFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        // Get shop URL from request
        $shopUrl = $this->getRequest()->getParam('shop');

        if (!$shopUrl) {
            // Try to get vendor ID
            $vendorId = $this->getRequest()->getParam('id');
            if (!$vendorId) {
                $this->_forward('noRoute');
                return;
            }

            $vendor = $this->vendorFactory->create()->load($vendorId);
        } else {
            // Load vendor by shop URL
            $vendor = $this->vendorFactory->create()->load($shopUrl, 'shop_url');
        }

        if (!$vendor->getId()) {
            $this->_forward('noRoute');
            return;
        }

        // Store vendor in registry for use in blocks
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $registry = $objectManager->get(\Magento\Framework\Registry::class);

        if (!$registry->registry('current_vendor')) {
            $registry->register('current_vendor', $vendor);
        }

        $resultPage = $this->resultPageFactory->create();

        // Set page title
        $vendorProfile = $objectManager->get(\Vendor\Marketplace\Model\VendorProfileFactory::class)
            ->create()
            ->load($vendor->getId(), 'vendor_id');

        $pageTitle = $vendorProfile->getShopName() ?: 'Vendor Shop';
        $resultPage->getConfig()->getTitle()->set($pageTitle);

        return $resultPage;
    }
}
