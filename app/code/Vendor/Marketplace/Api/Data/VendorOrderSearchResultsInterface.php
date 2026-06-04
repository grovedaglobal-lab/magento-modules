<?php
namespace Vendor\Marketplace\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface VendorOrderSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get vendor order list.
     * @return \Vendor\Marketplace\Api\Data\VendorOrderInterface[]
     */
    public function getItems();

    /**
     * Set vendor order list.
     * @param \Vendor\Marketplace\Api\Data\VendorOrderInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
