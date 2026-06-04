<?php
namespace Vendor\Ads\Service;

use Magento\Framework\App\ResourceConnection;
use Vendor\Ads\Helper\Config as AdsConfig;

class VendorCustomerResolver
{
    const PRESETS = [
        'custom_marketplace' => [
            'table' => 'vendor_entity',
            'id' => 'entity_id',
            'customer_id' => 'customer_id',
        ],
        'webkul' => [
            'table' => 'marketplace_userdata',
            'id' => 'seller_id',
            'customer_id' => 'seller_id',
        ],
        'cedcommerce' => [
            'table' => 'ced_csmarketplace_vendor',
            'id' => 'entity_id',
            'customer_id' => 'customer_id',
        ],
        'appjetty' => [
            'table' => 'aj_marketplace_seller',
            'id' => 'seller_id',
            'customer_id' => 'customer_id',
        ],
    ];

    protected $resource;
    protected $adsConfig;

    public function __construct(ResourceConnection $resource, AdsConfig $adsConfig)
    {
        $this->resource = $resource;
        $this->adsConfig = $adsConfig;
    }

    protected function getSchema(): array
    {
        $module = $this->adsConfig->getVendorModule();
        if (isset(self::PRESETS[$module])) {
            return self::PRESETS[$module];
        }

        return [
            'table' => $this->adsConfig->getVendorTable(),
            'id' => $this->adsConfig->getVendorIdColumn(),
            'customer_id' => $this->adsConfig->getCustomerIdColumn(),
        ];
    }

    public function getCustomerIdByVendorId(int $vendorId): ?int
    {
        if ($vendorId <= 0) {
            return null;
        }

        try {
            $schema = $this->getSchema();
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName($schema['table']);

            $value = $connection->fetchOne(
                $connection->select()
                    ->from($table, [$schema['customer_id']])
                    ->where($schema['id'] . ' = ?', $vendorId)
                    ->limit(1)
            );

            return $value ? (int)$value : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
