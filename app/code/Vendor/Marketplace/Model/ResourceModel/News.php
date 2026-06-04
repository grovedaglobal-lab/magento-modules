<?php
namespace Vendor\Marketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class News extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('vendor_news', 'entity_id');
    }

    /**
     * Perform actions after object save
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->saveSellers($object);
        return parent::_afterSave($object);
    }

    /**
     * Save news to seller mapping
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    public function saveSellers($object)
    {
        $newsId = $object->getId();
        $sellers = $object->getData('seller_ids');

        $connection = $this->getConnection();
        $table = $this->getTable('vendor_news_seller');

        // Always delete old mappings first
        $connection->delete($table, ['news_id = ?' => $newsId]);

        if ($object->getData('target_type') === \Vendor\Marketplace\Model\News::TARGET_SPECIFIC && !empty($sellers)) {
            $data = [];
            foreach ($sellers as $sellerId) {
                $data[] = [
                    'news_id' => (int) $newsId,
                    'vendor_id' => (int) $sellerId
                ];
            }
            $connection->insertMultiple($table, $data);
        }

        return $this;
    }

    /**
     * Get seller IDs associated with news
     *
     * @param int $newsId
     * @return array
     */
    public function getSellerIds($newsId)
    {
        $connection = $this->getConnection();
        $table = $this->getTable('vendor_news_seller');
        $select = $connection->select()->from($table, 'vendor_id')->where('news_id = ?', (int) $newsId);
        return $connection->fetchCol($select);
    }
}
