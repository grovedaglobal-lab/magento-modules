<?php
namespace Vendor\Ads\Cron;

use Vendor\Ads\Service\CacheManager;
use Psr\Log\LoggerInterface;

class PrecomputeRankings
{
    /** @var CacheManager */
    protected $cacheManager;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        CacheManager $cacheManager,
        LoggerInterface $logger
    ) {
        $this->cacheManager = $cacheManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            $this->logger->info('Starting Vendor Ads precomputation...');
            $this->cacheManager->precomputeAll();
            $this->logger->info('Vendor Ads precomputation completed successfully.');
        } catch (\Exception $e) {
            $this->logger->error('Error during Vendor Ads precomputation: ' . $e->getMessage());
        }
    }
}
