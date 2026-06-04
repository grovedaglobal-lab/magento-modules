<?php
namespace Vendor\Ads\Controller\Adminhtml\Bid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Ads\Api\BidRepositoryInterface;

class Cancel extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_bids';

    protected $bidRepository;

    public function __construct(
        Context $context,
        BidRepositoryInterface $bidRepository
    ) {
        parent::__construct($context);
        $this->bidRepository = $bidRepository;
    }

    public function execute()
    {
        $bidId = $this->getRequest()->getParam('bid_id');
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $bid = $this->bidRepository->getById($bidId);
            $this->bidRepository->delete($bid);
            $this->messageManager->addSuccessMessage(__('Bid cancelled and deleted successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
