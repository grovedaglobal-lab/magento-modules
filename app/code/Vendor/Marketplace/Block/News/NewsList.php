<?php
namespace Vendor\Marketplace\Block\News;

use Magento\Framework\View\Element\Template;
use Vendor\Marketplace\Model\ResourceModel\News\CollectionFactory;
use Vendor\Marketplace\Model\Session\VendorSession;

class NewsList extends Template
{
    /**
     * @var CollectionFactory
     */
    protected $newsCollectionFactory;

    /**
     * @var VendorSession
     */
    protected $vendorSession;

    /**
     * @var \Vendor\Marketplace\Model\ResourceModel\News\Collection
     */
    protected $newsCollection;

    /**
     * @param Template\Context $context
     * @param CollectionFactory $newsCollectionFactory
     * @param VendorSession $vendorSession
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        CollectionFactory $newsCollectionFactory,
        VendorSession $vendorSession,
        array $data = []
    ) {
        $this->newsCollectionFactory = $newsCollectionFactory;
        $this->vendorSession = $vendorSession;
        parent::__construct($context, $data);
    }

    /**
     * Get news collection
     *
     * @return \Vendor\Marketplace\Model\ResourceModel\News\Collection
     */
    public function getNewsCollection()
    {
        if (!$this->newsCollection) {
            $vendorId = $this->vendorSession->getVendorId();
            $collection = $this->newsCollectionFactory->create();

            $collection->addFieldToFilter('status', 1);

            // Join mapping table and filter by vendor ID or target_type = all
            $collection->getSelect()->joinLeft(
                ['vns' => $collection->getTable('vendor_news_seller')],
                'main_table.entity_id = vns.news_id',
                []
            );

            $collection->getSelect()->where(
                "main_table.target_type = 'all' OR (main_table.target_type = 'specific' AND vns.vendor_id = ?)",
                (int) $vendorId
            );

            $collection->getSelect()->group('main_table.entity_id');
            $collection->setOrder('created_at', 'DESC');

            $this->newsCollection = $collection;
        }
        return $this->newsCollection;
    }

    /**
     * Prepare layout for pagination
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getNewsCollection()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'vendor.news.pager'
            )->setCollection(
                    $this->getNewsCollection()
                );
            $this->setChild('pager', $pager);
            $this->getNewsCollection()->load();
        }
        return $this;
    }

    /**
     * Get pager HTML
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }
}
