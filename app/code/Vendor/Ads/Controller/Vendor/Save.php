<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Vendor\Ads\Api\BidRepositoryInterface;
use Vendor\Ads\Api\VendorResolverInterface;
use Vendor\Ads\Helper\Config as AdsConfig;
use Vendor\Ads\Model\BidFactory;
use Vendor\Ads\Model\CampaignFactory;
use Magento\Customer\Model\Session as CustomerSession;

class Save extends Action
{
    public function __construct(
        Context $context,
        private BidRepositoryInterface $bidRepository,
        private BidFactory $bidFactory,
        private CampaignFactory $campaignFactory,
        private \Vendor\Ads\Model\AdGroupFactory $adGroupFactory,
        private \Magento\Search\Model\QueryFactory $queryFactory,
        private CustomerSession $customerSession,
        private VendorResolverInterface $vendorResolver,
        private AdsConfig $adsConfig,
        private \Vendor\Ads\Api\WalletRepositoryInterface $walletRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $this->_redirect('*/*/');
        }

        try {
            $isEdit = !empty($data['bid_id']);
            $vendorId = $this->vendorResolver->getVendorIdByCustomer(

                (int)$this->customerSession->getCustomerId()
            );
            if (!$vendorId) {
                throw new \Exception(__('Not a vendor.'));
            }

            $this->walletRepository->getOrCreate($vendorId);

            // ── Step 0: Resolve Group ──
            $groupId   = null;
            $groupMode = $data['group_mode'] ?? 'none';

            if ($groupMode === 'existing_group' && !empty($data['group_id'])) {
                $groupId = (int)$data['group_id'];
            } elseif ($groupMode === 'new_group') {
                $groupName = trim($data['group_name'] ?? '');
                if (!$groupName) { $groupName = __('Portfolio %1', date('d M Y'))->render(); }

                // Check if group name already exists for this vendor to prevent UX frustration
                $group = $this->adGroupFactory->create();
                $groupCollection = $group->getCollection()
                    ->addFieldToFilter('vendor_id', $vendorId)
                    ->addFieldToFilter('name', $groupName);
                
                if ($groupCollection->getSize() > 0) {
                    $group = $groupCollection->getFirstItem();
                } else {
                    $group->setVendorId($vendorId);
                    $group->setName($groupName);
                    $group->setStatus((int)($data['group_status'] ?? 1));
                    $group->save();
                }
                $groupId = $group->getId();
            }

            // if 'none' → groupId stays null


            // ── Step 1: Resolve or create Campaign ──
            $campaignId   = null;
            $campaignMode = $data['campaign_mode'] ?? 'new';

            // Define these variables early as they are used in campaign creation
            $dailyBudget   = (float)($data['daily_budget'] ?? 0);
            $totalBudget   = (float)($data['total_budget'] ?? 0);

            if ($campaignMode === 'existing' && !empty($data['campaign_id'])) {
                $campaignId = (int)$data['campaign_id'];
            } else {
                $campaignName = trim($data['campaign_name'] ?? '');
                if (!$campaignName) { $campaignName = __('Campaign %1', date('d M Y'))->render(); }

                // Check for existing campaign name to prevent unique/duplicate errors
                $campaign = $this->campaignFactory->create();
                $campCollection = $campaign->getCollection()
                    ->addFieldToFilter('vendor_id', $vendorId)
                    ->addFieldToFilter('name', $campaignName);

                if ($campCollection->getSize() > 0) {
                    $campaign = $campCollection->getFirstItem();
                } else {
                    $campaign->setVendorId($vendorId);
                    $campaign->setData('group_id', $groupId);
                    $campaign->setName($campaignName);
                    $campaign->setDailyBudget($dailyBudget ?: null);
                    $campaign->setTotalBudget($totalBudget ?: null);
                    $campaign->setStatus((int)($data['campaign_status'] ?? 1));
                    $campaign->save();
                }
                $campaignId = $campaign->getId();
            }


            // ── Step 2: Shared Ad Settings ──
            $targetingType = $data['targeting_type'] ?? 'manual';
            // $dailyBudget and $totalBudget are already defined above
            $startDate     = !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d');
            $endDate       = !empty($data['end_date'])   ? $data['end_date']   : null;
            $isActive      = (int)($data['is_active'] ?? 1);
            $productId     = (int)$data['product_id'];

            // Wallet validation (one-time check for the max budget)
            $wallet = $this->walletRepository->getOrCreate($vendorId);
            $walletBalance = (float)$wallet->getBalance();
            $maxBudget = max($dailyBudget, $totalBudget);
            if ($maxBudget > 0 && $maxBudget > $walletBalance) {
                throw new \Exception(__('Budget (%1) exceeds wallet balance (%2).', number_format($maxBudget, 2), number_format($walletBalance, 2)));
            }

            $currentBidId = !empty($data['bid_id']) ? (int)$data['bid_id'] : null;
            $finalBids = [];

            if ($targetingType === 'manual') {
                $rawKeywords = $data['keywords'] ?? [];
                if (empty($rawKeywords)) {
                    throw new \Exception(__('Please add at least one keyword for Manual Targeting.'));
                }

                // Deduplicate within the request
                $uniqueKws = [];
                foreach ($rawKeywords as $kwData) {
                    $itemKey = strtolower(trim($kwData['keyword'] ?? '')) . '|' . ($kwData['match_type'] ?? 'phrase');
                    if (!isset($uniqueKws[$itemKey])) {
                        $uniqueKws[$itemKey] = $kwData;
                    }
                }

                foreach ($uniqueKws as $kwData) {
                    $keywordText = strtolower(trim($kwData['keyword'] ?? ''));
                    $matchType   = $kwData['match_type'] ?? 'phrase';
                    $bidAmount   = (float)($kwData['bid_amount'] ?? $this->adsConfig->getMinBid());

                    if (!$keywordText) continue;

                    // Resolve Query ID
                    $query = $this->queryFactory->create();
                    $query->setStoreId(0);
                    $query->loadByQueryText($keywordText);

                    if (!$query->getId()) {
                        try {
                            $query->setQueryText($keywordText);
                            $query->setStoreId(0);
                            $query->setIsActive(1);
                            $query->setIsProcessed(1);
                            $query->save();
                        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                            $query->loadByQueryText($keywordText);
                        }
                    }

                    $finalBids[] = [
                        'query_id'   => $query->getId(),
                        'match_type' => $matchType,
                        'bid_amount' => $bidAmount
                    ];
                }
            } else {
                // Auto targeting
                $finalBids[] = [
                    'query_id'   => null,
                    'match_type' => 'phrase',
                    'bid_amount' => (float)($data['bid_amount'] ?? 1.00)
                ];
            }

            $nowTimestamp = date('Y-m-d H:i:s');

            // ── Step 3: Versioning + Save Loop ──
            // On EDIT: deactivate old bid → create new version (no overwrite).
            // On CREATE: version=1, parent_ad_id=null, started_at=now.
            $count = 0;

            foreach ($finalBids as $index => $fb) {
                $oldBidId     = ($currentBidId && $index === 0) ? $currentBidId : null;
                $parentAdId   = null;
                $newVersion   = 1;

                if ($oldBidId) {
                    // Load existing bid to get version chain info
                    $oldBid = $this->bidFactory->create();
                    $oldBid->load($oldBidId);

                    if ($oldBid->getId() && (int)$oldBid->getData('vendor_id') === $vendorId) {
                        // Determine root of the version chain
                        $parentAdId = $oldBid->getData('parent_ad_id') ?: (int)$oldBid->getId();
                        $newVersion = ((int)($oldBid->getData('version') ?: 1)) + 1;

                        // Silently deactivate the old version
                        $oldBid->setData('is_active', 0);
                        $oldBid->save();
                    }
                }

                // Always create a fresh bid row (never overwrite)
                $bid = $this->bidFactory->create();
                $bid->setVendorId($vendorId);
                $bid->setData('campaign_id', $campaignId);
                $bid->setProductId($productId);
                $bid->setQueryId($fb['query_id']);
                $bid->setData('targeting_type', $targetingType);
                $bid->setMatchType($fb['match_type']);
                $bid->setBidAmount($fb['bid_amount']);
                $bid->setDailyBudget($dailyBudget ?: null);
                $bid->setTotalBudget($totalBudget ?: null);
                $bid->setStartDate($startDate);
                $bid->setEndDate($endDate);
                $bid->setIsActive($isActive);
                $bid->setData('version', $newVersion);
                $bid->setData('parent_ad_id', $parentAdId); // null for v1
                $bid->setData('started_at', $nowTimestamp);

                $this->bidRepository->save($bid);
                $count++;
            }

            $msg = $isEdit ? __('Advertisement updated successfully.') : __('Launched %1 advertisement(s) successfully!', $count);
            $this->messageManager->addSuccessMessage($msg);


        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('*/*/');
    }
}
