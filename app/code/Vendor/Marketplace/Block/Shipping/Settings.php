<?php
namespace Vendor\Marketplace\Block\Shipping;

use Magento\Framework\View\Element\Template;
use Vendor\Marketplace\Model\Session\VendorSession;
use Vendor\Marketplace\Model\ResourceModel\VendorProfile\CollectionFactory as ProfileCollectionFactory;
use Vendor\Marketplace\Model\Config\Source\ShippingSource;
use Vendor\Marketplace\Model\Config\Source\TaxClass;

class Settings extends Template
{
    /**
     * @var VendorSession
     */
    protected $_vendorSession;

    /**
     * @var ProfileCollectionFactory
     */
    protected $_profileCollectionFactory;

    /**
     * @var ShippingSource
     */
    protected $_shippingSource;

    /**
     * @var TaxClass
     */
    protected $_taxClassSource;

    /**
     * @param Template\Context $context
     * @param VendorSession $vendorSession
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ShippingSource $shippingSource
     * @param TaxClass $taxClassSource
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        VendorSession $vendorSession,
        ProfileCollectionFactory $profileCollectionFactory,
        ShippingSource $shippingSource,
        TaxClass $taxClassSource,
        array $data = []
    ) {
        $this->_vendorSession = $vendorSession;
        $this->_profileCollectionFactory = $profileCollectionFactory;
        $this->_shippingSource = $shippingSource;
        $this->_taxClassSource = $taxClassSource;
        parent::__construct($context, $data);
    }

    /**
     * @return \Vendor\Marketplace\Model\VendorProfile
     */
    public function getVendorProfile()
    {
        $vendorId = $this->_vendorSession->getVendorId();
        $collection = $this->_profileCollectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId);
        return $collection->getFirstItem();
    }

    /**
     * @return array
     */
    /**
     * @return array
     */
    public function getShippingSourceOptions()
    {
        // 1. Check Global Configuration
        $isEasyShipAllowed = $this->_scopeConfig->isSetFlag(
            'vendor_marketplace/vendorshipping/allow_easy_ship',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $profile = $this->getVendorProfile();
        $allowed = $profile->getAllowedShippingSources() ?: 'both';
        $options = $this->_shippingSource->toOptionArray();

        // 2. Filter Global Config (Remove Easy Ship if disabled globally)
        if (!$isEasyShipAllowed) {
            $options = array_filter($options, function ($opt) {
                return $opt['value'] !== 'easy_ship';
            });
            // If profile was strictly 'easy', we must handle that? 
            // In theory, if global is disabled, 'easy' shouldn't be selectable. 
            // But if previously saved, we might have an issue. 
            // For now, removing it from options forces user to select 'self_ship' on next save.
        }

        // 3. Filter Vendor Specific Config
        if ($allowed == 'self') {
            return array_filter($options, function ($opt) {
                return $opt['value'] == 'self_ship';
            });
        } elseif ($allowed == 'easy') {
            return array_filter($options, function ($opt) {
                return $opt['value'] == 'easy_ship';
            });
        } elseif ($allowed == 'none') {
            return [];
        }

        return $options;
    }

    /**
     * @return array
     */
    public function getTaxClassOptions()
    {
        return $this->_taxClassSource->toOptionArray();
    }

    /**
     * @return \Vendor\Marketplace\Model\ResourceModel\ShippingRate\Collection
     */
    public function getShippingRates()
    {
        // Use object manager for quick prototype, ideally inject factory
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $collectionFactory = $objectManager->create('Vendor\Marketplace\Model\ResourceModel\ShippingRate\CollectionFactory');
        $collection = $collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $this->_vendorSession->getVendorId());
        return $collection;
    }

    /**
     * @return array
     */
    public function getCountryOptions()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $countryCollection = $objectManager->create('Magento\Directory\Model\ResourceModel\Country\CollectionFactory')->create();
        return $countryCollection->toOptionArray();
    }

    /**
     * @return string
     */
    public function getSaveActionUrl()
    {
        return $this->getUrl('marketplace/shipping/save');
    }

    /**
     * @return array
     */
    public function getIndiaRegions()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $regionCollection = $objectManager->create('Magento\Directory\Model\ResourceModel\Region\CollectionFactory')->create();
        $regionCollection->addCountryFilter('IN');
        $regions = [];
        foreach ($regionCollection as $region) {
            $regions[] = [
                'id' => $region->getId(),
                'code' => $region->getCode(),
                'name' => $region->getName()
            ];
        }
        return $regions;
    }
}
