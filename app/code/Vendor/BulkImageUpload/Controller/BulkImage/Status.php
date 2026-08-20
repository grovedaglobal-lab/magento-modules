<?php
namespace Vendor\BulkImageUpload\Controller\BulkImage;

use Vendor\Marketplace\Controller\AbstractVendor;
use Magento\Framework\App\Action\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\BulkImageUpload\Model\BulkImageJobFactory;

class Status extends AbstractVendor
{
    protected $jsonFactory;
    protected $jobFactory;

    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        JsonFactory $jsonFactory,
        BulkImageJobFactory $jobFactory
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->jobFactory = $jobFactory;
        parent::__construct($context, $vendorSession);
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        $jobId = $this->getRequest()->getParam('job_id');
        
        $job = $this->jobFactory->create()->load($jobId);
        if (!$job->getId() || $job->getVendorId() != $this->_vendorSession->getVendorId()) {
            return $resultJson->setData(['error' => 'Job not found']);
        }

        return $resultJson->setData([
            'status' => $job->getStatus(),
            'total' => $job->getTotalSkus(),
            'processed' => $job->getProcessedSkus(),
            'success' => $job->getSuccessCount(),
            'result_json' => $job->getResultJson() ? json_decode($job->getResultJson(), true) : null
        ]);
    }
}
