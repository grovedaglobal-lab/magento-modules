<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory;
use Magento\Tax\Model\ClassModel;

class TaxClass implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->_collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $collection = $this->_collectionFactory->create();
        $collection->addFieldToFilter('class_type', ClassModel::TAX_CLASS_TYPE_PRODUCT);

        $options = [['value' => '', 'label' => __('-- Select Tax Class --')]];
        foreach ($collection as $taxClass) {
            $options[] = [
                'value' => $taxClass->getId(),
                'label' => $taxClass->getClassName()
            ];
        }
        return $options;
    }
}
