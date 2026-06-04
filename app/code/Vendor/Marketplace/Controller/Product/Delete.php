<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\VendorFactory;

class Delete extends Action
{
    protected $customerSession;
    protected $productRepository;
    protected $vendorFactory;

    public function __construct(
        Context $context,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        VendorFactory $vendorFactory
    ) {
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
                $customerId = $this->customerSession->getCustomerId();
                $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

                $product = $this->productRepository->getById($id);

                // Security Check
                if ($product->getData('vendor_id') != $vendor->getId()) {
                    throw new \Exception(__('You do not have permission to delete this product.'));
                }

                // Soft Delete: Disable instead of delete
                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
                $this->productRepository->save($product);

                $this->messageManager->addSuccessMessage(__('Product has been disabled (soft deleted).'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error deleting product: %1', $e->getMessage()));
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
