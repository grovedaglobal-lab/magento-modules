<?php
namespace Vendor\Ads\Controller\Vendor\Campaign;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Vendor\Ads\Api\VendorResolverInterface;

/**
 * Returns the list of campaigns belonging to the logged-in vendor
 */
class ListAction extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CustomerSession $customerSession,
        private VendorResolverInterface $vendorResolver,
        private \Vendor\Ads\Model\CampaignFactory $campaignFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['campaigns' => []]);
        }

        $vendorId = $this->vendorResolver->getVendorIdByCustomer(
            (int)$this->customerSession->getCustomerId()
        );

        if (!$vendorId) {
            return $result->setData(['campaigns' => []]);
        }

        $collection = $this->campaignFactory->create()->getCollection()
            ->addFieldToFilter('vendor_id', $vendorId)
            ->setOrder('created_at', 'DESC');

        $campaigns = [];
        foreach ($collection as $c) {
            $campaigns[] = [
                'id'     => $c->getCampaignId(),
                'name'   => $c->getName(),
                'status' => $c->getStatus(),
                'daily_budget' => $c->getDailyBudget(),
            ];
        }

        return $result->setData(['campaigns' => $campaigns]);
    }
}
