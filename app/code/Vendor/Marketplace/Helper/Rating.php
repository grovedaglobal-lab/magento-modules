<?php
namespace Vendor\Marketplace\Helper;

use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class Rating
{
    /**
     * @var ReviewCollectionFactory
     */
    private $reviewCollectionFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        ReviewCollectionFactory $reviewCollectionFactory,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Get vendor rating data
     *
     * @param int $vendorId
     * @return array
     */
    public function getVendorRatingData($vendorId)
    {
        $data = [
            'average_rating' => 0,
            'review_count' => 0
        ];

        try {
            $productCollection = $this->productCollectionFactory->create()
                ->addAttributeToFilter('vendor_id', $vendorId);
            $productIds = $productCollection->getAllIds();

            if (!empty($productIds)) {
                $reviewCollection = $this->reviewCollectionFactory->create()
                    ->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)
                    ->addFieldToFilter('entity_pk_value', ['in' => $productIds]);

                $reviewCollection->addRateVotes();

                $count = $reviewCollection->getSize();
                if ($count > 0) {
                    $totalPercent = 0;
                    foreach ($reviewCollection as $review) {
                        $votes = $review->getRatingVotes();
                        $sum = 0;
                        foreach ($votes as $vote) {
                            $sum += $vote->getPercent();
                        }
                        $totalPercent += (count($votes) > 0) ? ($sum / count($votes)) : 0;
                    }
                    $data['average_rating'] = ($totalPercent / $count / 100) * 5;
                    $data['review_count'] = $count;
                }
            }
        } catch (\Exception $e) {
            // Log error
        }

        return $data;
    }
}
