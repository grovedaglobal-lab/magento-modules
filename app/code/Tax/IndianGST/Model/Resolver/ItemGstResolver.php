<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote\Item;

class ItemGstResolver implements ResolverInterface
{
    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['model'])) {
            return null;
        }

        $item = $value['model'];
        $fieldName = $field->getName();

        // Get currency code
        $currencyCode = 'INR'; // Default fallback
        try {
            if ($item instanceof \Magento\Quote\Model\Quote\Item) {
                if ($item->getQuote()) {
                    $currencyCode = $item->getQuote()->getQuoteCurrencyCode();
                }
            } elseif (method_exists($item, 'getOrder')) {
                $order = $item->getOrder();
                if ($order) {
                    $currencyCode = $order->getOrderCurrencyCode();
                }
            }
        } catch (\Exception $e) {
            // Fallback or log
            $currencyCode = 'INR';
        }

        if ($fieldName === 'indiangst_amount') {
            $amount = $item->getData('indiangst_tax_amount');
            if ($amount === null) {
                $amount = $item->getData('indiangst_amount');
            }
            // Return 0 if null, but with correct structure
            $val = $amount !== null ? (float) $amount : 0.0;
            return [
                'value' => $val,
                'currency' => $currencyCode
            ];
        }

        if ($fieldName === 'indiangst_percent') {
            $percent = $item->getData('indiangst_percent');
            return $percent !== null ? (float) $percent : 0.0;
        }

        if (in_array($fieldName, ['indiangst_cgst_amount', 'indiangst_sgst_amount', 'indiangst_igst_amount'])) {
            $amount = $item->getData($fieldName);
            $val = $amount !== null ? (float) $amount : 0.0;
            return [
                'value' => $val,
                'currency' => $currencyCode
            ];
        }

        return null;
    }

}
