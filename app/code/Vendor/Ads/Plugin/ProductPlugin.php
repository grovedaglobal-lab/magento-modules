<?php
namespace Vendor\Ads\Plugin;

use Magento\Catalog\Model\Product;
use Vendor\Ads\Model\SearchState;

class ProductPlugin
{
    /** @var SearchState */
    protected $searchState;

    public function __construct(SearchState $searchState)
    {
        $this->searchState = $searchState;
    }

    /**
     * Add is_sponsored flag to product data
     *
     * @param Product $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterIsSponsored(Product $subject, $result)
    {
        if ($result !== null) {
            return $result;
        }

        return $this->searchState->isSponsored($subject->getId());
    }

    /**
     * Redirect sponsored product clicks through the Ads controller
     */
    public function afterGetProductUrl(Product $subject, $result)
    {
        if ($this->searchState->isSponsored($subject->getId())) {
            $bidId = $this->searchState->getBidId($subject->getId());
            return $subject->getUrlModel()->getUrl($subject, [
                '_direct' => 'vendor_ads/index/click',
                '_query' => [
                    'bid' => $bidId,
                    'product_id' => $subject->getId()
                ]
            ]);
        }

        return $result;
    }
}
