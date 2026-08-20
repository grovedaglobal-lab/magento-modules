<?php
namespace Vendor\BulkImageUpload\Block\BulkImage;

use Magento\Framework\View\Element\Template;
use Vendor\Marketplace\Model\Session\VendorSession;
use Vendor\BulkImageUpload\Model\ResourceModel\BulkImageJob\CollectionFactory;

class JobHistory extends Template
{
    protected $vendorSession;
    protected $collectionFactory;

    public function __construct(
        Template\Context $context,
        VendorSession $vendorSession,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->vendorSession = $vendorSession;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    public function getJobs()
    {
        $vendorId = $this->vendorSession->getVendorId();
        if (!$vendorId) {
            return [];
        }
        
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('vendor_id', $vendorId)
                   ->setOrder('created_at', 'DESC')
                   ->setPageSize(20)
                   ->setCurPage(1);
                   
        return $collection;
    }
    
    public function getStatusLabel($status)
    {
        switch ($status) {
            case 'pending': return 'Pending';
            case 'processing': return 'Processing';
            case 'completed': return 'Complete';
            case 'failed': return 'Failed';
            default: return ucfirst($status);
        }
    }
    
    public function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'pending': 
            case 'processing': return 'status-badge processing';
            case 'completed': return 'status-badge success';
            case 'failed': return 'status-badge error';
            default: return 'status-badge';
        }
    }
}
