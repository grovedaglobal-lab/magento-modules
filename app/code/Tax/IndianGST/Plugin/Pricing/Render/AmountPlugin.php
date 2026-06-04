<?php
namespace Tax\IndianGST\Plugin\Pricing\Render;

use Magento\Framework\Pricing\Render\Amount;
use Tax\IndianGST\Model\Calculator\GstCalculator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\Product;

class AmountPlugin
{
    const XML_PATH_DISPLAY_PRICE = 'tax_indiangst/general/display_price';

    /**
     * @var GstCalculator
     */
    protected $gstCalculator;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        GstCalculator $gstCalculator,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->gstCalculator = $gstCalculator;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Modify Display Value to include GST if configured
     *
     * @param Amount $subject
     * @param float $result
     * @return float
     */
    public function afterGetDisplayValue(Amount $subject, $result)
    {
        // LOGGING START
        $saleableItem = $subject->getSaleableItem();
        $sku = ($saleableItem instanceof Product) ? $saleableItem->getSku() : 'Unknown';

        $config = $this->scopeConfig->getValue(self::XML_PATH_DISPLAY_PRICE, ScopeInterface::SCOPE_STORE);
        $isInclusive = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', ScopeInterface::SCOPE_STORE);

        $logMsg = "SKU: $sku | Result: $result | Config: $config | IsInclusive: $isInclusive\n";
        file_put_contents('/tmp/gst_price.log', $logMsg, FILE_APPEND);
        // LOGGING END

        // 1. Check Config: 2 = Inc Tax
        if ($config != 2) {
            return $result;
        }

        // 2. Check if Catalog Prices ALREADY Include Tax
        if ($isInclusive) {
            return $result;
        }

        // 3. Get Product
        if (!$saleableItem instanceof Product) {
            return $result;
        }

        // 4. Calculate Tax
        $taxAmount = $this->gstCalculator->calculateProductTax($saleableItem, (float) $result);

        // Final log
        file_put_contents(BP . '/var/log/gst_price.log', " -> TaxAdded: $taxAmount | Final: " . ($result + $taxAmount) . "\n", FILE_APPEND);

        return $result + $taxAmount;
    }
}
