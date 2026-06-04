<?php
namespace Vendor\Marketplace\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Marketplace\Model\VendorFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Helper\Rating as RatingHelper;

class Vendor implements ResolverInterface
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
        if (!isset($args['id'])) {
            throw new GraphQlInputException(__('Vendor ID is required'));
        }

        $vendorId = $args['id'];
        $collection = $this->vendorFactory->create()->getCollection();
        $collection->addFieldToFilter('main_table.entity_id', $vendorId);
        $vendor = $collection->getFirstItem();

        if (!$vendor->getId()) {
            throw new GraphQlInputException(__('Vendor not found'));
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
            'pan_number' => $vendor->getData('business_license'),
            'signature' => $vendor->getData('signature'),
            'authorized_name' => $vendor->getData('authorized_name'),
            'status' => (int) $vendor->getData('status')
        ];

        // Add rating data
        $ratingData = $this->ratingHelper->getVendorRatingData($vendorId);
        $vendorData['average_rating'] = $ratingData['average_rating'];
        $vendorData['review_count'] = $ratingData['review_count'];

        // Add full URLs for logo and banner
        if ($vendorData['logo']) {
            $vendorData['logo_url'] = $mediaUrl . 'vendor/logo/' . $vendorData['logo'];
        } else {
            $vendorData['logo_url'] = null;
        }

        if ($vendorData['banner']) {
            $vendorData['banner_url'] = $mediaUrl . 'vendor/banner/' . $vendorData['banner'];
        } else {
            $vendorData['banner_url'] = null;
        }

        if ($vendorData['signature']) {
            $vendorData['signature_url'] = $mediaUrl . 'vendor/signature/' . $vendorData['signature'];
        } else {
            $vendorData['signature_url'] = null;
        }

        return $vendorData;
    }
}
