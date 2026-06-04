<?php
namespace Vendor\Ads\Model\Resolver;

use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Helper\Config as AdsConfig;
use Magento\Framework\App\ResourceConnection;

/**
 * Dynamic vendor resolver that reads admin config to determine
 * which multi-vendor module table to use (Webkul, CedCommerce, Custom, etc.)
 */
class DynamicVendorResolver implements VendorResolverInterface
{
    // Known module presets: [vendor_table, id_column, name_column, customer_id_column]
    const PRESETS = [
        'custom_marketplace' => [
            'table'       => 'vendor_entity',
            'id'          => 'entity_id',
            'name'        => 'name',
            'customer_id' => 'customer_id',
        ],
        'webkul' => [
            'table'       => 'marketplace_userdata',
            'id'          => 'seller_id',
            'name'        => 'shop_title',
            'customer_id' => 'seller_id', // Webkul uses customer_id AS seller_id directly
        ],
        'cedcommerce' => [
            'table'       => 'ced_csmarketplace_vendor',
            'id'          => 'entity_id',
            'name'        => 'name',
            'customer_id' => 'customer_id',
        ],
        'appjetty' => [
            'table'       => 'aj_marketplace_seller',
            'id'          => 'seller_id',
            'name'        => 'shop_name',
            'customer_id' => 'customer_id',
        ],
    ];

    protected $config;
    protected $resource;
    protected $connection;

    public function __construct(AdsConfig $config, ResourceConnection $resource)
    {
        $this->config = $config;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
    }

    /**
     * Resolve the table schema based on admin config
     */
    protected function getSchema(): array
    {
        $module = $this->config->getVendorModule();

        if (isset(self::PRESETS[$module])) {
            return self::PRESETS[$module];
        }

        // "custom" mode — admin has manually configured the columns
        return [
            'table'       => $this->config->getVendorTable(),
            'id'          => $this->config->getVendorIdColumn(),
            'name'        => $this->config->getVendorNameColumn(),
            'customer_id' => $this->config->getCustomerIdColumn(),
        ];
    }

    public function getVendorIdByCustomer(int $customerId): ?int
    {
        try {
            $schema = $this->getSchema();
            $table  = $this->resource->getTableName($schema['table']);

            $result = $this->connection->fetchOne(
                $this->connection->select()
                    ->from($table, [$schema['id']])
                    ->where("{$schema['customer_id']} = ?", $customerId)
                    ->limit(1)
            );

            return $result ? (int)$result : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getVendorName(int $vendorId): ?string
    {
        if (!$vendorId) {
            return __('N/A');
        }

        try {
            $schema = $this->getSchema();
            $table  = $this->resource->getTableName($schema['table']);

            // Special handling for the current marketplace (vendor_entity + vendor_profile + customer_entity)
            if ($schema['table'] == 'vendor_entity') {
                $select = $this->connection->select()
                    ->from(['ve' => $table], [])
                    ->joinLeft(
                        ['vp' => $this->resource->getTableName('vendor_profile')],
                        've.entity_id = vp.vendor_id',
                        ['shop_name']
                    )->joinLeft(
                        ['ce' => $this->resource->getTableName('customer_entity')],
                        've.customer_id = ce.entity_id',
                        ['firstname', 'lastname']
                    )->where('ve.entity_id = ?', $vendorId)
                    ->limit(1);
                
                $data = $this->connection->fetchRow($select);
                if ($data) {
                    if (!empty($data['shop_name'])) {
                        return $data['shop_name'];
                    }
                    if (!empty($data['firstname']) || !empty($data['lastname'])) {
                        return trim(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? ''));
                    }
                }
                return __('Vendor #%1', $vendorId);
            }

            // Fallback to standard single-table lookup
            $columns = $this->connection->describeTable($table);
            if (isset($columns[$schema['name']])) {
                $result = $this->connection->fetchOne(
                    $this->connection->select()
                        ->from($table, [$schema['name']])
                        ->where("{$schema['id']} = ?", $vendorId)
                        ->limit(1)
                );
                if ($result) {
                    return $result;
                }
            }

            return __('Vendor #%1', $vendorId);
        } catch (\Exception $e) {
            return __('Vendor #%1', $vendorId);
        }
    }

    public function isVendor(int $customerId): bool
    {
        return $this->getVendorIdByCustomer($customerId) !== null;
    }
}
