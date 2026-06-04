<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Returns bid intelligence: avg/min/max/competition for a keyword (store-scoped)
 */
class BidSuggest extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private \Magento\Framework\App\ResourceConnection $resource,
        private \Magento\Customer\Model\Session $customerSession,
        private \Vendor\Ads\Api\VendorResolverInterface $vendorResolver,
        private StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            if (!$this->customerSession->isLoggedIn()) {
                return $result->setData(['success' => false, 'error' => 'Not logged in']);
            }

            $keyword = strtolower(trim($this->getRequest()->getParam('keyword', '')));
            if (!$keyword) {
                return $result->setData(['success' => false, 'error' => 'No keyword']);
            }

            $storeId    = (int)$this->storeManager->getStore()->getId();
            $connection = $this->resource->getConnection();
            $queryTable = $this->resource->getTableName('search_query');
            $bidTable   = $this->resource->getTableName('vendor_ads_bid');

            // Find query_id scoped to current store
            $queryRow = $connection->fetchRow(
                $connection->select()
                    ->from($queryTable, ['query_id', 'popularity', 'num_results'])
                    ->where('query_text = ?', $keyword)
                    ->where('store_id = ?', $storeId)
                    ->limit(1)
            );

            if (!$queryRow) {
                // Fallback: try any store (keyword may be seeded on store 0)
                $queryRow = $connection->fetchRow(
                    $connection->select()
                        ->from($queryTable, ['query_id', 'popularity', 'num_results'])
                        ->where('query_text = ?', $keyword)
                        ->limit(1)
                );
            }

            $queryId    = $queryRow['query_id'] ?? null;
            $popularity = (int)($queryRow['popularity'] ?? 0);
            $numResults = (int)($queryRow['num_results'] ?? 0);
            $demand     = $popularity >= 100 ? 'high' : ($popularity >= 20 ? 'medium' : 'low');

            $stats = $connection->fetchRow(
                $connection->select()
                    ->from($bidTable, [
                        'count' => 'COUNT(*)',
                        'min'   => 'MIN(bid_amount)',
                        'avg'   => 'AVG(bid_amount)',
                        'max'   => 'MAX(bid_amount)',
                    ])
                    ->where('query_id = ?', $queryId)
                    ->where('is_active = 1')
            );

            $count = (int)($stats['count'] ?? 0);
            $avg   = $count ? round((float)$stats['avg'], 2) : null;
            $min   = $count ? round((float)$stats['min'], 2) : null;
            $max   = $count ? round((float)$stats['max'], 2) : null;

            // Suggested = avg + 10%, capped at max
            $suggested = $avg ? min(round($avg * 1.1, 2), $max) : null;

            $competition = 'low';
            if ($count >= 5) $competition = 'medium';
            if ($count >= 10) $competition = 'high';

            return $result->setData([
                'success'     => true,
                'suggested'   => $suggested,
                'min'         => $min,
                'avg'         => $avg,
                'max'         => $max,
                'competition' => $competition,
                'count'       => $count,
                'demand'      => $demand,
                'popularity'  => $popularity,
                'num_results' => $numResults,
            ]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
