<?php
namespace Vendor\Marketplace\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Data\OptionSourceInterface;

class ConfigurableAttributes implements OptionSourceInterface
{
    /**
     * @var AttributeCollectionFactory
     */
    protected $attributeCollectionFactory;

    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * Build selectable attributes for configurable variation dropdown visibility.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray()
    {
        $options = [];

        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('is_global', ScopedAttributeInterface::SCOPE_GLOBAL);
        $collection->addFieldToFilter('frontend_input', ['in' => ['select', 'boolean']]);
        $collection->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $label = (string) $attribute->getFrontendLabel();
            if ($label === '') {
                continue;
            }

            $options[] = [
                'value' => (int) $attribute->getAttributeId(),
                'label' => sprintf('%s (%s)', $label, $attribute->getAttributeCode())
            ];
        }

        return $options;
    }
}
