<?php
namespace Vendor\Marketplace\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Helper\Rating as RatingHelper;

class VendorByUrl implements ResolverInterface
{
    /**
     * @var VendorFactory
     */
    private $vendorFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RatingHelper
     */
    private $ratingHelper;

    /**
     * @param VendorFactory $vendorFactory
     * @param StoreManagerInterface $storeManager
     * @param RatingHelper $ratingHelper
     */
    public function __construct(
        VendorFactory $vendorFactory,
        StoreManagerInterface $storeManager,
        RatingHelper $ratingHelper
    ) {
        $this->vendorFactory = $vendorFactory;
        $this->storeManager = $storeManager;
        $this->ratingHelper = $ratingHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($args['shop_url'])) {
            return null;
        }

        $shopUrl = strtolower(trim($args['shop_url']));
        $collection = $this->vendorFactory->create()->getCollection();

        // Search by shop_url OR shop_name (case-insensitive) using separate OR conditions
        $collection->getSelect()->where(
            'LOWER(shop_url) = ? OR LOWER(shop_name) = ?',
            $shopUrl,
            $shopUrl
        );

        // If no result, also try matching slug with spaces replaced
        $vendor = $collection->getFirstItem();

        if (!$vendor->getId()) {
            // Try with hyphens converted to spaces (e.g. "farm-organic" → "farm organic")
            $shopUrlWithSpaces = str_replace('-', ' ', $shopUrl);
            $collection2 = $this->vendorFactory->create()->getCollection();
            $collection2->getSelect()->where(
                'LOWER(shop_url) = ? OR LOWER(shop_name) = ?',
                $shopUrlWithSpaces,
                $shopUrlWithSpaces
            );
            $vendor = $collection2->getFirstItem();
        }

        if (!$vendor->getId()) {
            return null;
        }

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        $vendorData = [
            'entity_id' => $vendor->getData('entity_id'),
            'shop_name' => $vendor->getData('shop_name'),
            'shop_url' => $vendor->getData('shop_url'),
            'logo' => $vendor->getData('logo'),
            'banner' => $vendor->getData('banner'),
            'description' => $vendor->getData('description'),
            'email' => $vendor->getData('email'),
            'phone' => $vendor->getData('phone'),
            'address' => $vendor->getData('address'),
            'city' => $vendor->getData('city'),
            'state' => $vendor->getData('state'),
            'zip_code' => $vendor->getData('zip_code'),
            'country' => $vendor->getData('country'),
            'company_name' => $vendor->getData('company_name'),
            'gst_number' => $vendor->getData('tax_id'),
            'pan_number' => null, // Redacted on public storefront
            'signature' => null,
            'authorized_name' => $vendor->getData('authorized_name'),
            'brand_header_image' => $vendor->getData('brand_header_image'),
            'brand_slider_image_1' => $vendor->getData('brand_slider_image_1'),
            'brand_slider_image_2' => $vendor->getData('brand_slider_image_2'),
            'brand_slider_image_3' => $vendor->getData('brand_slider_image_3'),
            'status' => (int) $vendor->getData('status'),
        ];

        // Add rating data
        $ratingData = $this->ratingHelper->getVendorRatingData($vendor->getId());
        $vendorData['average_rating'] = $ratingData['average_rating'];
        $vendorData['review_count'] = $ratingData['review_count'];

        // Add full URLs for logo and banner
        $vendorData['logo_url'] = $vendorData['logo']
            ? $mediaUrl . 'vendor/logo/' . $vendorData['logo']
            : null;

        $vendorData['banner_url'] = $vendorData['banner']
            ? $mediaUrl . 'vendor/banner/' . $vendorData['banner']
            : null;

        $vendorData['signature_url'] = null; // Redacted on public storefront

        $vendorData['brand_header_image_url'] = $vendorData['brand_header_image']
            ? $mediaUrl . 'vendor/brand/' . $vendorData['brand_header_image']
            : null;
        $vendorData['brand_slider_image_1_url'] = $vendorData['brand_slider_image_1']
            ? $mediaUrl . 'vendor/brand/' . $vendorData['brand_slider_image_1']
            : null;
        $vendorData['brand_slider_image_2_url'] = $vendorData['brand_slider_image_2']
            ? $mediaUrl . 'vendor/brand/' . $vendorData['brand_slider_image_2']
            : null;
        $vendorData['brand_slider_image_3_url'] = $vendorData['brand_slider_image_3']
            ? $mediaUrl . 'vendor/brand/' . $vendorData['brand_slider_image_3']
            : null;

        return $vendorData;
    }
}
