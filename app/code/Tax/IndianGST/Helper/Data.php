<?php
declare(strict_types=1);

namespace Tax\IndianGST\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{
    const XML_PATH_MARKETPLACE_MODULE = 'tax_indiangst/marketplace_integration/marketplace_module';
    const XML_PATH_MULTI_VENDOR_ENABLED = 'tax_indiangst/marketplace_integration/enable_multi_vendor';
    const XML_PATH_MARKETPLACE_TABLE = 'tax_indiangst/marketplace_integration/vendor_table';
    const XML_PATH_MARKETPLACE_ID_COL = 'tax_indiangst/marketplace_integration/vendor_id_col';
    const XML_PATH_MARKETPLACE_CODE_COL = 'tax_indiangst/marketplace_integration/vendor_code_col';
    const XML_PATH_MARKETPLACE_ATTR = 'tax_indiangst/marketplace_integration/product_vendor_attr';
    const XML_PATH_MARKETPLACE_BUSINESS_NAME_COL = 'tax_indiangst/marketplace_integration/vendor_business_name_col';
    const XML_PATH_MARKETPLACE_GSTIN_COL = 'tax_indiangst/marketplace_integration/vendor_gstin_col';
    const XML_PATH_MARKETPLACE_PAN_COL = 'tax_indiangst/marketplace_integration/vendor_pan_col';
    const XML_PATH_MARKETPLACE_POSTCODE_COL = 'tax_indiangst/marketplace_integration/vendor_postcode_col';
    const XML_PATH_MARKETPLACE_STATE_COL = 'tax_indiangst/marketplace_integration/vendor_state_col';

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Get configured Marketplace Module
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMarketplaceModule($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_MODULE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if Multi-Vendor Mode is Enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isMultiVendorEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MULTI_VENDOR_ENABLED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get configured Vendor Table Name
     *
     * @param int|null $storeId
     * @return string
     */
    public function getVendorTableName($storeId = null)
    {
        $module = $this->getMarketplaceModule($storeId);
        if ($module === 'webkul') {
            return 'marketplace_userdata';
        } elseif ($module === 'cedcommerce') {
            return 'ced_csmarketplace_vendor';
        } elseif ($module === 'vnecoms') {
            return 'ves_vendor_entity';
        }
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_TABLE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Admin Origin State from Config
     * 
     * @param int|null $storeId
     * @return string|null
     */
    public function getOriginState($storeId = null)
    {
        return $this->scopeConfig->getValue(
            'tax_indiangst/general/admin_state',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get configured Vendor ID Column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getVendorIdColumn($storeId = null)
    {
        $module = $this->getMarketplaceModule($storeId);
        if ($module === 'webkul') {
            return 'seller_id';
        } elseif ($module === 'cedcommerce') {
            return 'vendor_id';
        } elseif ($module === 'vnecoms') {
            return 'entity_id';
        }
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_ID_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get configured Vendor Display/Code Column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getVendorCodeColumn($storeId = null)
    {
        $module = $this->getMarketplaceModule($storeId);
        if ($module === 'webkul') {
            return 'shop_url'; // or shop_title
        } elseif ($module === 'cedcommerce') {
            return 'public_name';
        } elseif ($module === 'vnecoms') {
            return 'vendor_id'; // or vendor code
        }
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_CODE_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get configured Product Attribute for Vendor Link
     *
     * @param int|null $storeId
     * @return string
     */
    public function getProductVendorAttribute($storeId = null)
    {
        $module = $this->getMarketplaceModule($storeId);
        if ($module === 'webkul') {
            return 'seller_id';
        } elseif ($module === 'cedcommerce') {
            return 'vendor_id';
        } elseif ($module === 'vnecoms') {
            return 'vendor_id';
        }
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_ATTR,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBusinessNameColumn($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_BUSINESS_NAME_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGstinColumn($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_GSTIN_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getPanColumn($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_PAN_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getPostcodeColumn($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_POSTCODE_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getStateColumn($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_MARKETPLACE_STATE_COL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Convert Amount to Words (Indian Style)
     * 
     * @param float $amount
     * @return string
     */
    public function amountToWords($amount)
    {
        $amount = (float) $amount;
        $decimal = round($amount - ($no = floor($amount)), 2) * 100;
        $hundred = null;
        $digits_1 = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
        $digits_2 = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');
        $plural = (($count = count($str = array())) && $count > 1) ? 's' : null;
        $hundred = ($decimal) ? " and " . ($digits_1[(int) ($decimal / 10)] . " " . $digits_2[(int) ($decimal % 10)]) . ' Paise' : '';
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        $i = 0;
        while ($i < count($digits)) {
            $divider = ($i == 1) ? 10 : 100;
            $amount = $no % $divider;
            $no = (int) ($no / $divider);
            $i++;
            if ($amount) {
                $plural = (($count = count($str)) && $count > 1) ? 's' : null;
                $hundred = ($i == 1 && $str) ? ' and ' : null;
                $str[] = ($amount < 20) ? $digits_1[$amount] . ' ' . $digits[$i - 1] . $plural . ' ' . $hundred : $digits_2[floor($amount / 10)] . ' ' . $digits_1[$amount % 10] . ' ' . $digits[$i - 1] . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal) ? "and " . ($digits_1[(int) ($decimal / 10)] . " " . $digits_2[(int) ($decimal % 10)]) . ' Paise' : '';
        return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . ' Only';
    }
}
