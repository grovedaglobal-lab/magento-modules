<?php
namespace Tax\IndianGST\Controller\Adminhtml\Vendor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;
use Tax\IndianGST\Helper\Data as HelperData;

class GetVendorData extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var HelperData
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ResourceConnection $resource
     * @param HelperData $helper
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resource,
        HelperData $helper,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resource = $resource;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('vendor_id');
        $result = $this->resultJsonFactory->create();
        $data = [];

        $this->logger->info('GetVendorData: Request received for ID: ' . $id);

        if ($id) {
            $tableName = $this->helper->getVendorTableName();
            $idCol = $this->helper->getVendorIdColumn();

            // Get configured mapping columns
            $businessNameCol = $this->helper->getConfigValue('tax_indiangst/marketplace_integration/vendor_business_name_col');
            $gstinCol = $this->helper->getConfigValue('tax_indiangst/marketplace_integration/vendor_gstin_col');
            $panCol = $this->helper->getConfigValue('tax_indiangst/marketplace_integration/vendor_pan_col');
            $postcodeCol = $this->helper->getConfigValue('tax_indiangst/marketplace_integration/vendor_postcode_col');

            $this->logger->info("GetVendorData: Table: $tableName, ID Col: $idCol");

            if ($tableName && $idCol) {
                try {
                    $connection = $this->resource->getConnection();
                    $tbl = $this->resource->getTableName($tableName);

                    $select = $connection->select()
                        ->from($tbl)
                        ->where($idCol . ' = ?', $id);

                    $vendorData = $connection->fetchRow($select);

                    if ($vendorData) {
                        $this->logger->info('GetVendorData: Data found', $vendorData);

                        // Use helper for column mapping with fallback to known marketplace fields
                        $nameCol = $this->helper->getBusinessNameColumn() ?: 'shop_name';
                        $gstinCol = $this->helper->getGstinColumn() ?: 'tax_id';
                        $panCol = $this->helper->getPanColumn() ?: 'business_license';
                        $postcodeCol = $this->helper->getPostcodeColumn() ?: 'zip_code';

                        if (isset($vendorData[$nameCol])) {
                            $data['business_name'] = $vendorData[$nameCol];
                        }
                        if (isset($vendorData[$gstinCol])) {
                            $data['gstin'] = $vendorData[$gstinCol];
                        }
                        if (isset($vendorData[$panCol])) {
                            $data['pan_number'] = $vendorData[$panCol];
                        }
                        if (isset($vendorData[$postcodeCol])) {
                            $data['postcode'] = $vendorData[$postcodeCol];
                        }
                    } else {
                        $this->logger->warning('GetVendorData: No data found for ID');
                    }
                } catch (\Exception $e) {
                    $this->logger->error('GetVendorData: Error ' . $e->getMessage());
                }
            }
        }

        return $result->setData($data);
    }
}
