<?php
namespace Vendor\Marketplace\Block\Review;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Marketplace\Model\Session\VendorSession;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class ReviewList extends Template
{
    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @var ReviewCollectionFactory
     */
    protected $reviewCollectionFactory;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @param Context $context
     * @param VendorSession $vendorSession
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        VendorSession $vendorSession,
        ReviewCollectionFactory $reviewCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        $this->vendorSession = $vendorSession;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($context, $data);

        file_put_contents(BP . '/var/log/debug_custom.log', "ReviewList Block Initialized\n", FILE_APPEND);
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getReviewCollection()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'vendor.marketplace.review.list.pager'
            )->setCollection(
                    $this->getReviewCollection()
                );
            $this->setChild('pager', $pager);
            $this->getReviewCollection()->load();
        }
        return $this;
    }

    /**
     * Get review collection for vendor products
     *
     * @return \Magento\Review\Model\ResourceModel\Review\Collection
     */
    public function getReviewCollection()
    {
        if (!$this->_collection) {
            $vendorId = $this->vendorSession->getVendorId();

            // Get vendor product IDs
            $productCollection = $this->productCollectionFactory->create()
                ->addAttributeToFilter('vendor_id', $vendorId);
            $productIds = $productCollection->getAllIds();
            $productIds = !empty($productIds) ? $productIds : [0];

            $productIds = !empty($productIds) ? $productIds : [0];
            $storeId = $this->_storeManager->getStore()->getId();

            $this->_collection = $this->reviewCollectionFactory->create()
                ->addStoreFilter($storeId)
                ->addFieldToFilter('main_table.entity_pk_value', ['in' => $productIds])
                ->addStatusFilter(\Magento\Review\Model\Review::STATUS_APPROVED)
                ->setOrder('main_table.created_at', 'DESC');

            // Join product name
            $resource = $this->_collection->getResource();
            $attributeId = $this->getProductNameAttributeId();

            $this->_collection->getSelect()
                ->join(
                    ['re' => $this->_collection->getTable('review_entity')],
                    'main_table.entity_id = re.entity_id',
                    []
                )
                ->joinLeft(
                    ['cpev' => $this->_collection->getTable('catalog_product_entity_varchar')],
                    'main_table.entity_pk_value = cpev.entity_id AND cpev.attribute_id = ' . $attributeId . ' AND cpev.store_id = 0',
                    ['product_name' => 'value']
                )
                ->where('re.entity_code = ?', 'product');

            // Add ratings
            $this->_collection->addRateVotes();

            file_put_contents(BP . '/var/log/debug_review_list.log', "SQL: " . $this->_collection->getSelect()->__toString() . "\n", FILE_APPEND);
            file_put_contents(BP . '/var/log/debug_review_list.log', "Collection Size: " . $this->_collection->getSize() . "\n", FILE_APPEND);
        }
        return $this->_collection;
    }

    /**
     * Get product name attribute ID
     * 
     * @return int
     */
    private function getProductNameAttributeId()
    {
        /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute $attribute */
        $attribute = $this->productCollectionFactory->create()->getEntity()->getAttribute('name');
        return $attribute ? $attribute->getId() : 0;
    }




    /**
     * Get pager html
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * Get review status label
     *
     * @param int $statusId
     * @return \Magento\Framework\Phrase
     */
    public function getStatusLabel($statusId)
    {
        switch ($statusId) {
            case \Magento\Review\Model\Review::STATUS_APPROVED:
                return __('Approved');
            case \Magento\Review\Model\Review::STATUS_PENDING:
                return __('Pending');
            case \Magento\Review\Model\Review::STATUS_NOT_APPROVED:
                return __('Not Approved');
            default:
                return __('Unknown');
        }
    }

    /** @var \Magento\Review\Model\ResourceModel\Review\Collection */
    protected $_collection;
}
