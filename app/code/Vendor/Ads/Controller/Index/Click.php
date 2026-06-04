<?php
namespace Vendor\Ads\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Vendor\Ads\Service\BillingService;
use Magento\Catalog\Api\ProductRepositoryInterface;

class Click extends Action
{
    /** @var BillingService */
    protected $billingService;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    public function __construct(
        Context $context,
        BillingService $billingService,
        ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->billingService = $billingService;
        $this->productRepository = $productRepository;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $bidId = $this->getRequest()->getParam('bid');
        $productId = $this->getRequest()->getParam('product_id');

        if ($bidId && $productId) {
            $this->billingService->processClick($bidId);
            
            // Store mapping in session for conversion tracking
            $sponsoredBids = $this->checkoutSession->getSponsoredBids() ?: [];
            $sponsoredBids[$productId] = $bidId;
            $this->checkoutSession->setSponsoredBids($sponsoredBids);
        }

        if ($productId) {
            try {
                $product = $this->productRepository->getById($productId);
                return $this->_redirect($product->getProductUrl());
            } catch (\Exception $e) {
                // Fallback to home if product not found
            }
        }

        return $this->_redirect('/');
    }
}
