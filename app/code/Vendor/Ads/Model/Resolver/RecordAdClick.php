<?php
declare(strict_types=1);

namespace Vendor\Ads\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Vendor\Ads\Service\BillingService;

class RecordAdClick implements ResolverInterface
{
    /** @var BillingService */
    private $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $bidId = (int)($args['bid_id'] ?? 0);
        $productId = (int)($args['product_id'] ?? 0);

        if ($bidId <= 0 || $productId <= 0) {
            throw new GraphQlInputException(__('bid_id and product_id must be positive integers'));
        }

        try {
            $processed = $this->billingService->processClick($bidId);
            if ($processed) {
                // Must explicitly track stat to appear in reporting
                $this->billingService->trackStat($bidId, 'click');
            }
            return true;
        } catch (\Exception $e) {
            // Log but don't surface billing errors to the client
            return false;
        }
    }
}
