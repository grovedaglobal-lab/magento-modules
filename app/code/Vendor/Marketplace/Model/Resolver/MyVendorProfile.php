<?php
namespace Vendor\Marketplace\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\Marketplace\Model\ResourceModel\Vendor\CollectionFactory;

class MyVendorProfile implements ResolverInterface
{
    /**
     * @var CollectionFactory
     */
    private $vendorCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param CollectionFactory $vendorCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CollectionFactory $vendorCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->vendorCollectionFactory = $vendorCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        // Must be an authenticated customer
        if (!$context->getUserId()) {
            return null;
        }

        $customerId = $context->getUserId();

        // Look up vendor record by customer_id
        $collection = $this->vendorCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $vendor = $collection->getFirstItem();

        if (!$vendor || !$vendor->getId()) {
            // Customer is not a vendor – return null gracefully
            return null;
        }

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $vendorData = $vendor->getData();
        $vendorData['entity_id'] = (int) $vendor->getId();

        $vendorData['logo_url'] = !empty($vendorData['logo'])
            ? $mediaUrl . 'vendor/logo/' . $vendorData['logo']
            : null;

        $vendorData['banner_url'] = !empty($vendorData['banner'])
            ? $mediaUrl . 'vendor/banner/' . $vendorData['banner']
            : null;

        $vendorData['signature_url'] = !empty($vendorData['signature'])
            ? $mediaUrl . 'vendor/signature/' . $vendorData['signature']
            : null;

        return $vendorData;
    }
}
