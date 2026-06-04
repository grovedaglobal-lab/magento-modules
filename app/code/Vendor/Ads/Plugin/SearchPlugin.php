<?php
namespace Vendor\Ads\Plugin;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\Search\DocumentFactory;
use Vendor\Ads\Helper\SearchRanker;

class SearchPlugin
{
    /** @var SearchRanker */
    protected $searchRanker;

    /** @var SearchState */
    protected $searchState;

    /** @var DocumentFactory */
    protected $documentFactory;

    /** @var \Vendor\Ads\Service\BillingService */
    protected $billingService;

    public function __construct(
        SearchRanker $searchRanker,
        DocumentFactory $documentFactory,
        \Vendor\Ads\Model\SearchState $searchState,
        \Vendor\Ads\Service\BillingService $billingService
    ) {
        $this->searchRanker = $searchRanker;
        $this->documentFactory = $documentFactory;
        $this->searchState = $searchState;
        $this->billingService = $billingService;
    }

    /**
     * Inject sponsored products into search results
     *
     * @param SearchInterface $subject
     * @param SearchResultInterface $result
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultInterface
     */
    public function afterSearch(
        SearchInterface $subject,
        SearchResultInterface $result,
        SearchCriteriaInterface $searchCriteria
    ) {
        $queryText = '';
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if ($filter->getField() === 'search_term') {
                    $queryText = $filter->getValue();
                    break 2;
                }
            }
        }

        if (empty($queryText)) {
            return $result;
        }

        // Only inject ads on the first page
        if ($searchCriteria->getCurrentPage() > 1) {
            return $result;
        }

        try {
            $sponsoredData = $this->searchRanker->getSponsoredProducts($queryText);
            if (empty($sponsoredData)) {
                return $result;
            }

            $sponsoredBids = [];
            foreach ($sponsoredData as $productId => $data) {
                $sponsoredBids[$productId] = $data['bid_id'];
            }
            $this->searchState->setSponsoredProductBids($sponsoredBids);

            $items = $result->getItems();
            $itemMap = [];
            foreach ($items as $item) {
                $itemMap[$item->getId()] = $item;
            }

            $newItems = [];
            foreach ($sponsoredData as $productId => $data) {
                $score = $data['score'];
                if (isset($itemMap[$productId])) {
                    $document = $itemMap[$productId];
                    unset($itemMap[$productId]);
                } else {
                    $document = $this->documentFactory->create([
                        'data' => [
                            'entity_id' => $productId,
                            'id' => $productId,
                            'score' => $score
                        ]
                    ]);
                }
                
                $document->setData('is_sponsored', true);
                $newItems[] = $document;

                $this->billingService->trackStat($data['bid_id'], 'impression');
            }

            foreach ($itemMap as $item) {
                $newItems[] = $item;
            }

            $result->setItems($newItems);
            return $result;
        } catch (\Exception $e) {
            // Log and fallback to normal search
            if (isset($this->_logger)) {
                $this->_logger->error('Vendor Ads Plugin Error: ' . $e->getMessage());
            }
            return $result;
        }
    }
}
