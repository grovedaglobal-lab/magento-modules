<?php
namespace Vendor\Marketplace\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface VendorSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get vendor list.
     * @return \Vendor\Marketplace\Api\Data\VendorInterface[]
     */
    public function getItems();

    /**
     * Set vendor list.
     * @param \Vendor\Marketplace\Api\Data\VendorInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
