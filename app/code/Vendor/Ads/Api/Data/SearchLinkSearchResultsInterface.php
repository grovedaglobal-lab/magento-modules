<?php
namespace Vendor\Ads\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface SearchLinkSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Vendor\Ads\Api\Data\SearchLinkInterface[]
     */
    public function getItems();

    /**
     * @param \Vendor\Ads\Api\Data\SearchLinkInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
