<?php
namespace Vendor\Marketplace\Controller\Report;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\Controller\ResultFactory;

class Earnings extends AbstractVendor
{
    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set(__('Vendor Earnings'));
        return $resultPage;
    }
}
