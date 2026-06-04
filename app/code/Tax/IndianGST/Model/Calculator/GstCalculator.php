<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Calculator;

use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Address;
use Magento\Catalog\Model\Product;
use Tax\IndianGST\Model\ResourceModel\VendorProfile as VendorProfileResource;
use Magento\Framework\Exception\NoSuchEntityException;

class GstCalculator
{
    /**
     * @var VendorProfileResource
     */
    protected $vendorProfileResource;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    // Hardcoded Admin State for now (Should be from Config)
    const ADMIN_STATE_CODE = 'DL'; // Delhi Default

    /**
     * @var \Tax\IndianGST\Helper\Data
     */
    protected $gstHelper;

    public function __construct(
        VendorProfileResource $vendorProfileResource,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Tax\IndianGST\Helper\Data $gstHelper
    ) {
        $this->vendorProfileResource = $vendorProfileResource;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
        $this->gstHelper = $gstHelper;
    }

    /**
     * Calculate GST for a Product (Catalog View)
     *
     * @param Product $product
     * @param float $price
     * @return float
     */
    public function calculateProductTax(Product $product, float $price): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        // Multi-Vendor Check
        if ($this->gstHelper->isMultiVendorEnabled()) {
            $vendorCode = $this->getVendorCodeForProduct($product);
            if ($vendorCode) {
                // Check if Vendor is Registered
                // We need to look up our local profile table `tax_indiangst_vendor_profile`
                // using the vendor code (matched to vendor ID from product)

                // However, we sync vendor data into our table.
                // let's assume getVendorCodeForProduct returns the ID that matches our `vendor_code` in profile.

                $connection = $this->vendorProfileResource->getConnection();
                $select = $connection->select()
                    ->from($this->vendorProfileResource->getMainTable(), ['is_registered'])
                    ->where('vendor_code = ?', $vendorCode);

                $isRegistered = $connection->fetchOne($select);

                // If specific record found and explicitly 0, then 0. 
                // If record not found, we might assume registered or not? Default is 0 in DB.
                // If $isRegistered is false (not found) or 0, then tax is 0?
                // But if record not found, maybe sync didn't happen.

                // If vendor exists but is_registered is 0 -> RETURN 0 Tax.
                if ($isRegistered !== false && (int) $isRegistered === 0) {
                    return 0.0;
                }
            }
        }

        // Determine GST Percent
        $rateAttr = 'gst_rate';
        $gstPercent = 0;

        $attr = $product->getResource()->getAttribute($rateAttr);
        if ($attr && $attr->usesSource()) {
            $optionId = $product->getData($rateAttr);
            $label = $attr->getSource()->getOptionText($optionId);
            $gstPercent = (float) ($label ?: $optionId);
        } else {
            $gstPercent = (float) $product->getData($rateAttr);
        }

        if ($gstPercent <= 0) {
            return 0.0;
        }

        // Formula: Tax = Price * (Rate / 100)
        // Assuming Price input is Exclusive of Tax
        return $price * ($gstPercent / 100);
    }

    /**
     * Get Vendor Code/ID for Product
     *
     * @param Product $product
     * @return string|int|null
     */
    public function getVendorCodeForProduct(Product $product)
    {
        $attrCode = $this->gstHelper->getProductVendorAttribute();
        if (!$attrCode) {
            return null;
        }
        return $product->getData($attrCode);
    }

    /**
     * Calculate GST for a Quote Item
     *
     * @param Item $item
     * @param Address $shippingAddress
     * @return array
     */
    public function calculateItemTax(Item $item, Address $shippingAddress): array
    {
        $product = $item->getProduct();
        $this->gstHelper->getLogger()->info("---------------------------------");
        $this->gstHelper->getLogger()->info("[GstCalculator] Calculating for " . $product->getSku());

        // 0. Basic Validation - Default to India if not set (for Indian store)
        $countryId = $shippingAddress->getCountryId();
        if (!$countryId) {
            $countryId = $this->scopeConfig->getValue('tax/defaults/country', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }

        $this->gstHelper->getLogger()->info("[GstCalculator] Country: " . $countryId);

        if ($countryId !== 'IN') {
            $this->gstHelper->getLogger()->info("[GstCalculator] Not India, skipping.");
            return []; // Not India, skip (Standard Magento Tax applies)
        }

        // Reload product to get custom attributes if needed (Optimisation: Check if loaded)
        $rateAttr = 'gst_rate'; // Default
        if (!$product->getData($rateAttr)) {
            $product = $this->productRepository->get($product->getSku());
        }

        $gstPercent = 0;

        // Fix: Attribute is select, so getData returns Option ID. We need the Label/Value.
        $attr = $product->getResource()->getAttribute($rateAttr);
        if ($attr && $attr->usesSource()) {
            $optionId = $product->getData($rateAttr);
            // If optionId is "18", it might mean "18%" value directly if not using source model?
            // But we used Table source.
            $label = $attr->getSource()->getOptionText($optionId);
            // If label is "18", we use that. If false (not found), fallback.
            $gstPercent = (float) ($label ?: $optionId);
            $this->gstHelper->getLogger()->info("[GstCalculator] Rate Source. OptionID: $optionId, Label: $label, Resolved: $gstPercent");
        } else {
            $gstPercent = (float) $product->getData($rateAttr);
            $this->gstHelper->getLogger()->info("[GstCalculator] Rate Direct: $gstPercent");
        }

        $hsnCode = $product->getData('hsn_code');

        if ($gstPercent <= 0) {
            $this->gstHelper->getLogger()->info("[GstCalculator] Zero Rate. Skipping.");
            return [
                'type' => 'ZERO',
                'total_tax' => 0,
                'rate' => 0
            ];
        }

        // 1. Determine Origin State
        $originStateCode = $this->getOriginState($item);

        // 2. Determine Destination State
        $destStateCode = $this->mapStateCode($shippingAddress->getRegionCode(), $shippingAddress->getRegionId());

        $this->gstHelper->getLogger()->info("[GstCalculator] Origin: $originStateCode, Dest: $destStateCode");

        // 3. Compare
        $isInterState = ($originStateCode !== $destStateCode);

        // 4. Calculate Tax Amount
        // Use Global config "tax/calculation/price_includes_tax" for calculation logic
        // This decouples calculation (Catalog) from display (Indian GST Setting)
        $isInclusive = $this->scopeConfig->isSetFlag(
            'tax/calculation/price_includes_tax',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $item->getStoreId()
        );

        $price = $item->getRowTotal(); // Subtotal for the row (This is usually the price from DB)

        $this->gstHelper->getLogger()->info("[GstCalculator] Row Total (Price): $price, Inclusive: " . ($isInclusive ? 'YES' : 'NO'));

        if ($isInclusive) {
            // Price is Inclusive.
            // Tax = Price - (Price / (1 + Rate))
            $taxAmount = $price - ($price / (1 + ($gstPercent / 100)));
        } else {
            // Price is Exclusive.
            // Tax = Price * Rate
            $taxAmount = ($price * $gstPercent) / 100;
        }

        $this->gstHelper->getLogger()->info("[GstCalculator] Calculated Tax: $taxAmount");

        $breakup = [
            'total_tax' => $taxAmount,
            'rate' => $gstPercent,
            'hsn' => $hsnCode,
            'origin' => $originStateCode,
            'dest' => $destStateCode,
            'is_inclusive' => $isInclusive // Pass back to collector
        ];

        if ($isInterState) {
            $breakup['type'] = 'IGST';
            $breakup['igst_amount'] = $taxAmount;
            $breakup['cgst_amount'] = 0;
            $breakup['sgst_amount'] = 0;
        } else {
            $breakup['type'] = 'CGST_SGST';
            $breakup['igst_amount'] = 0;
            $breakup['cgst_amount'] = $taxAmount / 2;
            $breakup['sgst_amount'] = $taxAmount / 2;
        }

        return $breakup;
    }

    /**
     * Calculate GST for Shipping Amount
     *
     * @param Address $address
     * @return array
     */
    public function calculateShippingTax(Address $address): array
    {
        $shippingAmount = $address->getShippingAmount();
        if ($shippingAmount <= 0) {
            return [];
        }

        // Default Shipping GST Rate (Standard 18% for Courier/Services)
        // TODO: Add config for this
        $gstPercent = 18.0;

        // Origin for Shipping is ALWAYS Admin Origin (unless dropship logic logic changes this)
        // For now, assume Shipping Service provided by Marketplace Admin
        $storeId = $address->getQuote()->getStoreId();
        $adminState = $this->gstHelper->getOriginState($storeId) ?: self::ADMIN_STATE_CODE;

        $destStateCode = $this->mapStateCode($address->getRegionCode(), $address->getRegionId());

        $isInterState = ($adminState !== $destStateCode);

        // Check if Shipping Amount is Inclusive or Exclusive
        // Usually Shipping is Exclusive in Magento Config unless specified
        // Logic: Checks 'tax/calculation/shipping_includes_tax'
        $isInclusive = $this->scopeConfig->isSetFlag(
            'tax/calculation/shipping_includes_tax',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($isInclusive) {
            $taxAmount = $shippingAmount - ($shippingAmount / (1 + ($gstPercent / 100)));
        } else {
            $taxAmount = ($shippingAmount * $gstPercent) / 100;
        }

        $breakup = [
            'total_tax' => $taxAmount,
            'rate' => $gstPercent,
            'is_inclusive' => $isInclusive
        ];

        if ($isInterState) {
            $breakup['type'] = 'IGST';
            $breakup['igst_amount'] = $taxAmount;
            $breakup['cgst_amount'] = 0;
            $breakup['sgst_amount'] = 0;
        } else {
            $breakup['type'] = 'CGST_SGST';
            $breakup['igst_amount'] = 0;
            $breakup['cgst_amount'] = $taxAmount / 2;
            $breakup['sgst_amount'] = $taxAmount / 2;
        }

        return $breakup;
    }

    /**
     * Get Origin State Code (Vendor or Admin)
     */
    private function getOriginState(Item $item): string
    {
        // 1. Check Multi-Vendor Support
        if ($this->gstHelper->isMultiVendorEnabled($item->getStoreId())) {
            // 2. Check Vendor Origin
            $product = $item->getProduct();
            $vendorAttr = $this->gstHelper->getProductVendorAttribute() ?: 'vendor_id';
            $vendorId = $product->getData($vendorAttr);

            if ($vendorId) {
                // Fetch Vendor Profile 
                $vendorState = $this->vendorProfileResource->getVendorStateCode((int) $vendorId);
                if ($vendorState) {
                    return $vendorState;
                }
            }
        }

        // 3. Fallback to Shipping Origin Config (Magento Standard)
        $storeId = $item->getStoreId();
        $adminState = $this->gstHelper->getOriginState($storeId);
        if ($adminState) {
            return $adminState;
        }

        // 3. Fallback to Shipping Origin Config (Magento Standard)
        $regionId = $this->scopeConfig->getValue(
            \Magento\Shipping\Model\Config::XML_PATH_ORIGIN_REGION_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($regionId) {
            // We need to resolve Region ID to Code
            // Assuming regionFactory is injected (need to add it)
            // simplified: we'll stick to what we have or return hardcode if failing
        }

        return self::ADMIN_STATE_CODE;
    }

    private function mapStateCode($regionCode, $regionId)
    {
        // Simplified mapping (Magento uses ID often, we need to map to GST State Codes ideally)
        // For MVP, valid mapping is crucial. 
        // Return Region Code directly if it matches standard ISO (e.g., KA, MH, DL)
        return $regionCode ?: 'DL'; // Defaulting to test
    }

    /**
     * Check if Pricing/Calculation is Including Tax
     * Checks both Global Tax Config AND Indian GST Config to support legacy setup
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isInclusivePricing($storeId = null): bool
    {
        $global = $this->scopeConfig->isSetFlag(
            'tax/calculation/price_includes_tax',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $custom = ($this->scopeConfig->getValue(
            'tax_indiangst/general/display_price',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        ) == 2);

        return ($global || $custom);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isDisplayInclusive($storeId = null): bool
    {
        $displayType = (int) $this->scopeConfig->getValue(
            'tax/display/type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        // Return true if Include (2) or Both (3)
        return ($displayType === 2 || $displayType === 3);
    }
}
