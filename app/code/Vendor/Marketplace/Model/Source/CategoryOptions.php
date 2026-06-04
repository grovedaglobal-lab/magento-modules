<?php
namespace Vendor\Marketplace\Model\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class CategoryOptions implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(CollectionFactory $categoryCollectionFactory)
    {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Get category options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => '',
                'label' => __('Select a Category')
            ]
        ];

        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToSelect('name');
            $collection->addAttributeToSelect('level');
            $collection->setOrder('path', 'ASC');
            // Remove 'entry' filter which caused the crash.
            // keeping it simple to ensure data loads.

            foreach ($collection as $category) {
                // Skip root category (Level 0 and 1) - Default Category is usually Level 2
                if ($category->getLevel() < 2) {
                    continue;
                }
                
                // Indentation based on level
                $indent = str_repeat('. . ', max(0, $category->getLevel() - 2));
                
                $options[] = [
                    'value' => $category->getId(),
                    'label' => $indent . $category->getName()
                ];
            }
        } catch (\Exception $e) {
            // Fallback for debugging
            $options[] = ['value' => 'error', 'label' => 'Error: ' . $e->getMessage()];
        }

        return $options;
    }
}



