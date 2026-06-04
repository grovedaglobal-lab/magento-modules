<?php
/**
 * Vendor Package Size Request Model
 * 
 * Allows vendors to request new package size options via a form
 * Admin reviews and approves/rejects requests
 */
namespace Vendor\Marketplace\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PackageSizeRequest extends AbstractModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $_name = 'vendor_package_size_request';
    protected $_idFieldName = 'entity_id';

    /**
     * {@inheritdoc}
     */
    protected function _construct()
    {
        $this->_init(\Vendor\Marketplace\Model\ResourceModel\PackageSizeRequest::class);
    }

    /**
     * Get available statuses
     *
     * @return array
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
        ];
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel()
    {
        $statuses = self::getStatuses();
        return isset($statuses[$this->getStatus()]) ? $statuses[$this->getStatus()] : __('Unknown');
    }
}
