<?php
namespace Vendor\Marketplace\Block\Seller;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Navigation extends Template
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getMenuItems()
    {
        return [
            'dashboard' => [
                'label' => __('Marketplace Dashboard'),
                'url' => 'vendor_marketplace/seller/dashboard',
                'id' => 'dashboard'
            ],
            'profile' => [
                'label' => __('Seller Profile'),
                'url' => 'vendor_marketplace/seller/profile',
                'id' => 'profile'
            ],
            'create_attribute' => [
                'label' => __('Create Attribute'),
                'url' => 'vendor_marketplace/attribute/new',
                'id' => 'create_attribute'
            ],
            'new_product' => [
                'label' => __('New Products'),
                'url' => 'vendor_marketplace/product/new',
                'id' => 'new_product'
            ],
            'my_products' => [
                'label' => __('My Products List'),
                'url' => 'vendor_marketplace/product/list',
                'id' => 'my_products'
            ],
            'transactions' => [
                'label' => __('My Transaction List'),
                'url' => 'vendor_marketplace/transaction/list',
                'id' => 'transactions'
            ],
            'earnings' => [
                'label' => __('Earnings'),
                'url' => 'vendor_marketplace/seller/earnings',
                'id' => 'earnings'
            ],
            'pdf_header' => [
                'label' => __('Manage Print PDF Header Info'),
                'url' => 'vendor_marketplace/seller/pdfheader',
                'id' => 'pdf_header'
            ],
            'order_history' => [
                'label' => __('My Order History'),
                'url' => 'vendor_marketplace/order/history',
                'id' => 'order_history'
            ],
            'customers' => [
                'label' => __('Customers'),
                'url' => 'vendor_marketplace/seller/customers',
                'id' => 'customers'
            ],
            'review' => [
                'label' => __('Review'),
                'url' => 'vendor_marketplace/seller/review',
                'id' => 'review'
            ]
        ];
    }

    public function getCurrentUrl()
    {
        return $this->_urlBuilder->getCurrentUrl();
    }

    public function isCurrent($url)
    {
        return strpos($this->getCurrentUrl(), $this->getUrl($url)) !== false;
    }
}
