<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * AJAX: returns all ads (keywords, bids, products, status) for a given campaign
 */
class CampaignKeywords extends Action
{
    private JsonFactory $jsonFactory;
    private CustomerSession $customerSession;
    private VendorResolverInterface $vendorResolver;
    private ResourceConnection $resource;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        ResourceConnection $resource,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory    = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->vendorResolver  = $vendorResolver;
        $this->resource        = $resource;
        $this->logger          = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            if (!$this->customerSession->isLoggedIn()) {
                return $result->setData(['success' => false, 'error' => 'Not logged in']);
            }

            $vendorId   = $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
            $campaignId = (int)$this->getRequest()->getParam('campaign_id');

            if (!$vendorId || !$campaignId) {
                return $result->setData(['success' => false, 'error' => 'Invalid request']);
            }

            $connection = $this->resource->getConnection();
            $bidTable   = $this->resource->getTableName('vendor_ads_bid');
            $sqTable    = $this->resource->getTableName('search_query');
            $prodTable  = $this->resource->getTableName('catalog_product_entity_varchar');
            $eavAttr    = $this->resource->getTableName('eav_attribute');
            $statsTable = $this->resource->getTableName('vendor_ads_stats');

            // Get the attribute_id for 'name' - integrated directly into the join to avoid separate query
            // and potential null syntax errors.
            $select = $connection->select()
                ->from(['b' => $bidTable], [
                    'bid_id',
                    'product_id',
                    'query_id',
                    'targeting_type',
                    'bid_amount',
                    'match_type',
                    'daily_budget',
                    'total_budget',
                    'spent_amount',
                    'start_date',
                    'end_date',
                    'is_active',
                    'created_at',
                    'version'
                ])
                ->joinLeft(
                    ['sq' => $sqTable],
                    'b.query_id = sq.query_id',
                    ['keyword' => 'query_text', 'popularity']
                )
                ->joinLeft(
                    ['attr' => $eavAttr],
                    "attr.attribute_code = 'name' AND attr.entity_type_id = 4", // 4 is catalog_product standard
                    []
                )
                ->joinLeft(
                    ['pn' => $prodTable],
                    "b.product_id = pn.entity_id AND pn.attribute_id = attr.attribute_id AND pn.store_id = 0",
                    ['product_name' => 'value']
                )
                ->joinLeft(
                    ['s' => $statsTable],
                    'b.bid_id = s.bid_id',
                    [
                        'impressions' => new \Zend_Db_Expr('COALESCE(SUM(s.impressions), 0)'),
                        'clicks'      => new \Zend_Db_Expr('COALESCE(SUM(s.clicks), 0)'),
                    ]
                )
                ->where('b.campaign_id = ?', (int)$campaignId)
                ->where('b.vendor_id = ?', (int)$vendorId)
                ->group('b.bid_id')
                ->order('b.bid_id DESC');

            $ads = $connection->fetchAll($select);

            return $result->setData([
                'success' => true,
                'ads'     => $ads
            ]);

        } catch (\Throwable $e) {
            $this->logger->critical('CampaignKeywords Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $result->setData([
                'success' => false,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString() // for debugging
            ]);
        }
    }
}
