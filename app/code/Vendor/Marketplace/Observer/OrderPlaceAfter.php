<?php
namespace Vendor\Marketplace\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\Marketplace\Model\VendorOrderFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorOrder as ResourceVendorOrder;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\Marketplace\Model\CommissionCalculator;
use Vendor\Marketplace\Model\VendorProfileFactory;
use Vendor\Marketplace\Model\ResourceModel\ShippingRate\CollectionFactory as ShippingRateCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class OrderPlaceAfter implements ObserverInterface
{
    protected $vendorOrderFactory;
    protected $resourceVendorOrder;
    protected $productRepository;
    protected $commissionCalculator;
    protected $vendorProfileFactory;
    protected $shippingRateCollectionFactory;
    protected $_eventManager;
    protected $scopeConfig;
    protected $logger;

    public function __construct(
        VendorOrderFactory $vendorOrderFactory,
        ResourceVendorOrder $resourceVendorOrder,
        ProductRepositoryInterface $productRepository,
        CommissionCalculator $commissionCalculator,
        VendorProfileFactory $vendorProfileFactory,
        ShippingRateCollectionFactory $shippingRateCollectionFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->vendorOrderFactory = $vendorOrderFactory;
        $this->resourceVendorOrder = $resourceVendorOrder;
        $this->productRepository = $productRepository;
        $this->commissionCalculator = $commissionCalculator;
        $this->vendorProfileFactory = $vendorProfileFactory;
        $this->shippingRateCollectionFactory = $shippingRateCollectionFactory;
        $this->_eventManager = $eventManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $orderId = $order->getEntityId();

            if (!$orderId || $order->getData('is_wallet_recharge')) {
                return;
            }

            $connection = $this->resourceVendorOrder->getConnection();
            $tableName = $this->resourceVendorOrder->getMainTable();

            $existingVendorOrder = $connection->fetchOne(
                $connection->select()
                    ->from($tableName, 'entity_id')
                    ->where('order_id = ?', $orderId)
            );

            if ($existingVendorOrder) {
                return;
            }

            $vendorData = [];

            foreach ($order->getAllItems() as $item) {
                if ($item->getParentItem()) {
                    continue;
                }
                $vendorId = null;
                $product = $item->getProduct();
                if ($product) {
                    $vendorId = $product->getData('vendor_id');
                }
                if (!$vendorId) {
                    try {
                        $loadedProduct = $this->productRepository->getById($item->getProductId());
                        $vendorId = $loadedProduct->getData('vendor_id');
                    } catch (\Exception $e) {
                    }
                }

                if ($vendorId) {
                    try {
                        $commInfo = $this->commissionCalculator->calculate($vendorId, $item);
                        $itemCommission = $commInfo['amount'];
                        $calcCategory = $commInfo['category'];
                        $calcPercent = $commInfo['percent'];
                    } catch (\Exception $e) {
                        $this->logger->error('Vendor Marketplace: Calculation Error: ' . $e->getMessage());
                        $itemCommission = 0;
                        $calcCategory = 'Default';
                        $calcPercent = 0;
                    }

                    $groupKey = $calcCategory . '_' . $calcPercent;

                    if (!isset($vendorData[$vendorId])) {
                        $vendorData[$vendorId] = [
                            'total_weight' => 0,
                            'total_subtotal_for_shipping' => 0,
                            'groups' => []
                        ];
                    }

                    if (!isset($vendorData[$vendorId]['groups'][$groupKey])) {
                        $vendorData[$vendorId]['groups'][$groupKey] = [
                            'subtotal' => 0,
                            'commission' => 0,
                            'discount' => 0,
                            'gross_payout' => 0,
                            'category' => $calcCategory,
                            'percent' => $calcPercent
                        ];
                    }

                    $itemStoreId = $order->getStoreId();
                    $basis = $this->scopeConfig->getValue(
                        'vendor_marketplace/commission/calculation_basis',
                        ScopeInterface::SCOPE_STORE,
                        $itemStoreId
                    );

                    $itemSubtotal = ($basis === 'include_tax') ? $item->getRowTotalInclTax() : $item->getRowTotal();
                    $vendorDiscount = (float) $item->getDiscountAmount();
                    $itemWeight = ((float) $item->getWeight()) * (float) $item->getQtyOrdered();

                    $vendorData[$vendorId]['groups'][$groupKey]['subtotal'] += $itemSubtotal;
                    $vendorData[$vendorId]['groups'][$groupKey]['commission'] += $itemCommission;
                    $vendorData[$vendorId]['groups'][$groupKey]['discount'] += $vendorDiscount;
                    $vendorData[$vendorId]['groups'][$groupKey]['gross_payout'] += ($item->getRowTotalInclTax() - $vendorDiscount);

                    $vendorData[$vendorId]['total_weight'] += $itemWeight;
                    $vendorData[$vendorId]['total_subtotal_for_shipping'] += $itemSubtotal;
                }
            }

            foreach ($vendorData as $vendorId => $vendorInfo) {
                $shippingAmount = $this->getVendorShippingAmount(
                    $order,
                    $vendorId,
                    $vendorInfo['total_subtotal_for_shipping'],
                    $vendorInfo['total_weight']
                );

                $shippingAssigned = false;

                foreach ($vendorInfo['groups'] as $groupData) {
                    $subtotal = $groupData['subtotal'];
                    $commission = $groupData['commission'];
                    $discount = $groupData['discount'];
                    $earnings = $groupData['gross_payout'] - $commission;

                    $vendorOrder = $this->vendorOrderFactory->create();
                    $vendorOrder->setOrderId($order->getEntityId());
                    $vendorOrder->setVendorId($vendorId);
                    $vendorOrder->setSubtotal($subtotal);

                    // Assign shipping only to the first group for this vendor
                    if (!$shippingAssigned) {
                        $vendorOrder->setData('shipping_amount', $shippingAmount);
                        $shippingAssigned = true;
                    } else {
                        $vendorOrder->setData('shipping_amount', 0);
                    }

                    $vendorOrder->setCommissionAmount($commission);
                    $vendorOrder->setDiscountAmount($discount);
                    $vendorOrder->setVendorEarnings($earnings);
                    $vendorOrder->setCategoryName($groupData['category']);
                    $vendorOrder->setCommissionPercentage($groupData['percent']);
                    $vendorOrder->setStatus('pending');

                    $this->resourceVendorOrder->save($vendorOrder);

                    $this->_eventManager->dispatch('vendor_marketplace_commission_calculated', [
                        'vendor_order' => $vendorOrder,
                        'order' => $order
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical('Vendor Marketplace Observer Error: ' . $e->getMessage());
        }
    }

    protected function getVendorShippingAmount($order, $vendorId, $subtotal, $weight)
    {
        $profile = $this->vendorProfileFactory->create()->load($vendorId, 'vendor_id');
        if (!$profile->getId()) {
            return 0.0;
        }

        $shippingSource = $profile->getShippingSource() ?: 'self_ship';
        if ($shippingSource !== 'self_ship') {
            return 0.0;
        }

        $freeShippingThreshold = (float) $profile->getFreeShippingAmount();
        if ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) {
            return 0.0;
        }

        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            return 0.0;
        }

        $rate = $this->getVendorShippingRate(
            $vendorId,
            (string) $shippingAddress->getCountryId(),
            (int) $shippingAddress->getRegionId(),
            (string) $shippingAddress->getPostcode(),
            (float) $weight
        );

        if (!$rate || $rate->getPrice() === null) {
            return 0.0;
        }

        return (float) $rate->getPrice();
    }

    protected function getVendorShippingRate($vendorId, $countryId, $regionId, $zip, $weight)
    {
        $collection = $this->shippingRateCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('country_id', $countryId);

        $collection->addFieldToFilter('weight_from', ['lteq' => $weight]);
        $collection->addFieldToFilter('weight_to', ['gteq' => $weight]);

        $collection->getSelect()->where(
            '(region_id = ? OR region_id = 0 OR region_id IS NULL)',
            $regionId
        );

        $collection->getSelect()->where(
            '(zip_code = ? OR zip_code = "*" OR zip_code IS NULL OR zip_code = "")',
            $zip
        );

        $rates = $collection->getItems();
        $bestRate = null;
        $bestScore = -1;

        foreach ($rates as $rate) {
            $score = 0;
            if ((int) $rate->getRegionId() == $regionId && $regionId != 0) {
                $score += 10;
            }
            if ($rate->getZipCode() == $zip && $zip != '*' && $zip != '') {
                $score += 5;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRate = $rate;
            }
        }

        if (!$bestRate) {
            return false;
        }

        return $bestRate;
    }
}
