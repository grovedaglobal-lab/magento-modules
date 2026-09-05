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
            $extractedFiles = [];
            foreach ($zipPaths as $zipPath) {
                if (file_exists($zipPath)) {
                    $files = $this->zipExtractor->extract($zipPath, $extractedDir, $result);
                    $extractedFiles = array_merge($extractedFiles, $files);
                }
            }

            if (empty($extractedFiles) && is_dir($extractedDir)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractedDir, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isFile()) {
                        $extractedFiles[] = $fileInfo->getPathname();
                    }
                }
            }

            if (empty($extractedFiles)) {
                $result->addGlobalError("No valid image files found in the uploaded file(s). Allowed formats: .jpg, .jpeg, .png, .webp");
            }

            $groupedSkus = $this->skuMatcher->matchAndValidate($extractedFiles, $vendorId, $result);
            $totalValidSkus = count($groupedSkus);
            
            if ($totalValidSkus === 0 && count($extractedFiles) > 0 && !$result->hasErrors()) {
                $result->addGlobalError("No matching product SKUs could be resolved from the uploaded image(s).");
            }

            $job->setTotalSkus($totalValidSkus)->save();
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

            // Determine final status
            $status = 'completed';
            if ($result->hasErrors()) {
                if ($result->successCount === 0) {
                    $status = 'failed';
                } else {
                    $status = 'partial';
                }
            } elseif ($result->successCount === 0) {
                $status = 'failed';
            }
            
            $job->setProcessedSkus($processed)
                ->setSuccessCount($result->successCount)
                ->setStatus($status)
                ->setResultJson(json_encode($result->toArray()))
                ->setCompletedAt((new \DateTime())->format('Y-m-d H:i:s'))
                ->save();
                
        } catch (Exception $e) {
            $result->addGlobalError($e->getMessage());
            $job->setStatus('failed')
                ->setResultJson(json_encode($result->toArray()))
                ->setCompletedAt((new \DateTime())->format('Y-m-d H:i:s'))
                ->save();
        }
    }
}
