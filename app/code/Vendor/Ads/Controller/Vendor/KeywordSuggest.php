<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Returns keyword suggestions from Magento's search_query table (store-scoped)
 */
class KeywordSuggest extends Action
{
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private \Magento\Framework\App\ResourceConnection $resource,
        private StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $q       = strtolower(trim($this->getRequest()->getParam('q', '')));
            $storeId = (int)$this->storeManager->getStore()->getId();

            $connection = $this->resource->getConnection();
            $table      = $this->resource->getTableName('search_query');

            // Primary: store-scoped
            $select = $connection->select()
                ->from($table, ['query_id', 'query_text', 'num_results', 'popularity'])
                ->where('query_text LIKE ?', '%' . $q . '%')
                ->where('store_id = ?', $storeId)
                ->where('num_results > 0')
                ->order('popularity DESC')
                ->limit(15);

            $rows = $connection->fetchAll($select);

            // Fallback: no store filter (catches store_id = 0 seed data in dev)
            if (empty($rows)) {
                $fallback = $connection->select()
                    ->from($table, ['query_id', 'query_text', 'num_results', 'popularity'])
                    ->where('query_text LIKE ?', '%' . $q . '%')
                    ->where('num_results > 0')
                    ->order('popularity DESC')
                    ->limit(15);
                $rows = $connection->fetchAll($fallback);
            }

            $suggestions = array_map(function ($r) {
                $popularity = (int)$r['popularity'];
                $demand     = $popularity >= 100 ? 'high'
                            : ($popularity >= 20  ? 'medium' : 'low');

                return [
                    'value'      => $r['query_text'], // Changed query_id to value for easier handling
                    'query_id'   => (int)$r['query_id'],
                    'label'      => $r['query_text'],
                    'results'    => (int)$r['num_results'],
                    'popularity' => $popularity,
                    'demand'     => $demand,
                ];
            }, $rows);

            return $result->setData([
                'success' => true,
                'suggestions' => $suggestions
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('KeywordSuggest Error: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
