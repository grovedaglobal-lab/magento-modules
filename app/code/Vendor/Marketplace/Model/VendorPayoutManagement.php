<?php
namespace Vendor\Marketplace\Model;

use Vendor\Marketplace\Api\VendorPayoutManagementInterface;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder\CollectionFactory as VendorOrderCollectionFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorPayout\CollectionFactory as VendorPayoutCollectionFactory;
use Vendor\Marketplace\Model\VendorPayoutFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorPayout as ResourceVendorPayout;
use Magento\Authorization\Model\UserContextInterface;
use Vendor\Marketplace\Model\VendorRepository;

class VendorPayoutManagement implements VendorPayoutManagementInterface
{
    protected $orderCollectionFactory;
    protected $payoutCollectionFactory;
    protected $payoutFactory;
    protected $resourcePayout;
    protected $userContext;
    protected $vendorRepository;

    public function __construct(
        VendorOrderCollectionFactory $orderCollectionFactory,
        VendorPayoutCollectionFactory $payoutCollectionFactory,
        VendorPayoutFactory $payoutFactory,
        ResourceVendorPayout $resourcePayout,
        UserContextInterface $userContext,
        VendorRepository $vendorRepository
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->payoutCollectionFactory = $payoutCollectionFactory;
        $this->payoutFactory = $payoutFactory;
        $this->resourcePayout = $resourcePayout;
        $this->userContext = $userContext;
        $this->vendorRepository = $vendorRepository;
    }

    protected function getVendorId()
    {
        $customerId = $this->userContext->getUserId();
        // Assuming user is vendor
        $vendor = $this->vendorRepository->getByCustomerId($customerId);
        return $vendor->getEntityId();
    }

    public function getEarnings()
    {
        $vendorId = $this->getVendorId();

        // 1. Calculate Total Lifetime Earnings
        $orders = $this->orderCollectionFactory->create();
        $orders->addFieldToFilter('vendor_id', $vendorId);
        // We might want to filter by order status 'complete' only in production

        $totalEarned = 0;
        foreach ($orders as $order) {
            $totalEarned += $order->getVendorEarnings();
        }

        // 2. Calculate Total Paid Out (or Pending Requests)
        $payouts = $this->payoutCollectionFactory->create();
        $payouts->addFieldToFilter('vendor_id', $vendorId);
        $payouts->addFieldToFilter('status', ['neq' => 'rejected']); // Count all non-rejected as liability

        $totalPaid = 0;
        foreach ($payouts as $payout) {
            $totalPaid += $payout->getAmount();
        }

        return $totalEarned - $totalPaid;
    }

    public function requestPayout($amount)
    {
        $vendorId = $this->getVendorId();
        $balance = $this->getEarnings();

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount.'));
        }

        if ($amount > $balance) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Insufficient balance.'));
        }

        $payout = $this->payoutFactory->create();
        $payout->setVendorId($vendorId);
        $payout->setAmount($amount);
        $payout->setStatus('pending');

        $this->resourcePayout->save($payout);

        return $payout;
    }
}
