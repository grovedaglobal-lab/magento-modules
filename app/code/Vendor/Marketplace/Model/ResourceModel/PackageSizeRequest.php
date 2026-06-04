<?php
/**
 * Package Size Request Resource Model
 */
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PackageSizeRequest extends AbstractDb
{
    /**
     * {@inheritdoc}
     */
    protected function _construct()
    {
        $this->_init('vendor_package_size_request', 'entity_id');
    }

    /**
     * Get requests by vendor
     *
     * @param int $vendorId
     * @param string $status
     * @return array
     */
    public function getByVendor($vendorId, $status = null)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('vendor_id = ?', $vendorId)
            ->order('created_at DESC');

        if ($status) {
            $select->where('status = ?', $status);
        }

        return $connection->fetchAll($select);
    }

    /**
     * Check if vendor already requested this size
     *
     * @param int $vendorId
     * @param string $packageSize
     * @return bool
     */
    public function requestExists($vendorId, $packageSize)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'entity_id')
            ->where('vendor_id = ?', $vendorId)
            ->where('package_size = ?', $packageSize)
            ->where('status IN (?, ?)', [\Vendor\Marketplace\Model\PackageSizeRequest::STATUS_PENDING, \Vendor\Marketplace\Model\PackageSizeRequest::STATUS_APPROVED]);

        return (bool) $connection->fetchOne($select);
    }
}
