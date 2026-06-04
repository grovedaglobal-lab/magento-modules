<?php
namespace Vendor\Ads\Api;

use Vendor\Ads\Api\Data\SearchLinkInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface SearchLinkRepositoryInterface
{
    /**
     * @param SearchLinkInterface $searchLink
     * @return SearchLinkInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(SearchLinkInterface $searchLink);

    /**
     * @param int $id
     * @return SearchLinkInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * @param int $queryId
     * @return SearchLinkInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByQueryId($queryId);

    /**
     * @param SearchLinkInterface $searchLink
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(SearchLinkInterface $searchLink);

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Ads\Api\Data\SearchLinkSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
