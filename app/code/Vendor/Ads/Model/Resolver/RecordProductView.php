<?php
declare(strict_types=1);

namespace Vendor\Ads\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Reports\Model\Product\Index\ViewedFactory;
use Magento\Reports\Model\EventFactory;
use Magento\Reports\Model\Event;
use Magento\Customer\Model\Visitor;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * GraphQL mutation resolver: recordProductView
 *
 * Writes a product view into Magento's native report_viewed_product_index table.
 * This is necessary because the headless Next.js frontend bypasses Magento's
 * catalog_controller_product_view event â€” so views never get tracked natively.
 *
 * After recording, Magento's existing cron jobs aggregate the data into:
 *   - report_viewed_product_aggregated_daily
 *   - report_viewed_product_aggregated_weekly
 *   - report_viewed_product_aggregated_monthly
 *   - report_viewed_product_aggregated_yearly
 *
 * These are then used by:
 *   - Magento Admin â†’ Most Viewed Products dashboard
 *   - Our trendingProductIds GraphQL query
 */
class RecordProductView implements ResolverInterface
{
    /** @var ViewedFactory */
    private $viewedFactory;

    /** @var EventFactory */
    private $eventFactory;

    /** @var Visitor */
    private $visitor;

    /** @var CustomerSession */
    private $customerSession;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ViewedFactory $viewedFactory,
        EventFactory $eventFactory,
        Visitor $visitor,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->viewedFactory   = $viewedFactory;
        $this->eventFactory    = $eventFactory;
        $this->visitor         = $visitor;
        $this->customerSession = $customerSession;
        $this->storeManager    = $storeManager;
        $this->logger          = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $productId = (int)($args['product_id'] ?? 0);

        if ($productId <= 0) {
            throw new GraphQlInputException(__('product_id must be a positive integer.'));
        }

        try {
            $storeId    = (int)$this->storeManager->getStore()->getId();
            $customerId = null;
            $visitorId  = null;

            // Resolve customer_id from the GraphQL context (authenticated requests)
            if ($context->getUserId()) {
                $customerId = (int)$context->getUserId();
            }

            // Resolve visitor_id â€” Magento generates this per session.
            // In a headless context we derive a stable visitor from the customer or
            // create an ephemeral one; the important thing is the product_id gets recorded.
            if (!$customerId) {
                // Use a hash of the product_id as a pseudo-visitor for anonymous views.
                // This is lightweight â€” Magento deduplicates on (product_id, visitor_id, store_id).
                $visitorId = abs(crc32('headless_' . $productId . '_' . $storeId));
            }

            /** @var \Magento\Reports\Model\Product\Index\Viewed $viewedIndex */
            $viewedIndex = $this->viewedFactory->create();
            $viewedIndex->setData([
                'product_id'  => $productId,
                'store_id'    => $storeId,
                'customer_id' => $customerId,
                'visitor_id'  => $visitorId,
            ]);

            // save() triggers the native Magento upsert into report_viewed_product_index
            $viewedIndex->save();

            // Record the report event for aggregated reports (Most Viewed Products)
            $event = $this->eventFactory->create();
            $event->setData([
                'event_type_id' => Event::EVENT_PRODUCT_VIEW,
                'object_id'     => $productId,
                'subject_id'    => $customerId,
                'subtype'       => 1,
                'store_id'      => $storeId
            ]);
            $event->save();

            $this->logger->debug(sprintf(
                '[RecordProductView] Recorded view for product_id=%d store_id=%d customer_id=%s',
                $productId,
                $storeId,
                $customerId ?? 'guest'
            ));

            return true;
        } catch (\Exception $e) {
            // Log but never throw â€” view tracking must never break the product page
            $this->logger->error('[RecordProductView] Failed to record product view: ' . $e->getMessage(), [
                'product_id' => $productId,
                'exception'  => $e,
            ]);
            return false;
        }
    }
}
