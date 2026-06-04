<?php
declare(strict_types=1);

namespace Tax\IndianGST\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Tax\IndianGST\Model\Service\CommissionTaxService;

class CalculateCommissionGst implements ObserverInterface
{
    /**
     * @var CommissionTaxService
     */
    /**
     * @var \Tax\IndianGST\Model\Service\CommissionTaxService
     */
    protected $commissionTaxService;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        CommissionTaxService $commissionTaxService,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->commissionTaxService = $commissionTaxService;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        /** @var \Vendor\Marketplace\Model\VendorOrder $vendorOrder */
        $vendorOrder = $observer->getData('vendor_order');

        if (!$vendorOrder || !$vendorOrder->getEntityId()) {
            $this->logger->warning("GST Commission Observer: No valid vendor_order found in event data.");
            return;
        }

        $this->logger->info("GST Commission Observer: Triggered for Vendor Order #{$vendorOrder->getEntityId()}");

        try {
            $this->commissionTaxService->calculateAndSave(
                $vendorOrder,
                (float) $vendorOrder->getCommissionAmount()
            );
        } catch (\Exception $e) {
            $this->logger->critical("GST Commission Observer Error: " . $e->getMessage());
        }
    }
}
