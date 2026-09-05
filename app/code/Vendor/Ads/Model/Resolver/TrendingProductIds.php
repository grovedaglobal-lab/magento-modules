<?php
declare(strict_types=1);

namespace Vendor\Ads\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\App\ResourceConnection;

/**
 * GraphQL resolver: trendingProductIds
 *
 * Reads real-time product views directly from report_event table.
 * Returns both product_ids and skus so the frontend can filter by sku.
 * No cron aggregation delay â€” works immediately after a product is viewed.
 */
class TrendingProductIds implements ResolverInterface
{
    /** @var ResourceConnection */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $limit      = max(1, min((int)($args['limit'] ?? 8), 50));
        $minViews   = max(0, (int)($args['minViews'] ?? 0));
        $categoryId = isset($args['categoryId']) && $args['categoryId'] > 0
            ? (int)$args['categoryId']
            : null;

        $connection   = $this->resource->getConnection();
        $eventTable   = $this->resource->getTableName('report_event');
        $productTable = $this->resource->getTableName('catalog_product_entity');

        try {
            // Aggregate views from the real-time report_event table
            // Join catalog_product_entity to get SKU for frontend filtering
            $select = $connection->select()
                ->from(['r' => $eventTable], [
                    'product_id'   => 'r.object_id',
                    'total_views'  => new \Zend_Db_Expr('COUNT(r.event_id)'),
                ])
                ->join(
                    ['p' => $productTable],
                    'p.entity_id = r.object_id',
                    ['sku']
                )
                ->where('r.event_type_id = ?', 1) // 1 = catalog_product_view
                ->group('r.object_id')
                ->order('total_views DESC')
                ->limit($limit);

            if ($minViews > 0) {
                $select->having('COUNT(r.event_id) >= ?', $minViews);
            }

            if ($categoryId !== null) {
                $categoryProductTable = $this->resource->getTableName('catalog_category_product');
                $select->join(
                    ['cp' => $categoryProductTable],
                    'cp.product_id = r.object_id AND cp.category_id = ' . $categoryId,
                    []
                );
            }

            $rows = $connection->fetchAll($select);

        } catch (\Exception $e) {
            return ['product_ids' => [], 'skus' => []];
        }

        return [
            'product_ids' => array_map(fn($row) => (int)$row['product_id'], $rows),
            'skus'        => array_map(fn($row) => (string)$row['sku'], $rows),
        ];
    }
}
