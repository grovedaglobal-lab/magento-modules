<?php
namespace Vendor\Ads\Plugin;

use Magento\Catalog\Model\Layer;
use Vendor\Ads\Helper\SearchRanker;
use Vendor\Ads\Model\SearchState;
use Vendor\Ads\Service\BillingService;

class CategoryCollectionPlugin
{
    /** @var SearchRanker */
    protected $searchRanker;

    /** @var SearchState */
    protected $searchState;

    /** @var BillingService */
    protected $billingService;

    public function __construct(
        SearchRanker $searchRanker,
        SearchState $searchState,
        BillingService $billingService
    ) {
        $this->searchRanker = $searchRanker;
        $this->searchState = $searchState;
        $this->billingService = $billingService;
    }

    /**
     * Inject sponsored products into category product collection
     *
     * @param Layer $subject
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function afterGetProductCollection(Layer $subject, $collection)
    {
        // Don't inject if already loaded or not a category page
        if ($collection->isLoaded()) {
            return $collection;
        }

        $category = $subject->getCurrentCategory();
        if (!$category || !$category->getId() || $category->getId() == $category->getRootCategoryId()) {
            return $collection;
        }

        try {
            $categoryName = $category->getName();
            $sponsoredData = $this->searchRanker->getSponsoredProducts($categoryName);

            if (empty($sponsoredData)) {
                return $collection;
            }

            $sponsoredBids = [];
            $sponsoredIds = [];
            foreach ($sponsoredData as $productId => $data) {
                $sponsoredBids[$productId] = $data['bid_id'];
                $sponsoredIds[] = $productId;
                
                // Track impression
                $this->billingService->trackStat($data['bid_id'], 'impression');
            }

            // Sync with SearchState for the labeling plugin
            $this->searchState->setSponsoredProductBids($sponsoredBids);

            // Add sponsored products to the collection
            // We use a union or just ensure they are included and moved to top
            $collection->addAttributeToFilter('entity_id', ['in' => array_keys($sponsoredBids)], 'left');
            
            // Re-order collection to put sponsored items at the top
            $orderSql = 'CASE ';
            $i = 0;
            foreach (array_keys($sponsoredBids) as $id) {
                $orderSql .= "WHEN e.entity_id = " . (int)$id . " THEN " . $i++ . " ";
            }
            $orderSql .= "ELSE 9999 END";
            
            $collection->getSelect()->order(new \Zend_Db_Expr($orderSql));

        } catch (\Exception $e) {
            // Fail silently to not break category pages
        }

        return $collection;
    }
}
