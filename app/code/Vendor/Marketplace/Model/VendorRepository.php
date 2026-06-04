<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorRepositoryInterface;
use Vendor\Marketplace\Api\Data\VendorInterface;
use Vendor\Marketplace\Model\ResourceModel\Vendor as ResourceVendor;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory as VendorCollectionFactory;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Vendor\Marketplace\Api\Data\VendorSearchResultsInterfaceFactory;

class VendorRepository implements VendorRepositoryInterface
{
    protected $resource;
    protected $vendorFactory;
    protected $collectionFactory;
    protected $collectionProcessor;
    protected $searchResultsFactory;

    public function __construct(
        ResourceVendor $resource,
        VendorFactory $vendorFactory,
        VendorCollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        VendorSearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->vendorFactory = $vendorFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(VendorInterface $vendor)
    {
        try {
            $this->resource->save($vendor);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $vendor;
    }

    public function getById($vendorId)
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', $vendorId);
        $collection->setPageSize(1);
        /** @var \Vendor\Marketplace\Model\Vendor $vendor */
        $vendor = $collection->getFirstItem();

        if (!$vendor->getEntityId()) {
            throw new NoSuchEntityException(__('Vendor with id "%1" does not exist.', $vendorId));
        }
        return $vendor;
    }

    public function getByCustomerId($customerId)
    {
        $vendor = $this->vendorFactory->create();
        $this->resource->load($vendor, $customerId, 'customer_id');
        if (!$vendor->getEntityId()) {
            throw new NoSuchEntityException(__('Vendor with customer id "%1" does not exist.', $customerId));
        }
        return $vendor;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function getByShopUrl($shopUrl)
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('shop_url', $shopUrl);
        $collection->setPageSize(1);
        $vendor = $collection->getFirstItem();

        if (!$vendor->getEntityId()) {
            throw new NoSuchEntityException(__('Vendor with shop url "%1" does not exist.', $shopUrl));
        }
        return $vendor;
    }
}
