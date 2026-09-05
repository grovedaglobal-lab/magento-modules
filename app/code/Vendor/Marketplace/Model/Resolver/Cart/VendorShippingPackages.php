<?php
declare(strict_types=1);

namespace Vendor\Marketplace\Model\Resolver\Cart;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as ProfileCollectionFactory;
use Vendor\Marketplace\Model\ResourceModel\ShippingRate\CollectionFactory as RateCollectionFactory;

class VendorShippingPackages implements ResolverInterface
{
    /**
     * @var ProfileCollectionFactory
     */
    protected $profileCollectionFactory;

    /**
     * @var RateCollectionFactory
     */
    protected $rateCollectionFactory;

    /**
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param RateCollectionFactory $rateCollectionFactory
     */
    public function __construct(
        ProfileCollectionFactory $profileCollectionFactory,
        RateCollectionFactory $rateCollectionFactory
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->rateCollectionFactory = $rateCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($value['model']) || !($value['model'] instanceof Quote)) {
            return [];
        }

        /** @var Quote $quote */
        $quote = $value['model'];
        $items = $quote->getAllItems();
        if (empty($items)) {
            return [];
        }

        $shippingAddress = $quote->getShippingAddress();
        $destCountryId = $shippingAddress ? $shippingAddress->getCountryId() : 'IN';
        $destRegionId = $shippingAddress ? (int) $shippingAddress->getRegionId() : 0;
        $destPostcode = $shippingAddress ? (string) $shippingAddress->getPostcode() : '';

        // Group items by vendor
        $vendorGroups = [];
        foreach ($items as $item) {
            if ($item->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                continue;
            }

            $vendorId = $item->getVendorId();
            if (!$vendorId) {
                $product = $item->getProduct();
                if ($product) {
                    $vendorId = (int) $product->getData('vendor_id');
                }
            }
            if (!$vendorId) {
                $vendorId = 0;
            }

            if (!isset($vendorGroups[$vendorId])) {
                $vendorGroups[$vendorId] = [
                    'vendor_id' => $vendorId,
                    'item_uids' => [],
                    'subtotal' => 0.0,
                    'weight' => 0.0,
                ];
            }

            $qty = (float) $item->getQty();
            $weight = (float) $item->getWeight();
            $rowTotal = (float) $item->getRowTotal();

            $vendorGroups[$vendorId]['subtotal'] += $rowTotal;
            $vendorGroups[$vendorId]['weight'] += ($weight * $qty);
            $vendorGroups[$vendorId]['item_uids'][] = base64_encode((string) $item->getId());
        }

        $packages = [];
        foreach ($vendorGroups as $vendorId => $group) {
            if ($vendorId == 0) {
                continue;
            }

            $profile = $this->getVendorProfile($vendorId);
            $shopName = ($profile && $profile->getShopName()) ? $profile->getShopName() : ('Vendor #' . $vendorId);
            $freeThreshold = ($profile && $profile->getFreeShippingAmount()) ? (float) $profile->getFreeShippingAmount() : 0.0;
            $shippingSource = ($profile && $profile->getShippingSource()) ? $profile->getShippingSource() : 'self_ship';

            $subtotal = $group['subtotal'];
            $weight = $group['weight'];
            $shippingAmount = 0.0;
            $isFree = false;
            $neededForFree = 0.0;

            if ($freeThreshold > 0 && $subtotal >= $freeThreshold) {
                $isFree = true;
                $shippingAmount = 0.0;
                $neededForFree = 0.0;
            } else {
                if ($freeThreshold > 0) {
                    $neededForFree = max(0.0, $freeThreshold - $subtotal);
                }

                if ($shippingSource == 'self_ship') {
                    $rate = $this->getVendorRate($vendorId, (string) $destCountryId, (int) $destRegionId, $destPostcode, (float) $weight);
                    if ($rate && $rate->getPrice() !== null) {
                        $shippingAmount = (float) $rate->getPrice();
                    } else {
                        $shippingAmount = 0.0;
                    }
                }
            }

            if ($shippingAmount <= 0.0) {
                $isFree = true;
            }

            $packages[] = [
                'vendor_id' => (int) $vendorId,
                'vendor_name' => (string) $shopName,
                'item_uids' => $group['item_uids'],
                'subtotal' => (float) round($subtotal, 2),
                'shipping_amount' => (float) round($shippingAmount, 2),
                'is_free_shipping' => (bool) $isFree,
                'free_shipping_threshold' => (float) round($freeThreshold, 2),
                'amount_needed_for_free_shipping' => (float) round($neededForFree, 2),
            ];
        }

        return $packages;
    }

    /**
     * @param int $vendorId
     * @return \Magento\Framework\DataObject|bool
     */
    protected function getVendorProfile($vendorId)
    {
        $collection = $this->profileCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        return $collection->getFirstItem();
    }

    /**
     * @param int $vendorId
     * @param string $countryId
     * @param int $regionId
     * @param string $zip
     * @param float $weight
     * @return \Magento\Framework\DataObject|bool
     */
    protected function getVendorRate($vendorId, $countryId, $regionId, $zip, $weight)
    {
        $collection = $this->rateCollectionFactory->create();
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
            if ($rate->getRegionId() == $regionId && $regionId != 0) {
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

        return $bestRate;
    }
}