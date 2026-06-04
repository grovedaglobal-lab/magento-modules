<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorOrderManagementInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder\CollectionFactory as OrderCollectionFactory;
use Magento\Authorization\Model\UserContextInterface;
use Vendor\Marketplace\Model\VendorRepository;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Vendor\Marketplace\Api\Data\VendorOrderSearchResultsInterfaceFactory;

class VendorOrderManagement implements VendorOrderManagementInterface
{
    protected $collectionFactory;
    protected $userContext;
    protected $vendorRepository;
    protected $collectionProcessor;
    protected $searchResultsFactory;

    public function __construct(
        OrderCollectionFactory $collectionFactory,
        UserContextInterface $userContext,
        VendorRepository $vendorRepository,
        CollectionProcessorInterface $collectionProcessor,
        VendorOrderSearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->userContext = $userContext;
        $this->vendorRepository = $vendorRepository;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    protected function getVendorId()
    {
        $customerId = $this->userContext->getUserId();
        $vendor = $this->vendorRepository->getByCustomerId($customerId);
        return $vendor->getEntityId();
    }

    public function getMyOrders(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $vendorId = $this->getVendorId();

        $collection = $this->collectionFactory->create();

        // Force Vendor Filter
        // We cannot trust SearchCriteria to not be manipulated by client to show other data if we just allow "all"
        // So we manually add filter, or append to criteria. 
        // Safer to filter Collection directly AFTER processing criteria? 
        // No, processing first applies limits.

        // Best practice: Add filter to criteria.
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

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }
}
