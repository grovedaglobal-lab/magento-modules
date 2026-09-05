<?php
namespace Vendor\Marketplace\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\VendorFactory;

class Delete extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
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

        $formKeyValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Data\Form\FormKey\Validator::class);
        if (!$formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
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

                // Hard Delete: Truly delete the product from the catalog
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $registry = $objectManager->get(\Magento\Framework\Registry::class);
                $registry->register("isSecureArea", true);
                // Soft Delete: Unassign from vendor in EAV tables and disable
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
                $connection = $resource->getConnection();

                // Collect all related IDs if configurable
                $productIds = [(int)$product->getId()];
                if ($product->getTypeId() === "configurable") {
                    $configurable = $objectManager->get(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::class);
                    $children = $configurable->getUsedProducts($product);
                    foreach ($children as $child) {
                        $productIds[] = (int)$child->getId();
                    }
                }

                // Lookup vendor_id attribute ID
                $eavTable = $resource->getTableName("eav_attribute");
                $attrId = (int)$connection->fetchOne(
                    $connection->select()->from($eavTable, "attribute_id")
                        ->where("entity_type_id = ?", 4)
                        ->where("attribute_code = ?", "vendor_id")
                );

                $intTable = $resource->getTableName("catalog_product_entity_int");
                if ($attrId && !empty($productIds)) {
                    $connection->delete($intTable, [
                        "attribute_id = ?" => $attrId,
                        "entity_id IN (?)" => $productIds
                    ]);
                }

                // Set status disabled
                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
                $this->productRepository->save($product);

                $this->messageManager->addSuccessMessage(__("Product has been deleted successfully."));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error deleting product: %1', $e->getMessage()));
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
