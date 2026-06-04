<?php
namespace Vendor\Marketplace\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Vendor\Marketplace\Model\ResourceModel\ShippingRate\CollectionFactory as RateCollectionFactory;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as ProfileCollectionFactory;

class VendorShipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'vendorshipping';

    /**
     * @var ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var RateCollectionFactory
     */
    protected $_rateCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    protected $_profileCollectionFactory;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $_eavConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $errorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param RateCollectionFactory $rateCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $errorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        RateCollectionFactory $rateCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Model\Config $eavConfig,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_rateCollectionFactory = $rateCollectionFactory;
        $this->_profileCollectionFactory = $profileCollectionFactory;
        $this->_storeManager = $storeManager;
        $this->_eavConfig = $eavConfig;

        parent::__construct($scopeConfig, $errorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name') ?: 'Shipping rate'];
    }

    /**
     * @param string $field
     * @return string|null
     */
    public function getConfigData($field)
    {
        if (empty($this->_code)) {
            return null;
        }

        $storeId = $this->getStore();

        $path = 'vendor_marketplace/' . $this->_code . '/' . $field;
        $value = $this->_scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value !== null) {
            return $value;
        }

        return parent::getConfigData($field);
    }

    /**
     * @param string $field
     * @return bool
     */
    public function getConfigFlag($field)
    {
        $path = 'vendor_marketplace/' . $this->_code . '/' . $field;
        return $this->_scopeConfig->isSetFlag(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * Get Store ID
     * @return int
     */
    protected function getStore()
    {
        try {
            return $this->_storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getConfigFlag('active');
    }

    /**
     * @param \Magento\Framework\DataObject $request
     * @return bool
     */
    public function processAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        return true;
    }

    /**
     * Collect rates
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->isActive()) {
            return false;
        }

        $result = $this->_rateResultFactory->create();
        $items = $request->getAllItems();

        if (empty($items)) {
            return false;
        }

        $vendorGroups = [];
        foreach ($items as $item) {
            if ($item->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                continue;
            }

            // Get Vendor ID from extension attributes or product data
            $vendorId = $item->getVendorId();

            if (!$vendorId) {
                $product = $item->getProduct();
                if ($product) {
                    $vendorId = $product->getData('vendor_id');
                }
            }

            if (!$vendorId) {
                $vendorId = 0; // Default or Admin
            }

            if (!isset($vendorGroups[$vendorId])) {
                $vendorGroups[$vendorId] = [
                    'weight' => 0,
                    'subtotal' => 0,
                    'items' => []
                ];
            }

            $qty = $item->getQty();
            $weight = $item->getWeight();
            $rowTotal = $item->getRowTotal();

            $vendorGroups[$vendorId]['weight'] += ($weight * $qty);
            $vendorGroups[$vendorId]['subtotal'] += $rowTotal;
            $vendorGroups[$vendorId]['items'][] = $item;
        }

        $totalShippingCost = 0;

        // Process each vendor group
        foreach ($vendorGroups as $vendorId => $data) {
            if ($vendorId == 0) {
                continue;
            }

            $profile = $this->getVendorProfile($vendorId);
            if (!$profile || !$profile->getId()) {
                continue;
            }

            $shippingSource = $profile->getShippingSource() ?: 'self_ship';
            if ($shippingSource == 'easy_ship') {
                continue;
            }

            if ($shippingSource == 'self_ship') {
                $freeShippingThreshold = $profile->getFreeShippingAmount();

                if ($freeShippingThreshold > 0 && $data['subtotal'] >= $freeShippingThreshold) {
                    continue;
                }

                $rate = $this->getVendorRate(
                    $vendorId,
                    $request->getDestCountryId(),
                    $request->getDestRegionId(),
                    $request->getDestPostcode(),
                    $data['weight']
                );

                if ($rate && $rate->getPrice() !== null) {
                    $totalShippingCost += (float) $rate->getPrice();
                } else {
                    // If any vendor in the cart doesn't have a valid rate, we can't ship the whole cart
                    return false;
                }
            }
        }

        $method = $this->_rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title') ?: 'Vendor Shipping');
        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name') ?: 'Shipping rate');
        $method->setPrice($totalShippingCost);
        $method->setCost($totalShippingCost);

        $result->append($method);

        return $result;
    }

    /**
     * @param int $vendorId
     * @return \Magento\Framework\DataObject|bool
     */
    protected function getVendorProfile($vendorId)
    {
        $collection = $this->_profileCollectionFactory->create();
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
        $collection = $this->_rateCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('country_id', $countryId);

        // Filter by Weight Range
        $collection->addFieldToFilter('weight_from', ['lteq' => $weight]);
        $collection->addFieldToFilter('weight_to', ['gteq' => $weight]);

        // Prioritize Region: Specific Region > Region 0 (All)
        // Manual collection filtering for OR conditions
        $collection->getSelect()->where(
            '(region_id = ? OR region_id = 0 OR region_id IS NULL)',
            $regionId
        );

        // Handle Zip Code Logic (Exact Request Zip OR Wildcard '*' OR NULL OR Empty if request is empty)
        $collection->getSelect()->where(
            '(zip_code = ? OR zip_code = "*" OR zip_code IS NULL OR zip_code = "")',
            $zip
        );

        $rates = $collection->getItems();
        $bestRate = null;
        $bestScore = -1;

        foreach ($rates as $rate) {
            $score = 0;
            // Region match: 10 points
            if ($rate->getRegionId() == $regionId && $regionId != 0) {
                $score += 10;
            }
            // Zip match: 5 points
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
