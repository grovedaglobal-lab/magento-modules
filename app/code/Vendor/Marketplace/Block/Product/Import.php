<?php
namespace Vendor\Marketplace\Block\Product;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Import extends Template
{
    protected $categoryCollectionFactory;

    public function __construct(
        Context $context,
        CategoryCollectionFactory $categoryCollectionFactory,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getCategories()
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'path', 'level']);
        $collection->addAttributeToSelect('level');
        $collection->addAttributeToFilter('is_active', 1);
        $collection->setOrder('path', 'ASC');

        $categories = [];
        $categoryNames = [];
        foreach ($collection as $category) {
            if ($category->getLevel() < 2) {
                continue;
            }

            $pathIds = array_filter(explode('/', (string) $category->getPath()));
            $breadcrumb = [];

            foreach (array_slice($pathIds, 2) as $pathId) {
                if ($pathId == $category->getId()) {
                    $breadcrumb[] = $category->getName();
                } elseif (isset($categoryNames[$pathId])) {
                    $breadcrumb[] = $categoryNames[$pathId];
                }
            }

            $displayLabel = !empty($breadcrumb) ? implode(' > ', $breadcrumb) : $category->getName();

            $categories[] = [
                'value' => $category->getId(),
                'label' => $category->getName(),
                'display_label' => $displayLabel,
                'level' => $category->getLevel()
            ];

            $categoryNames[$category->getId()] = $category->getName();
        }

        return $categories;
    }
}