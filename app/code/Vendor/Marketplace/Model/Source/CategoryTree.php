<?php
namespace Vendor\Marketplace\Model\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\CategoryRepository;

class CategoryTree
{
    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * Constructor
     *
     * @param CollectionFactory $categoryCollectionFactory
     * @param CategoryRepository $categoryRepository
     */
    public function __construct(
        CollectionFactory $categoryCollectionFactory,
        CategoryRepository $categoryRepository
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Get category tree data
     *
     * @return array
     */
    public function getCategoryTree()
    {
        try {
            $collection = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect(['name', 'entity_id', 'parent_id', 'level'])
                ->addIsActiveFilter()
                ->setOrder('path', 'ASC');

            $tree = [];
            foreach ($collection as $category) {
                if ($category->getLevel() >= 2) {
                    $tree[] = [
                        'value' => $category->getId(),
                        'label' => $category->getName(),
                        'is_active' => $category->getIsActive(),
                    ];
                }
            }
            return $tree;
        } catch (\Exception $e) {
            return [];
        }
    }
}
