<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tax\IndianGST\Model\ResourceModel\VendorProfile\CollectionFactory as VendorProfileCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RequestInterface;
use Tax\IndianGST\Helper\Data as HelperData;

class VendorList implements OptionSourceInterface
{
    /**
     * @var VendorProfileCollectionFactory
     */
    protected $gstProfileCollectionFactory;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HelperData
     */
    protected $helper;

    /**
     * @param VendorProfileCollectionFactory $gstProfileCollectionFactory
     * @param ResourceConnection $resource
     * @param RequestInterface $request
     * @param HelperData $helper
     */
    public function __construct(
        VendorProfileCollectionFactory $gstProfileCollectionFactory,
        ResourceConnection $resource,
        RequestInterface $request,
        HelperData $helper
    ) {
        $this->gstProfileCollectionFactory = $gstProfileCollectionFactory;
        $this->resource = $resource;
        $this->request = $request;
        $this->helper = $helper;
    }

    /**
     * To Option Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        $vendorTable = $this->helper->getVendorTableName();
        $idCol = $this->helper->getVendorIdColumn();
        $codeCol = $this->helper->getVendorCodeColumn();

        if (!$vendorTable || !$idCol || !$codeCol) {
            // Fallback just in case config is missing
            return [['value' => '', 'label' => __('-- Configuration Missing --')]];
        }

        // Get logic is a bit more manual now since we can't rely on a specific Collection class structure
        // We use direct SQL for maximum compatibility with any table structure
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName($vendorTable);

        // Check if table exists
        if (!$connection->isTableExists($tableName)) {
            return [['value' => '', 'label' => __('-- Vendor Table Not Found --')]];
        }

        // Get IDs of vendors who already have GST profiles
        $gstCollection = $this->gstProfileCollectionFactory->create();
        $existingVendorCodes = $gstCollection->getColumnValues('vendor_code');

        // Check if we are editing an existing profile
        $currentId = $this->request->getParam('entity_id');
        if ($currentId) {
            $currentProfile = $gstCollection->getItemById($currentId);
            if ($currentProfile) {
                // Remove the current vendor's code from the exclusion list so it shows up
                $currentVendorCode = $currentProfile->getVendorCode();
                $existingVendorCodes = array_diff($existingVendorCodes, [$currentVendorCode]);
            }
        }

        // Select vendors from marketplace table
        $select = $connection->select()
            ->from($tableName, ['value' => $idCol, 'label' => $codeCol]);

        if (!empty($existingVendorCodes)) {
            $select->where($idCol . ' NOT IN (?)', $existingVendorCodes);
        }

        $results = $connection->fetchAll($select);

        if (empty($results)) {
            return [['value' => '', 'label' => __('No vendors available. Please create vendors in the marketplace first.')]];
        }

        $options[] = ['value' => '', 'label' => __('-- Select Vendor --')];
        foreach ($results as $row) {
            $options[] = [
                'value' => $row['value'],
                'label' => $row['label']
            ];
        }

        return $options;
    }
}
