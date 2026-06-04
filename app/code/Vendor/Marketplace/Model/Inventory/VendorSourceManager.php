<?php
namespace Vendor\Marketplace\Model\Inventory;

use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\StockSourceLinksSaveInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Api\Data\VendorProfileInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class VendorSourceManager
{
    protected $sourceRepository;
    protected $sourceFactory;
    protected $sourceItemsSave;
    protected $sourceItemFactory;
    protected $stockSourceLinksSave;
    protected $getStockSourceLinks;
    protected $stockSourceLinkFactory;
    protected $stockResolver;
    protected $storeManager;
    protected $scopeConfig;
    protected $countryCollectionFactory;
    protected $regionCollectionFactory;
    protected $logger;

    public function __construct(
        SourceRepositoryInterface $sourceRepository,
        SourceInterfaceFactory $sourceFactory,
        SourceItemsSaveInterface $sourceItemsSave,
        SourceItemInterfaceFactory $sourceItemFactory,
        StockSourceLinksSaveInterface $stockSourceLinksSave,
        GetStockSourceLinksInterface $getStockSourceLinks,
        StockSourceLinkInterfaceFactory $stockSourceLinkFactory,
        StockResolverInterface $stockResolver,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CountryCollectionFactory $countryCollectionFactory,
        RegionCollectionFactory $regionCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->sourceRepository = $sourceRepository;
        $this->sourceFactory = $sourceFactory;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->stockSourceLinksSave = $stockSourceLinksSave;
        $this->getStockSourceLinks = $getStockSourceLinks;
        $this->stockSourceLinkFactory = $stockSourceLinkFactory;
        $this->stockResolver = $stockResolver;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Get standardized source code for a vendor
     * 
     * @param int $vendorId
     * @return string
     */
    public function getSourceCode($vendorId)
    {
        return 'vendor_' . $vendorId;
    }

    /**
     * Create or Update Source for Vendor
     * 
     * @param VendorProfileInterface $profile
     * @return SourceInterface
     */
    public function processVendorSource(VendorProfileInterface $profile)
    {
        $sourceCode = $this->getSourceCode($profile->getVendorId());

        try {
            $source = $this->sourceRepository->get($sourceCode);
        } catch (NoSuchEntityException $e) {
            $source = $this->sourceFactory->create();
            $source->setSourceCode($sourceCode);
            $source->setName($profile->getShopName() . ' Source');
            $source->setEnabled(true);
        }

        // Map Profile Address to Source Address
        // Note: SourceInterface uses specific setters, likely standard address fields
        $source->setName($profile->getShopName() . ' (' . $sourceCode . ')');

        // Handling address. Assuming your pickup address fields are text/varchar.
        // We need to parse or map them.

        // Get Field Mappings from Config
        $addrField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/address_field') ?: 'pickup_address';
        $cityField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/city_field') ?: 'pickup_city';
        $countryField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/country_field') ?: 'pickup_country';
        $postcodeField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/postcode_field') ?: 'pickup_zip_code';
        $regionField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/region_field') ?: 'pickup_state';
        $phoneField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/phone_field') ?: 'pickup_phone';
        $emailField = $this->scopeConfig->getValue('vendor_marketplace/source_mapping/email_field') ?: 'pickup_email';

        // Process Country
        $rawCountry = $profile->getData($countryField);
        $this->logger->info("VendorSourceManager: Processing Source for Vendor " . $profile->getVendorId());
        $this->logger->info("VendorSourceManager: Raw Country: " . var_export($rawCountry, true));

        $countryId = 'US'; // Default
        if ($rawCountry) {
            if (strlen($rawCountry) == 2) {
                $countryId = strtoupper($rawCountry);
            } else {
                // Try to find by name
                $countryId = $this->getCountryIdByName($rawCountry) ?: 'US';
            }
        }
        $this->logger->info("VendorSourceManager: Resolved Country ID: " . $countryId);

        $source->setCountryId($countryId);

        // Process Region
        $rawRegion = $profile->getData($regionField);
        $this->logger->info("VendorSourceManager: Raw Region: " . var_export($rawRegion, true));
        $regionId = null;
        if ($rawRegion) {
            $regionId = $this->getRegionIdByName($countryId, $rawRegion);
        }
        $this->logger->info("VendorSourceManager: Resolved Region ID: " . var_export($regionId, true));

        if ($regionId) {
            $source->setRegionId($regionId);
            $source->setRegion(null); // Clear string region if ID is present
        } else {
            $source->setRegion($rawRegion ?: '');
            $source->setRegionId(null);
        }

        $source->setPostcode($profile->getData($postcodeField) ?: '00000');
        $source->setCity($profile->getData($cityField) ?: 'Unknown');
        $source->setStreet($profile->getData($addrField) ?: 'Unknown Street');
        $source->setPhone($profile->getData($phoneField) ?: '');
        $source->setEmail($profile->getData($emailField) ?: '');

        // Required fields for Source
        if (!$source->getDescription()) {
            $source->setDescription('Auto-created source for Vendor ' . $profile->getVendorId());
        }

        $this->sourceRepository->save($source);

        // Link Source to Stock
        $this->linkSourceToStock($sourceCode);

        return $source;
    }

    /**
     * Link Source to the Website's Stock
     * 
     * @param string $sourceCode
     */
    protected function linkSourceToStock($sourceCode)
    {
        try {
            // Get Current Website
            $website = $this->storeManager->getWebsite();
            $this->logger->info("VSM: Linking source $sourceCode for Website: " . $website->getCode());
            // Get Stock assigned to this Website
            $stock = $this->stockResolver->execute(
                \Magento\InventorySalesApi\Api\Data\SalesChannelInterface::TYPE_WEBSITE,
                $website->getCode()
            );
            $this->logger->info("VSM: Resolved Stock ID: " . $stock->getStockId());

            if ($stock->getStockId() === 1) {
                $this->logger->critical(
                    "VSM: Cannot link vendor source to Default Stock (ID 1). " .
                    "Please create a new stock, assign the website to it, and ensure all vendor sources are assigned to the new stock."
                );
                return; // Stop execution
            }

            $searchCriteria = \Magento\Framework\App\ObjectManager::getInstance()
                ->create(\Magento\Framework\Api\SearchCriteriaBuilder::class)
                ->addFilter('stock_id', $stock->getStockId())
                ->addFilter('source_code', $sourceCode)
                ->create();

            $links = $this->getStockSourceLinks->execute($searchCriteria);

            if ($links->getTotalCount() == 0) {
                $this->logger->info("VSM: Creating new link.");
                // Link it
                $link = $this->stockSourceLinkFactory->create();
                $link->setStockId($stock->getStockId());
                $link->setSourceCode($sourceCode);
                $link->setPriority(10); // Standard priority
                $this->stockSourceLinksSave->execute([$link]);
            }

        } catch (\Exception $e) {
            $this->logger->error("Failed to link vendor source $sourceCode to stock: " . $e->getMessage());
        }
    }

    /**
     * Assign product to vendor source
     * 
     * @param string $sku
     * @param int $vendorId
     * @param float $qty
     * @return void
     */
    public function assignProductToVendorSource($sku, $vendorId, $qty = 0.0)
    {
        $sourceCode = $this->getSourceCode($vendorId);
        $this->logger->info("Assigning product $sku to source $sourceCode with qty $qty");

        // Check if source exists
        try {
            $this->sourceRepository->get($sourceCode);
        } catch (NoSuchEntityException $e) {
            // If source doesn't exist, we can't assign. 
            // This might happen if Vendor hasn't filled profile yet.
            $this->logger->warning("Attempted to assign product $sku to non-existent source $sourceCode");
            return;
        }

        // Create Source Item
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSku($sku);
        $sourceItem->setSourceCode($sourceCode);
        $sourceItem->setQuantity($qty);
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK); // Default to In Stock if qty > 0? Or just enabled.

        try {
            $this->sourceItemsSave->execute([$sourceItem]);
            $this->logger->info("Successfully saved source item for $sku in $sourceCode");
        } catch (\Exception $e) {
            $this->logger->error("Failed to save source item for $sku in $sourceCode: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Look up Country ID by Name
     * @param string $name
     * @return string|null
     */
    protected function getCountryIdByName($name)
    {
        $collection = $this->countryCollectionFactory->create();
        /** @var \Magento\Directory\Model\Country $country */
        foreach ($collection as $country) {
            if (strcasecmp((string) $country->getName(), $name) === 0) {
                return $country->getId();
            }
        }
        return null;
    }

    /**
     * Look up Region ID by Name/Code and Country
     * @param string $countryId
     * @param string $regionName
     * @return int|null
     */
    protected function getRegionIdByName($countryId, $regionName)
    {
        $collection = $this->regionCollectionFactory->create();
        $collection->addCountryFilter($countryId);

        /** @var \Magento\Directory\Model\Region $region */
        foreach ($collection as $region) {
            if (
                strcasecmp((string) $region->getName(), $regionName) === 0 ||
                strcasecmp((string) $region->getCode(), $regionName) === 0 ||
                strcasecmp((string) $region->getDefaultName(), $regionName) === 0
            ) {
                return $region->getId();
            }
        }
        return null;
    }
}
