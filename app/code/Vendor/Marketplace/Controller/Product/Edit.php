<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\VendorFactory;

class Edit extends Action
{
    protected $resultPageFactory;
    protected $customerSession;
    protected $productRepository;
    protected $vendorFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        VendorFactory $vendorFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->vendorFactory = $vendorFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                // Security check: Ensure product belongs to vendor
                $customerId = $this->customerSession->getCustomerId();
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

                $product = $this->productRepository->getById($id);
                if ($product->getData('vendor_id') != $vendor->getId()) {
                    $this->messageManager->addErrorMessage(__('You do not have permission to edit this product.'));
                    return $this->resultRedirectFactory->create()->setPath('*/*/index');
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Product not found or access denied.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $title = $id ? __('Edit Product') : __('Add New Product');
        $resultPage->getConfig()->getTitle()->set($title);
        return $resultPage;
    }
}
