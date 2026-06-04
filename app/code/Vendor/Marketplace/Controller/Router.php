<?php
namespace Vendor\Marketplace\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\ResponseInterface;
use Vendor\Marketplace\Model\VendorFactory;

class Router implements RouterInterface
{
    protected $actionFactory;
    protected $vendorFactory;

    public function __construct(
        ActionFactory $actionFactory,
        VendorFactory $vendorFactory
    ) {
        $this->actionFactory = $actionFactory;
        $this->vendorFactory = $vendorFactory;
    }

    public function match(RequestInterface $request)
    {
        $identifier = trim($request->getPathInfo(), '/');

        if (strpos($identifier, 'shop-by-brand/') === false) {
            return null;
        }

        if ($request->getModuleName() === 'marketplace') {
            return null;
        }

        if (empty($identifier)) {
            return null;
        }

        $prefix = 'shop-by-brand/';
        if (strpos($identifier, $prefix) !== 0) {
            return null;
        }

        $shopUrl = substr($identifier, strlen($prefix));

        // Check if the identifier matches a vendor shop URL
        $vendor = $this->vendorFactory->create()->load($shopUrl, 'shop_url');

        if (!$vendor->getId()) {
            return null;
        }

        $request->setModuleName('marketplace')
            ->setControllerName('seller')
            ->setActionName('index')
            ->setParam('id', $vendor->getId());

        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }
}
