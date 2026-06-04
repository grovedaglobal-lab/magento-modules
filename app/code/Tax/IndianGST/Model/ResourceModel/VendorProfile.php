<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class VendorProfile extends AbstractDb
{
    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->regionFactory = $regionFactory;
    }

    protected function _construct()
    {
        $this->_init('tax_indiangst_vendor_profile', 'entity_id');
    }

    /**
     * Get Vendor State Code by Vendor ID (from tax profile)
     * 
     * @param int $vendorId
     * @return string|null
     */
    public function getVendorStateCode(int $vendorId): ?string
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['region_id'])
            ->where('vendor_code = ?', $vendorId); // Assuming vendor_code maps to vendor_id from other module
        // WARNING: Integration point. If vendor_id is INT in other module, we might need a different lookup.
        // My schema used 'vendor_code' as unique key, but let's assume we link by ID often.
        // Let's assume input $vendorId map directly if needed.

        // Actually better to select region_id directly
        $regionId = $connection->fetchOne($select);

        if ($regionId) {
            // Convert ID to Code
            $region = $this->regionFactory->create()->load($regionId);
            return $region->getCode();
        }

        return null;
    }
}
