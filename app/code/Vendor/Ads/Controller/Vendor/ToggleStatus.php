<?php
namespace Vendor\Ads\Controller\Vendor;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;
use Magento\Framework\App\ResourceConnection;

class ToggleStatus extends Action
{
    protected $jsonFactory;
    protected $customerSession;
    protected $vendorResolver;
    protected $resource;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        VendorResolverInterface $vendorResolver,
        ResourceConnection $resource
    ) {
        parent::__construct($context);
        $this->jsonFactory     = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->vendorResolver  = $vendorResolver;
        $this->resource        = $resource;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Not logged in.')]);
        }

        if (!$this->getRequest()->isPost()) {
            return $result->setData(['success' => false, 'message' => __('Invalid request method. POST required.')]);
        }

        $formKeyValidator = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Data\Form\FormKey\Validator::class);
        if (!$formKeyValidator->validate($this->getRequest())) {
            return $result->setData(['success' => false, 'message' => __('Invalid form key.')]);
        }

        $vendorId = $this->vendorResolver->getVendorIdByCustomer(
            (int)$this->customerSession->getCustomerId()
        );
        if (!$vendorId) {
            return $result->setData(['success' => false, 'message' => __('Not a vendor.')]);
        }

        $campaignId = (int)$this->getRequest()->getParam('campaign_id');
        $newStatus  = (int)$this->getRequest()->getParam('status'); // 1 = active, 0 = paused

        if (!$campaignId) {
            return $result->setData(['success' => false, 'message' => __('Invalid campaign.')]);
        }

        try {
            $connection    = $this->resource->getConnection();
            $campaignTable = $this->resource->getTableName('vendor_ads_campaign');

            // Verify ownership
            $existing = $connection->fetchRow(
                "SELECT campaign_id FROM {$campaignTable} WHERE campaign_id = ? AND vendor_id = ?",
                [$campaignId, $vendorId]
            );

            if (!$existing) {
                return $result->setData(['success' => false, 'message' => __('Campaign not found or unauthorized.')]);
            }

            $connection->update(
                $campaignTable,
                ['status' => $newStatus],
                ['campaign_id = ?' => $campaignId, 'vendor_id = ?' => $vendorId]
            );

            // Cascade status to all bids belonging to this campaign.
            // is_active mirrors the campaign status so the ad engine never
            // serves ads for a paused campaign even without a query-level join.
            $bidTable = $this->resource->getTableName('vendor_ads_bid');
            $connection->update(
                $bidTable,
                ['is_active' => $newStatus],
                ['campaign_id = ?' => $campaignId, 'vendor_id = ?' => $vendorId]
            );

            return $result->setData([
                'success' => true,
                'message' => $newStatus ? __('Campaign activated.') : __('Campaign paused.'),
                'status'  => $newStatus
            ]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
