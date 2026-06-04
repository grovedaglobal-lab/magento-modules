<?php
namespace Vendor\Ads\Controller\Adminhtml\Keyword;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Vendor\Ads\Api\SearchLinkRepositoryInterface;
use Vendor\Ads\Api\Data\SearchLinkInterfaceFactory;
use Magento\Framework\Controller\Result\RedirectFactory;

class Toggle extends Action
{
    const ADMIN_RESOURCE = 'Vendor_Ads::ads_keywords';

    protected $searchLinkRepository;
    protected $searchLinkFactory;

    public function __construct(
        Context $context,
        SearchLinkRepositoryInterface $searchLinkRepository,
        SearchLinkInterfaceFactory $searchLinkFactory
    ) {
        parent::__construct($context);
        $this->searchLinkRepository = $searchLinkRepository;
        $this->searchLinkFactory = $searchLinkFactory;
    }

    public function execute()
    {
        $queryId = $this->getRequest()->getParam('query_id');
        $active = $this->getRequest()->getParam('active');
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            try {
                $searchLink = $this->searchLinkRepository->getByQueryId($queryId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $searchLink = $this->searchLinkFactory->create();
                $searchLink->setQueryId($queryId);
            }
            
            $searchLink->setIsAdsEnabled($active);
            $this->searchLinkRepository->save($searchLink);
            
            $this->messageManager->addSuccessMessage(__('Ads status updated successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
