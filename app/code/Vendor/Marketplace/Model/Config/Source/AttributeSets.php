<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;

class AttributeSets implements OptionSourceInterface
{
    protected $collectionFactory;
    protected $eavConfig;

    public function __construct(
        CollectionFactory $collectionFactory,
        Config $eavConfig
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->eavConfig = $eavConfig;
    }

    public function toOptionArray()
    {
        $options = [];
        $entityTypeId = $this->eavConfig->getEntityType(Product::ENTITY)->getId();

        $collection = $this->collectionFactory->create();
        $collection->setEntityTypeFilter($entityTypeId);

        foreach ($collection as $attributeSet) {
            $options[] = [
                'value' => $attributeSet->getId(),
                'label' => $attributeSet->getAttributeSetName()
            ];
        }

        return $options;
    }
}
