<?php
namespace Vendor\Marketplace\Controller\Coupon;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class NewAction extends Action
{
    public function execute()
    {
        $this->_forward('edit');
    }
}
