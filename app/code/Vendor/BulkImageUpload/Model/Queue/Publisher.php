<?php
namespace Vendor\BulkImageUpload\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    protected $publisher;

    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    public function execute($jobId, $vendorId, $zipPaths, $tempDir)
    {
        $payload = json_encode([
            'job_id' => $jobId,
            'vendor_id' => $vendorId,
            'zip_paths' => is_array($zipPaths) ? $zipPaths : [$zipPaths],
            'temp_dir' => $tempDir
        ]);
        
        $this->publisher->publish('vendor.bulk.image.upload', $payload);
    }
}
