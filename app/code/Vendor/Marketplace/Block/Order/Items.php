<?php
namespace Vendor\Marketplace\Block\Order;

use Magento\Sales\Block\Order\Items as MagentoOrderItems;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Vendor\Marketplace\Helper\Data as VendorHelper;

class Items extends MagentoOrderItems
{
    /**
     * @var VendorHelper
     */
    protected $vendorHelper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param VendorHelper $vendorHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        VendorHelper $vendorHelper,
        array $data = []
    ) {
        $this->vendorHelper = $vendorHelper;
        parent::__construct($context, $registry, $data);
    }

    /**
     * Check if price should be displayed including tax
     * @return bool
     */
    public function displayPriceInclTax()
    {
        $value = $this->getConfigValue('tax/sales_display/price', $this->getOrder()->getStoreId());
        return $value == \Magento\Tax\Model\Config::DISPLAY_TYPE_INCLUDING_TAX ||
            $value == \Magento\Tax\Model\Config::DISPLAY_TYPE_BOTH;
    }

    /**
     * Check if subtotal should be displayed including tax
     * @return bool
     */
    public function displaySubtotalInclTax()
    {
        $value = $this->getConfigValue('tax/sales_display/subtotal', $this->getOrder()->getStoreId());
        return $value == \Magento\Tax\Model\Config::DISPLAY_TYPE_INCLUDING_TAX ||
            $value == \Magento\Tax\Model\Config::DISPLAY_TYPE_BOTH;
    }

    /**
     * Get system configuration value
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigValue($path, $storeId = null)
    {
        return $this->_scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get order items - filter by vendor
     * @return array
     */
    public function getItems()
    {
        $items = [];
        $order = $this->getOrder();
        $vendorOrder = $this->_coreRegistry->registry('current_vendor_order');

        if ($order && $vendorOrder) {
            $vendorId = $vendorOrder->getVendorId();
            $items = $this->vendorHelper->getVendorOrderItems($order, $vendorId);
        }

        return $items;
    }

    /**
     * Get item options
     * @param \Magento\Sales\Model\Order\Item $item
     * @return array
     */
    public function getItemOptions($item)
    {
        $result = [];
        $options = $item->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }
        return $result;
    }
}
