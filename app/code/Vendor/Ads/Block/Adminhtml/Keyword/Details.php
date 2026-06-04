<?php
namespace Vendor\Ads\Block\Adminhtml\Keyword;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Search\Model\QueryFactory;
use Vendor\Ads\Api\SearchLinkRepositoryInterface;
use Vendor\Ads\Service\SynonymResolver;

class Details extends Template
{
    protected $queryFactory;
    protected $searchLinkRepository;
    protected $synonymResolver;

    public function __construct(
        Context $context,
        QueryFactory $queryFactory,
        SearchLinkRepositoryInterface $searchLinkRepository,
        SynonymResolver $synonymResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->queryFactory = $queryFactory;
        $this->searchLinkRepository = $searchLinkRepository;
        $this->synonymResolver = $synonymResolver;
    }

    public function getQuery()
    {
        $queryId = $this->getRequest()->getParam('query_id');
        return $this->queryFactory->create()->load($queryId);
    }

    public function getSearchLink()
    {
        try {
            return $this->searchLinkRepository->getByQueryId($this->getRequest()->getParam('query_id'));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSynonyms()
    {
        $query = $this->getQuery();
        if ($query && $query->getId()) {
            return $this->synonymResolver->getSynonyms($query->getQueryText());
        }
        return [];
    }

    public function getBidHistory(): array
    {
        $queryId    = (int)$this->getRequest()->getParam('query_id');
        $connection = $this->resource->getConnection();
        $bidTable   = $this->resource->getTableName('vendor_ads_bid');
        $campTable  = $this->resource->getTableName('vendor_ads_campaign');

        $select = $connection->select()
            ->from(['b' => $bidTable])
            ->joinLeft(
                ['c' => $campTable],
                'c.campaign_id = b.campaign_id',
                ['campaign_name' => 'name']
            )
            ->where('b.query_id = ?', $queryId)
            ->order(['b.version DESC', 'b.created_at DESC']);

        return $connection->fetchAll($select);
    }

    public function getVendorName(int $vendorId): string
    {
        return $this->vendorResolver->getVendorName($vendorId) ?? "Vendor #{$vendorId}";
    }

    public function getCompareUrl(): string
    {
        return $this->getUrl('vendor_ads/bid/versionCompare');
    }

    public function fieldLabel(string $field): string
    {
        $map = [
            'bid_amount'     => 'Bid',
            'daily_budget'   => 'Daily Budget',
            'total_budget'   => 'Total Budget',
            'match_type'     => 'Match Type',
            'targeting_type' => 'Targeting',
            'start_date'     => 'Start Date',
            'end_date'       => 'End Date',
        ];
        return $map[$field] ?? $field;
    }
}
