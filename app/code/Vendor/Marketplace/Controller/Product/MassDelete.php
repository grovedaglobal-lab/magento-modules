<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;

class MassDelete extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    protected $customerSession;
    protected $vendorFactory;
    protected $productRepository;
    protected $collectionFactory;
    protected $registry;

    public function __construct(
        Context $context,
        Session $customerSession,
        VendorFactory $vendorFactory,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $collectionFactory,
        Registry $registry
    ) {
        $this->customerSession = $customerSession;
        $this->vendorFactory = $vendorFactory;
        $this->productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->registry = $registry;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $formKeyValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Data\Form\FormKey\Validator::class);
        if (!$formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key.'));
            return $this->_redirect('*/*/index');
        }

        $ids = $this->getRequest()->getParam('product_ids');

        if (!is_array($ids) || empty($ids)) {
            $this->messageManager->addErrorMessage(__('Please select products to delete.'));
            return $this->_redirect('*/*/index');
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $vendor = $this->vendorFactory->create()->load($customerId, 'customer_id');

            if (!$vendor->getId()) {
                $this->messageManager->addErrorMessage(__('Vendor profile not found.'));
                return $this->_redirect('*/*/index');
            }

            $collection = $this->collectionFactory->create();
            $collection->addAttributeToFilter('entity_id', ['in' => $ids]);
            $collection->addAttributeToFilter('vendor_id', $vendor->getId());

            $this->registry->register('isSecureArea', true);
            $count = 0;
            foreach ($collection as $product) {
                // Hard Delete: Truly delete the product from the catalog
                // Soft Delete: Unassign from vendor in EAV tables and disable
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
                $connection = $resource->getConnection();
                $productIds = [(int)$product->getId()];
                if ($product->getTypeId() === "configurable") {
                    $configurable = $objectManager->get(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::class);
                    foreach ($configurable->getUsedProducts($product) as $child) {
                        $productIds[] = (int)$child->getId();
                    }
                }
                $attrId = (int)$connection->fetchOne(
                    $connection->select()->from($resource->getTableName("eav_attribute"), "attribute_id")
                        ->where("entity_type_id = ?", 4)->where("attribute_code = ?", "vendor_id")
                );
                if ($attrId && !empty($productIds)) {
                    $connection->delete($resource->getTableName("catalog_product_entity_int"), [
                        "attribute_id = ?" => $attrId,
                        "entity_id IN (?)" => $productIds
                    ]);
                }
                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
                $this->productRepository->save($product);
                $count++;
                $count++;
            }
            $this->registry->unregister('isSecureArea');

            $this->messageManager->addSuccessMessage(__("A total of %1 record(s) have been deleted.", $count));
        } catch (\Exception $e) {
            // ensure registry is unregistered in case of error
            if ($this->registry->registry('isSecureArea')) {
                $this->registry->unregister('isSecureArea');
            }

            $this->messageManager->addErrorMessage(__('An error occurred while deleting products: %1', $e->getMessage()));
        }

        return $this->_redirect('*/*/index');
    }
}
