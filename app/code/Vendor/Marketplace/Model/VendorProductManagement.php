<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorProductManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Authorization\Model\UserContextInterface;
use Vendor\Marketplace\Model\VendorRepository;

class VendorProductManagement implements VendorProductManagementInterface
{
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $userContext;
    protected $vendorRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        UserContextInterface $userContext,
        VendorRepository $vendorRepository
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->userContext = $userContext;
        $this->vendorRepository = $vendorRepository;
    }

    protected function getVendorId()
    {
        $customerId = $this->userContext->getUserId();
        // assuming running in context of customer (Vendor)
        $vendor = $this->vendorRepository->getByCustomerId($customerId);
        return $vendor->getEntityId();
    }

    public function getMyProducts(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $vendorId = $this->getVendorId();

        // Force the vendor_id filter
        // Note: Direct manipulation of SearchCriteria can be complex due to groups. 
        // A cleaner way is to create a new filter group but standard Repo doesn't merge well if strict.
        // For simplicity in this demo, we assume we can append a filter.

        // Actually, we must ensure we ADD query filter for vendor_id.
        // We cannot modify the interface passed by reference effectively usually.
        // But let's try to pass the builder.

        // BETTER APPROACH: Add filter to the builder and create NEW criteria?
        // But we want to respect pagination/sorting from $searchCriteria.

        // Hack for now: Rebuild filters.
        // In production, use Collection Processor plugin or careful rebuilding.

        // Let's rely on adding a Filter manually if possible, or just using collection directly? 
        // But we must return SearchResults.

        // Let's use the current Criteria but append the filter.
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterBuilder = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\FilterBuilder::class);
        $filter = $filterBuilder->setField('vendor_id')
            ->setValue($vendorId)
            ->setConditionType('eq')
            ->create();

        $filterGroupBuilder = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\Search\FilterGroupBuilder::class);
        $filterGroup = $filterGroupBuilder->addFilter($filter)->create();

        $filterGroups[] = $filterGroup;
        $searchCriteria->setFilterGroups($filterGroups);

        return $this->productRepository->getList($searchCriteria);
    }

    public function deleteMyProduct($sku)
    {
        $vendorId = $this->getVendorId();
        $product = $this->productRepository->get($sku);

        if ($product->getData('vendor_id') != $vendorId) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You do not have permission to delete this product.')
            );
        }

        return $this->productRepository->delete($product);
    }
}
