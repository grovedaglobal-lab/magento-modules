<?php
declare(strict_types=1);

namespace Tax\IndianGST\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class GstResolver implements ResolverInterface
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

        $model = $value['model'];

        if (!($model instanceof Quote) && !($model instanceof Order)) {
            return null;
        }
        $fieldName = $field->getName();

        if ($model instanceof Quote) {
            $address = $model->getShippingAddress();
            if (!$address)
                return null;
            $amount = $address->getData($fieldName);
            $currencyCode = $model->getQuoteCurrencyCode();
        } else {
            $amount = $model->getData($fieldName);
            $currencyCode = $model->getOrderCurrencyCode();
        }

        if ($amount === null || (float) $amount === 0.0) {
            return null;
        }

        return [
            'value' => (float) $amount,
            'currency' => $currencyCode
        ];
    }
}
