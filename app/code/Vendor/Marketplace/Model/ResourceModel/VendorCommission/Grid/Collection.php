<?php
namespace Vendor\Marketplace\Model\ResourceModel\VendorCommission\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Psr\Log\LoggerInterface as Logger;

class Collection extends \Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult
{
    /**
     * @inheritdoc
     */
    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        // Group by category_id to avoid duplicates in the grid
        $this->getSelect()->group('category_id');

        // Aggregate all ranges into one field for display
        $this->getSelect()->columns([
            'ranges_data' => new \Zend_Db_Expr('GROUP_CONCAT(CONCAT(min_price, ":", IFNULL(max_price, ""), ":", commission_percent) SEPARATOR "|")')
        ]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSelectCountSql()
    {
        $countSelect = parent::getSelectCountSql();
        $countSelect->reset(\Magento\Framework\DB\Select::GROUP);
        $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS);
        $countSelect->columns(new \Zend_Db_Expr('COUNT(DISTINCT category_id)'));

        return $countSelect;
    }
}
