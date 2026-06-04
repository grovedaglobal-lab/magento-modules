<?php
namespace Vendor\Ads\Model;

use Vendor\Ads\Api\SearchLinkRepositoryInterface;
use Vendor\Ads\Api\Data\SearchLinkInterface;
use Vendor\Ads\Model\ResourceModel\SearchLink as ResourceSearchLink;
use Vendor\Ads\Model\ResourceModel\SearchLink\CollectionFactory as SearchLinkCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;

class SearchLinkRepository implements SearchLinkRepositoryInterface
{
    protected $resource;
    protected $searchLinkFactory;
    protected $collectionFactory;
    protected $collectionProcessor;
    protected $searchResultsFactory;

    public function __construct(
        ResourceSearchLink $resource,
        SearchLinkFactory $searchLinkFactory,
        SearchLinkCollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        \Vendor\Ads\Api\Data\SearchLinkSearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->searchLinkFactory = $searchLinkFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(SearchLinkInterface $searchLink)
    {
        try {
            $this->resource->save($searchLink);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $searchLink;
    }

    public function getById($id)
    {
        $searchLink = $this->searchLinkFactory->create();
        $this->resource->load($searchLink, $id);
        if (!$searchLink->getId()) {
            throw new NoSuchEntityException(__('SearchLink with ID %1 does not exist.', $id));
        }
        return $searchLink;
    }

    public function getByQueryId($queryId)
    {
        $searchLink = $this->searchLinkFactory->create();
        $this->resource->load($searchLink, $queryId, 'query_id');
        if (!$searchLink->getId()) {
            // Return empty object instead of error? 
            // Better to throw error or create one.
            throw new NoSuchEntityException(__('SearchLink with query ID %1 does not exist.', $queryId));
        }
        return $searchLink;
    }

    public function delete(SearchLinkInterface $searchLink)
    {
        try {
            $this->resource->delete($searchLink);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }
}
