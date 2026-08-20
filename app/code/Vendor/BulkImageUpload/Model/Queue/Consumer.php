<?php
namespace Vendor\BulkImageUpload\Model\Queue;

use Vendor\BulkImageUpload\Model\ZipExtractor;
use Vendor\BulkImageUpload\Model\SkuMatcher;
use Vendor\BulkImageUpload\Model\ImageAssigner;
use Vendor\BulkImageUpload\Model\BulkUploadResult;
use Vendor\BulkImageUpload\Model\BulkImageJobFactory;
use Exception;

class Consumer
{
    protected $zipExtractor;
    protected $skuMatcher;
    protected $imageAssigner;
    protected $jobFactory;

    public function __construct(
        ZipExtractor $zipExtractor,
        SkuMatcher $skuMatcher,
        ImageAssigner $imageAssigner,
        BulkImageJobFactory $jobFactory
    ) {
        $this->zipExtractor = $zipExtractor;
        $this->skuMatcher = $skuMatcher;
        $this->imageAssigner = $imageAssigner;
        $this->jobFactory = $jobFactory;
    }

    public function process($message)
    {
        $data = json_decode($message, true);
        $jobId = $data['job_id'];
        $vendorId = $data['vendor_id'];
        $tempDir = $data['temp_dir'];
        
        // Handle both old single 'zip_path' and new array 'zip_paths'
        $zipPaths = isset($data['zip_paths']) ? $data['zip_paths'] : [$data['zip_path']];

        $job = $this->jobFactory->create()->load($jobId);
        if (!$job->getId()) return;
        
        $job->setStatus('processing')->save();

        $result = new BulkUploadResult();
        try {
            $extractedDir = $tempDir . '/extracted';
            
            // Extract all zip files into the same extracted directory
            foreach ($zipPaths as $zipPath) {
                if (file_exists($zipPath)) {
                    $this->zipExtractor->extract($zipPath, $extractedDir);
                }
            }

            $extractedFiles = [];
            if (is_dir($extractedDir)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractedDir, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isFile()) {
                        $extractedFiles[] = $fileInfo->getPathname();
                    }
                }
            }

            $groupedSkus = $this->skuMatcher->matchAndValidate($extractedFiles, $vendorId, $result);
            
            $job->setTotalSkus(count($groupedSkus))->save();
            $processed = 0;
            
            foreach ($groupedSkus as $sku => $images) {
                $this->imageAssigner->assignImages($sku, $images, $result, 10, $vendorId);
                $processed++;
                if ($processed % 5 == 0) {
                    $job->setProcessedSkus($processed)
                        ->setSuccessCount($result->successCount)
                        ->save();
                }
            }
            
            $job->setProcessedSkus($processed)
                ->setSuccessCount($result->successCount)
                ->setStatus('completed')
                ->setResultJson(json_encode($result->toArray()))
                ->setCompletedAt((new \DateTime())->format('Y-m-d H:i:s'))
                ->save();
                
        } catch (Exception $e) {
            $result->addGlobalError($e->getMessage());
            $job->setStatus('failed')
                ->setResultJson(json_encode($result->toArray()))
                ->save();
        }
    }
}

