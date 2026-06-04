<?php
namespace Vendor\Marketplace\Controller\Adminhtml\News;

use Magento\Backend\App\Action;

class NewAction extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'Vendor_Marketplace::manage_news';

    /**
     * Create new news action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        return $this->_forward('edit');
    }
}
