<?php
/**
 * Category-Based Package Size Suggestions
 * 
 * Suggests package sizes based on product category
 * Helps vendors quickly select appropriate sizes
 */
namespace Vendor\Marketplace\Model\Product\Attribute;

class PackageSizeSuggestions
{
    /**
     * Get package size suggestions by product category
     *
     * @return array
     */
    public static function getCategorySuggestions()
    {
        return [
            'electronics' => [
                'label' => 'Electronics',
                'sizes' => ['Single Unit', 'Bulk', 'Pack of 2', 'Pack of 5']
            ],
            'apparel' => [
                'label' => 'Apparel & Fashion',
                'sizes' => ['Small', 'Medium', 'Large', 'Extra Large', 'XXL']
            ],
            'food' => [
                'label' => 'Food & Beverages',
                'sizes' => ['100g', '250g', '500g', '1kg', '2kg', '5kg', '10kg']
            ],
            'beverages' => [
                'label' => 'Drinks',
                'sizes' => ['250ml', '500ml', '750ml', '1L', '1.5L', '2L', '5L']
            ],
            'cosmetics' => [
                'label' => 'Beauty & Cosmetics',
                'sizes' => ['10ml', '50ml', '100ml', '250ml', '500ml']
            ],
            'supplements' => [
                'label' => 'Health Supplements',
                'sizes' => ['Pack of 30', 'Pack of 60', 'Pack of 90', 'Pack of 120']
            ],
            'household' => [
                'label' => 'Household Cleaning',
                'sizes' => ['250ml', '500ml', '1L', '2L', '5L', '10L']
            ],
            'office' => [
                'label' => 'Office & Stationery',
                'sizes' => ['Pack of 6', 'Pack of 12', 'Pack of 24', 'Pack of 50']
            ],
        ];
    }

    /**
     * Get suggested sizes for a specific category
     *
     * @param string $categoryKey
     * @return array
     */
    public static function getByCategoryKey($categoryKey)
    {
        $suggestions = self::getCategorySuggestions();
        return isset($suggestions[$categoryKey]) ? $suggestions[$categoryKey]['sizes'] : [];
    }

    /**
     * Get all suggestion categories
     *
     * @return array
     */
    public static function getCategories()
    {
        $suggestions = self::getCategorySuggestions();
        $categories = [];
        foreach ($suggestions as $key => $data) {
            $categories[$key] = $data['label'];
        }
        return $categories;
    }
}
