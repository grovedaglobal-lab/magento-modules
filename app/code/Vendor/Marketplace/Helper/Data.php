<?php
namespace Vendor\Marketplace\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Vendor\Marketplace\Model\VendorFactory;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Magento\Framework\UrlInterface;

class Data extends AbstractHelper
{
    protected $vendorFactory;
    protected $vendorProfileFactory;
    protected $urlBuilder;
    protected $_productRepository;

    public function __construct(
        Context $context,
        VendorFactory $vendorFactory,
        VendorProfileFactory $vendorProfileFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->_productRepository = $productRepository;
        parent::__construct($context);
    }

    public function getVendorNameByProduct($product)
    {
        if (!$product) {
            return '';
        }
        $vendorId = $product->getData('vendor_id');
        if (!$vendorId) {
            // Try reloading if not in data
            try {
                $product = $this->_productRepository->getById($product->getId());
                $vendorId = $product->getData('vendor_id');
            } catch (\Exception $e) {
                return '';
            }
        }

        if ($vendorId) {
            $profile = $this->vendorProfileFactory->create()->load($vendorId, 'vendor_id');
            if ($profile->getId()) {
                return $profile->getShopName();
            }

            // Fallback to URL
            $vendor = $this->vendorFactory->create()->load($vendorId);
            if ($vendor->getId()) {
                return ucwords(str_replace('-', ' ', $vendor->getShopUrl()));
            }
        }
        return '';
    }

    public function getVendorUrlByProduct($product)
    {
        if (!$product) {
            return '';
        }
        $vendorId = $product->getData('vendor_id');
        if (!$vendorId) {
            try {
                $product = $this->_productRepository->getById($product->getId());
                $vendorId = $product->getData('vendor_id');
            } catch (\Exception $e) {
                return '';
            }
        }

        if ($vendorId) {
            $vendor = $this->vendorFactory->create()->load($vendorId);
            if ($vendor->getId() && $vendor->getShopUrl()) {
                // Use SEO-friendly shop-by-brand URL
                return $this->_urlBuilder->getUrl('shop-by-brand/' . $vendor->getShopUrl());
            }
        }
        return '';
    }
    public function getVendorOrderItems($order, $vendorId)
    {
        $items = [];
        if ($order) {
            foreach ($order->getAllItems() as $item) {
                if ($item->getParentItem())
                    continue;

                $product = $item->getProduct();
                $itemVendorId = $product ? $product->getData('vendor_id') : null;

                $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/vendor_items_debug.log');
                $logger = new \Zend_Log();
                $logger->addWriter($writer);
                $logger->info("HELPER: Item " . $item->getSku() . " ProductVendorID: " . ($itemVendorId ?? 'NULL'));

                // If vendor ID missing, try reload (safeguard)
                if (!$itemVendorId) {
                    try {
                        $p = $this->_productRepository->getById($item->getProductId());
                        $itemVendorId = $p->getData('vendor_id');
                        $logger->info("HELPER: Reloaded ProductVendorID: " . ($itemVendorId ?? 'NULL'));
                    } catch (\Exception $e) {
                        $logger->info("HELPER: Reload failed: " . $e->getMessage());
                    }
                }

                if ($itemVendorId == $vendorId) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    public function getProductById($productId)
    {
        try {
            return $this->_productRepository->getById($productId);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function isAutoApproveEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'vendor_marketplace/general/auto_approve_product',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
