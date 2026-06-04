<?php
namespace Vendor\Marketplace\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Framework\Stdlib\ArrayManager;

class VendorSearch extends AbstractModifier
{
    /**
     * @var ArrayManager
     */
    protected $arrayManager;

    /**
     * @param ArrayManager $arrayManager
     */
    public function __construct(
        ArrayManager $arrayManager
    ) {
        $this->arrayManager = $arrayManager;
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data)
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta)
    {
        $attributeCode = 'vendor_id';

        $path = $this->arrayManager->findPath($attributeCode, $meta, null, 'children');

        if ($path) {
            $meta = $this->arrayManager->merge(
                $path . '/arguments/data/config',
                $meta,
                [
                    'component' => 'Magento_Ui/js/form/element/ui-select',
                    'elementTmpl' => 'ui/grid/filters/elements/ui-select',
                    'filterOptions' => true,
                    'multiple' => false,
                    'disableLabel' => true,
                ]
            );
        }

        return $meta;
    }
}
