<?php
namespace Vendor\Ads\Plugin;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Model\Product;

class ProductBlockPlugin
{
    /** @var \Vendor\Ads\Model\SearchState */
    protected $searchState;

    public function __construct(
        \Vendor\Ads\Model\SearchState $searchState
    ) {
        $this->searchState = $searchState;
    }

    /**
     * Inject Sponsored label into product details
     *
     * @param AbstractProduct $subject
     * @param string $result
     * @param Product $product
     * @return string
     */
    public function afterGetProductDetailsHtml(AbstractProduct $subject, $result, Product $product)
    {
        if ($this->searchState->isSponsored($product->getId()) || $product->getData('is_sponsored')) {
            $label = '<div class="sponsored-tag-container">' .
                     '<span class="sponsored-label">' . __('Sponsored') . '</span>' .
                     '</div>';
            
            // Subtle JS to highlight the parent card without template modification
            $js = '<script>
                (function() {
                    var label = document.currentScript.previousElementSibling;
                    var parent = label.closest(".product-item-info");
                    if (parent) {
                        parent.classList.add("sponsored-item-highlight");
                        parent.setAttribute("data-sponsored", "true");
                    }
                })();
            </script>';
            
            return $label . $js . $result;
        }
        return $result;
    }
}
