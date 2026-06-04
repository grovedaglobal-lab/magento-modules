<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Service;

use Tax\IndianGST\Model\ResourceModel\VendorProfile as VendorProfileResource;
use Tax\IndianGST\Model\VendorCommissionGstFactory;
use Tax\IndianGST\Model\ResourceModel\VendorCommissionGst as VendorCommissionGstResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CommissionTaxService
{
    const SAC_CODE = '9983';

    /**
     * @var VendorProfileResource
     */
    protected $vendorProfileResource;

    /**
     * @var VendorCommissionGstFactory
     */
    protected $commissionGstFactory;

    /**
     * @var VendorCommissionGstResource
     */
    protected $commissionGstResource;

    /**
     * @var \Vendor\Marketplace\Model\ResourceModel\VendorOrder
     */
    protected $vendorOrderResource;

    /**
     * @var \Vendor\Marketplace\Model\VendorOrderFactory
     */
    protected $vendorOrderFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        VendorProfileResource $vendorProfileResource,
        VendorCommissionGstFactory $commissionGstFactory,
        VendorCommissionGstResource $commissionGstResource,
        ScopeConfigInterface $scopeConfig,
        \Vendor\Marketplace\Model\ResourceModel\VendorOrder $vendorOrderResource,
        \Vendor\Marketplace\Model\VendorOrderFactory $vendorOrderFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->vendorProfileResource = $vendorProfileResource;
        $this->commissionGstFactory = $commissionGstFactory;
        $this->commissionGstResource = $commissionGstResource;
        $this->scopeConfig = $scopeConfig;
        $this->vendorOrderResource = $vendorOrderResource;
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->logger = $logger;
    }

    /**
     * Calculate and Save GST on Commission
     *
     * @param \Vendor\Marketplace\Model\VendorOrder $vendorOrder
     * @param float $commissionAmount
     * @return void
     */
    public function calculateAndSave($vendorOrder, float $commissionAmount)
    {
        $vendorOrderEntityId = $vendorOrder->getEntityId();
        $vendorId = $vendorOrder->getVendorId();
        $this->logger->info("GST Commission: Starting calculation for Vendor Order #$vendorOrderEntityId, Vendor ID #$vendorId, Amount: $commissionAmount");

        if ($commissionAmount <= 0) {
            $this->logger->info("GST Commission: Amount is zero or less. Skipping.");
            return;
        }

        // Check if GST on commission is enabled
        $isEnabled = $this->scopeConfig->isSetFlag(
            'tax_indiangst/marketplace_integration/apply_gst_on_commission',
            ScopeInterface::SCOPE_STORE
        );

        if (!$isEnabled) {
            $this->logger->info("GST Commission: GST calculation is disabled in settings.");
            return;
        }

        // 1. Get Vendor State Code/ID
        // In the resource model, it fetches region_id then converts to Code.
        $vendorState = $this->vendorProfileResource->getVendorStateCode((int) $vendorId);

        // 2. Get Admin State Code (Marketplace Origin)
        $adminStateId = $this->scopeConfig->getValue(
            'shipping/origin/region_id',
            ScopeInterface::SCOPE_STORE
        );

        // Normalize Comparison: We need to compare Codes correctly.
        $adminStateCode = '';
        if ($adminStateId) {
            try {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $region = $objectManager->create(\Magento\Directory\Model\Region::class)->load($adminStateId);
                $adminStateCode = $region->getCode();
            } catch (\Exception $e) {
                $this->logger->error("GST Commission: Error loading admin state: " . $e->getMessage());
            }
        }

        $this->logger->info("GST Commission: Comparing States - Vendor: $vendorState, Admin: $adminStateCode (ID: $adminStateId)");

        // 3. Determine Tax Type
        $isInterState = (empty($vendorState) || empty($adminStateCode) || $vendorState !== $adminStateCode);

        $this->logger->info("GST Commission: Is Inter-State? " . ($isInterState ? 'YES' : 'NO'));

        // 4. Get GST Rate and SAC Code from Config
        $gstRate = (float) $this->scopeConfig->getValue(
            'tax_indiangst/marketplace_integration/commission_gst_rate',
            ScopeInterface::SCOPE_STORE
        ) ?: 18.00;

        $sacCode = $this->scopeConfig->getValue(
            'tax_indiangst/marketplace_integration/commission_sac_code',
            ScopeInterface::SCOPE_STORE
        ) ?: '9983';

        // 5. Calculate
        $taxAmount = ($commissionAmount * $gstRate) / 100;

        $cgst = 0;
        $sgst = 0;
        $igst = 0;

        if ($isInterState) {
            $igst = $taxAmount;
        } else {
            $cgst = $taxAmount / 2;
            $sgst = $taxAmount / 2;
        }

        $this->logger->info("GST Commission: Tax Calculated - Total: $taxAmount, CGST: $cgst, SGST: $sgst, IGST: $igst");

        // 6. Save GST Record
        try {
            $model = $this->commissionGstFactory->create();
            $model->setData([
                'order_id' => $vendorOrderEntityId,
                'vendor_id' => $vendorId,
                'commission_amount' => $commissionAmount,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_tax' => $taxAmount,
                'sac_code' => $sacCode
            ]);

            $this->commissionGstResource->save($model);
            $this->logger->info("GST Commission: Saved GST record successfully.");
        } catch (\Exception $e) {
            $this->logger->error("GST Commission: Error saving GST record: " . $e->getMessage());
        }

        // 7. Update Vendor Order in Marketplace module
        try {
            if ($vendorOrder->getId()) {
                $vendorOrder->setData('commission_tax_amount', $taxAmount);
                $vendorOrder->setData('commission_cgst_amount', $cgst);
                $vendorOrder->setData('commission_sgst_amount', $sgst);
                $vendorOrder->setData('commission_igst_amount', $igst);
                $vendorOrder->setData('commission_sac_code', $sacCode);

                // GST is exclusive: subtract it from vendor earnings
                $currentEarnings = (float) $vendorOrder->getVendorEarnings();
                $vendorOrder->setVendorEarnings($currentEarnings - $taxAmount);

                $this->vendorOrderResource->save($vendorOrder);
                $this->logger->info("GST Commission: Updated Marketplace Vendor Order #$vendorOrderEntityId with tax details.");
            }
        } catch (\Exception $e) {
            $this->logger->error("GST Commission: Error updating Vendor Order: " . $e->getMessage());
        }
    }
}
