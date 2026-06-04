<?php
namespace Vendor\Ads\Block\Vendor;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Api\BidRepositoryInterface;
use Vendor\Ads\Api\WalletRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class Form extends Template
{
    public function __construct(
        Context $context,
        private CustomerSession $customerSession,
        private VendorResolverInterface $vendorResolver,
        private BidRepositoryInterface $bidRepository,
        private ProductCollectionFactory $productCollectionFactory,
        private \Magento\Search\Model\QueryFactory $queryFactory,
        private WalletRepositoryInterface $walletRepository,
        private \Vendor\Ads\Model\CampaignFactory $campaignFactory,
        private \Vendor\Ads\Model\AdGroupFactory $adGroupFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getVendorId(): ?int
    {
        return $this->vendorResolver->getVendorIdByCustomer(
            (int)$this->customerSession->getCustomerId()
        );
    }

    public function getBid()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                return $this->bidRepository->getById($id);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    public function getVendorProducts(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return [];

        $walletRechargeSku = $this->_scopeConfig->getValue(
            'vendor_ads/wallet_recharge/recharge_product_sku',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ?: 'wallet-recharge';

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('vendor_id', $vendorId);
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('type_id', ['nin' => [
            \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
            \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
        ]]);

        $products = [];
        foreach ($collection as $product) {
            if ($product->getSku() === $walletRechargeSku) continue;
            $products[] = [
                'id'   => $product->getId(),
                'name' => $product->getName(),
                'sku'  => $product->getSku(),
            ];
        }
        return $products;
    }

    public function getVendorCampaigns(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return [];

        $collection = $this->campaignFactory->create()->getCollection()
            ->addFieldToFilter('vendor_id', $vendorId)
            ->addFieldToFilter('status', 1)
            ->setOrder('created_at', 'DESC');

        $campaigns = [];
        foreach ($collection as $c) {
            $campaigns[] = [
                'id'           => $c->getCampaignId(),
                'name'         => $c->getName(),
                'daily_budget' => $c->getDailyBudget(),
                'group_id'     => $c->getData('group_id'),
            ];
        }
        return $campaigns;
    }

    /**
     * Returns groups with nested campaigns for the hierarchy selector.
     * Structure: [{ id, name, status, campaigns: [{id, name, daily_budget}] }]
     */
    public function getVendorGroups(): array
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return [];

        $groupCollection = $this->adGroupFactory->create()->getCollection()
            ->addFieldToFilter('vendor_id', $vendorId)
            ->setOrder('created_at', 'DESC');

        $campaigns = $this->getVendorCampaigns(); // already filtered by vendor

        $groups = [];
        foreach ($groupCollection as $g) {
            $gId = $g->getGroupId();
            $nested = array_values(array_filter($campaigns, fn($c) => (int)$c['group_id'] === (int)$gId));
            $groups[] = [
                'id'        => $gId,
                'name'      => $g->getName(),
                'status'    => $g->getStatus(),
                'campaigns' => $nested,
            ];
        }
        return $groups;
    }


    public function getWalletBalance(): float
    {
        $vendorId = $this->getVendorId();
        if (!$vendorId) return 0.0;

        try {
            $wallet = $this->walletRepository->getOrCreate($vendorId);
            return (float)$wallet->getBalance();
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/save');
    }

    public function getMatchTypes(): array
    {
        return [
            ['value' => 'exact',  'label' => __('Exact Match')],
            ['value' => 'phrase', 'label' => __('Phrase Match')],
            ['value' => 'broad',  'label' => __('Broad Match')],
        ];
    }

    public function getKeywordText($queryId): string
    {
        if (!$queryId) return '';
        $query = $this->queryFactory->create()->load($queryId);
        return (string)$query->getQueryText();
    }

    public function getKeywordSuggestUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/keywordsuggest');
    }

    public function getBidSuggestUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/bidsuggest');
    }

    public function getWalletUrl(): string
    {
        return $this->getUrl('vendor_ads/vendor/wallet');
    }
}
