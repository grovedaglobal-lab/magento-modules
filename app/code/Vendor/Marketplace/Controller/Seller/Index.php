<?php
namespace Vendor\Marketplace\Controller\Seller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Vendor\Marketplace\Model\VendorFactory;

class Index extends Action
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
        $vendorId = $this->getRequest()->getParam('id');
        if (!$vendorId) {
            $this->_forward('noRoute');
            return;
        }

        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
