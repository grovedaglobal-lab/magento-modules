<?php
namespace Vendor\Ads\Block\Vendor;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Magento\Framework\App\ResourceConnection;

class Index extends Template
{
    protected $customerSession;
    protected $vendorResolver;
    protected $resource;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->vendorResolver  = $vendorResolver;
        $this->resource        = $resource;
    }

    public function getVendorId(): ?int
    {
        return $this->vendorResolver->getVendorIdByCustomer((int)$this->customerSession->getCustomerId());
    }

    public function getCampaigns(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) {
            return [];
        }

        $connection    = $this->resource->getConnection();
        $bidTable      = $this->resource->getTableName('vendor_ads_bid');
        $walletTable   = $this->resource->getTableName('vendor_ads_wallet');
        $campaignTable = $this->resource->getTableName('vendor_ads_campaign');
        $groupTable    = $this->resource->getTableName('vendor_ads_group');

        $select = $connection->select()
            ->from(['c' => $campaignTable])
            ->joinLeft(['g' => $groupTable], 'c.group_id = g.group_id', ['group_name' => 'name'])
            ->joinLeft(['w' => $walletTable], 'c.vendor_id = w.vendor_id', ['balance'])
            ->joinLeft(
                ['b' => $bidTable],
                'c.campaign_id = b.campaign_id',
                ['ad_count' => new \Zend_Db_Expr('COUNT(b.bid_id)')]
            )
            ->where('c.vendor_id = ?', $vendorId)
            ->group('c.campaign_id')
            ->order('c.campaign_id DESC');

        return $connection->fetchAll($select);
    }


    public function getCreateBidUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/create');
    }

    public function getEditUrl(int $campaignId): string
    {
        return $this->getUrl('vendor_ads/vendor/edit', ['id' => $campaignId]);
    }

    public function getToggleStatusUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/togglestatus');
    }

    public function getCampaignKeywordsUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/campaignkeywords');
    }
}
