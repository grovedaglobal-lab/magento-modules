<?php
namespace Vendor\Ads\Controller\Adminhtml\Bid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Ads\Api\BidRepositoryInterface;

class Toggle extends Action
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
        $active = $this->getRequest()->getParam('active');
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $bid = $this->bidRepository->getById($bidId);
            $bid->setIsActive($active);
            $this->bidRepository->save($bid);
            
            $this->messageManager->addSuccessMessage($active ? __('Bid resumed successfully.') : __('Bid paused successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
